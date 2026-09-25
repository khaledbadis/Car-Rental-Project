<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Document;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleCategory;
use App\Modules\Rentals\Actions;
use App\Modules\Rentals\Ledger;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class RentalConcurrencyTest extends TestCase
{
    use DatabaseMigrations;

    private function race(bool $sameKey): void
    {
        $u = User::factory()->create(['role' => 'manager', 'active' => true]);
        $car = Vehicle::create(['registration' => 'FINRACE', 'make' => 'Test', 'model' => 'Car', 'year' => 2024, 'category_id' => VehicleCategory::create(['name' => 'Race'])->id, 'fuel_type' => 'petrol', 'transmission' => 'manual', 'mileage_km' => 0, 'daily_rate' => '100']);
        $customer = Customer::create(['type' => 'individual', 'name' => 'Ledger race']);
        foreach (['insurance', 'inspection'] as $type) {
            Document::create(['vehicle_id' => $car->id, 'type' => $type, 'path' => 'test', 'original_name' => 'test.pdf', 'mime' => 'application/pdf', 'size' => 10, 'expires_at' => now()->addYears(2)->toDateString(), 'uploaded_by' => $u->id]);
        }
        $r = app(Actions::class)->create($u, ['customer_id' => $customer->id, 'vehicle_id' => $car->id, 'starts_at' => now()->toIso8601String(), 'ends_at' => now()->addDay()->toIso8601String(), 'basis' => 'daily', 'discount_type' => 'none', 'idempotency_key' => (string) Str::uuid()]);
        $entry = ['kind' => 'deposit_received', 'amount' => '100', 'method' => 'cash', 'reason' => 'Test funds', 'effective_at' => now()->toIso8601String(), 'idempotency_key' => (string) Str::uuid()];
        app(Ledger::class)->post($u, 'rental', $r->id, $entry);
        $entry['kind'] = $sameKey ? 'payment' : 'deposit_applied';
        $entry['amount'] = $sameKey ? '1' : '60';
        $entry['idempotency_key'] = (string) Str::uuid();
        $processes = [];
        DB::beginTransaction();
        DB::table('agency_settings')->where('id', 1)->lockForUpdate()->first();
        try {
            for ($i = 0; $i < 2; $i++) {
                if (! $sameKey) {
                    $entry['idempotency_key'] = (string) Str::uuid();
                }$p = new Process([PHP_BINARY, base_path('tests/Support/post-ledger.php'), (string) $u->id, (string) $r->id, json_encode($entry)], base_path(), ['APP_ENV' => 'testing', 'DB_DATABASE' => 'rental_test', 'DB_URL' => '']);
                $p->setTimeout(20);
                $p->start();
                $processes[] = $p;
            }$deadline = microtime(true) + 10;
            do {
                $ready = count(array_filter($processes, fn ($p) => str_contains($p->getOutput(), 'READY')));
                if ($ready === 2) {
                    break;
                }usleep(10000);
            } while (microtime(true) < $deadline);
            $this->assertSame(2, $ready);
            DB::commit();
            $codes = [];
            foreach ($processes as $p) {
                $codes[] = $p->wait();
                $this->assertSame('', $p->getErrorOutput());
            }sort($codes);
            $this->assertSame($sameKey ? [0, 0] : [0, 2], $codes);
            $this->assertDatabaseCount('ledger_entries', 3);
            $totals = app(Ledger::class)->totals($r->account);
            $this->assertSame($sameKey ? 100 : 6000, $totals['payments_cents']);
            $this->assertSame($sameKey ? 10000 : 4000, $totals['held_cents']);
        } finally {
            if (DB::transactionLevel()) {
                DB::rollBack();
            }foreach ($processes as $p) {
                if ($p->isRunning()) {
                    $p->stop();
                }
            }
        }
    }

    public function test_concurrent_deposit_applications_cannot_overspend(): void
    {
        $this->race(false);
    }

    public function test_concurrent_retries_post_one_payment(): void
    {
        $this->race(true);
    }
}
