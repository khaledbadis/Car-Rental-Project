<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Document;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleCategory;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class ReservationConcurrencyTest extends TestCase
{
    use DatabaseMigrations;

    public function test_simultaneous_confirmations_cannot_double_book(): void
    {
        $u = User::factory()->create(['role' => 'manager', 'active' => true]);
        $car = Vehicle::create(['registration' => 'RACE01', 'make' => 'Test', 'model' => 'Car', 'year' => 2024, 'category_id' => VehicleCategory::create(['name' => 'Race'])->id, 'fuel_type' => 'petrol', 'transmission' => 'manual', 'mileage_km' => 0, 'daily_rate' => '1000']);
        $customer = Customer::create(['type' => 'individual', 'name' => 'Race customer']);
        foreach (['insurance', 'inspection'] as $type) {
            Document::create(['vehicle_id' => $car->id, 'type' => $type, 'path' => 'test', 'original_name' => 'test.pdf', 'mime' => 'application/pdf', 'size' => 10, 'expires_at' => '2030-12-31', 'uploaded_by' => $u->id]);
        }
        $input = ['customer_id' => $customer->id, 'vehicle_id' => $car->id, 'status' => 'confirmed', 'starts_at' => '2027-01-10T10:00:00+01:00', 'ends_at' => '2027-01-11T10:00:00+01:00'];
        $processes = [];
        DB::beginTransaction();
        DB::table('agency_settings')->where('id', 1)->lockForUpdate()->first();
        try {
            for ($i = 0; $i < 2; $i++) {
                $p = new Process([PHP_BINARY, base_path('tests/Support/confirm-reservation.php'), (string) $u->id, json_encode($input)], base_path(), ['APP_ENV' => 'testing', 'DB_DATABASE' => 'rental_test', 'DB_URL' => '']);
                $p->setTimeout(20);
                $p->start();
                $processes[] = $p;
            }
            $deadline = microtime(true) + 10;
            do {
                $ready = count(array_filter($processes, fn ($p) => str_contains($p->getOutput(), 'READY')));
                if ($ready === 2) {
                    break;
                }usleep(10000);
            } while (microtime(true) < $deadline);
            $this->assertSame(2, $ready, 'Both independent workers must reach the locked transaction.');
            DB::commit();
            $codes = [];
            foreach ($processes as $p) {
                $codes[] = $p->wait();
                $this->assertSame('', $p->getErrorOutput());
            }sort($codes);
            $this->assertSame([0, 2], $codes);
            $this->assertDatabaseCount('reservations', 1);
            $this->assertDatabaseCount('vehicle_commitments', 1);
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
}
