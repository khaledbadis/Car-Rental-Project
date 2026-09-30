<?php

namespace App\Modules\Release;

use App\Models\Customer;
use App\Models\LedgerEntry;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleCategory;
use App\Modules\Foundation\Actions as Audit;
use App\Modules\Rentals\Actions;
use App\Modules\Rentals\Ledger;
use App\Modules\Rentals\Money;
use App\Modules\Reservations\Actions as Schedule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class CutoverRehearsal
{
    // Deliberately rollback-only: sample records and historical opening amounts must never leak into operations.
    public function run(User $actor): array
    {
        abort_unless(app()->environment(['local', 'testing']), 403);
        Gate::forUser($actor)->authorize('settings.manage');
        $sample = json_decode(file_get_contents(database_path('fixtures/cutover-sample.json')), true, 512, JSON_THROW_ON_ERROR);
        DB::beginTransaction();
        try {
            DB::table('agency_settings')->where('id', 1)->lockForUpdate()->first();
            $category = VehicleCategory::create(['name' => 'Fictional migration rehearsal '.Str::random(8)]);
            $cars = [];
            $customers = [];
            $accounts = [];
            foreach ($sample['vehicles'] as $v) {
                $cars[$v['source_id']] = Vehicle::create(['registration' => $v['registration'], 'make' => $v['make'], 'model' => $v['model'], 'year' => 2024, 'category_id' => $category->id, 'fuel_type' => 'petrol', 'transmission' => 'automatic', 'mileage_km' => 100, 'daily_rate' => $v['daily_rate']]);
            }
            foreach ($sample['customers'] as $c) {
                $customers[$c['source_id']] = Customer::create(['type' => 'individual', 'name' => $c['name']]);
            }
            foreach ($sample['active_rentals'] as $source) {
                $r = app(Actions::class)->create($actor, ['customer_id' => $customers[$source['customer']]->id, 'vehicle_id' => $cars[$source['vehicle']]->id, 'starts_at' => now()->subDays($source['started_days_ago'])->toIso8601String(), 'ends_at' => now()->addDays($source['ends_in_days'])->toIso8601String(), 'basis' => 'daily', 'discount_type' => 'none', 'document_override_reason' => 'Fictional migration rehearsal; legacy documents require review', 'idempotency_key' => (string) Str::uuid()]);
                // Imported already-active state is not a new handover. Preserve its source timestamp.
                $r->update(['status' => 'active', 'handed_over_at' => $r->starts_at]);
                $account = $r->account;
                app(Ledger::class)->base($actor, $account, 0, 'Replace simulated price with exact legacy opening debt');
                foreach (['charge' => 'outstanding', 'deposit_received' => 'held_deposit'] as $kind => $field) {
                    $amount = Money::cents($source[$field]);
                    LedgerEntry::create(['financial_account_id' => $account->id, 'kind' => $kind, 'category' => 'opening', 'amount_cents' => $amount, 'charge_delta' => $kind === 'charge' ? $amount : 0, 'deposit_delta' => $kind === 'deposit_received' ? $amount : 0, 'payment_delta' => 0, 'reason' => 'Opening legacy balance '.$source['source_id'].'; not new cash/revenue', 'effective_at' => now(), 'actor_id' => $actor->id, 'created_at' => now()]);
                }
                app(Audit::class)->audit($actor, 'migration.rehearsed', 'rental', $r->id, null, ['source_id' => $source['source_id']]);
                if (app(Schedule::class)->conflicts($r->vehicle_id, now()->toImmutable(), now()->addHour()->toImmutable())->isEmpty()) {
                    throw new \RuntimeException('Imported active occupancy missing.');
                }
                $accounts[] = $account;
            }
            foreach ($sample['reservations'] as $source) {
                app(Schedule::class)->save($actor, ['status' => 'confirmed', 'customer_id' => $customers[$source['customer']]->id, 'vehicle_id' => $cars[$source['vehicle']]->id, 'starts_at' => now()->addDays($source['starts_in_days'])->toIso8601String(), 'ends_at' => now()->addDays($source['ends_in_days'])->toIso8601String(), 'document_override_reason' => 'Fictional migration rehearsal']);
            }
            $totals = ['vehicles' => count($cars), 'customers' => count($customers), 'active_rentals' => count($sample['active_rentals']), 'reservations' => count($sample['reservations']), 'outstanding_cents' => 0, 'held_cents' => 0];
            foreach ($accounts as $account) {
                $balance = app(Ledger::class)->totals($account);
                $totals['outstanding_cents'] += $balance['outstanding_cents'];
                $totals['held_cents'] += $balance['held_cents'];
            }
            if ($totals !== $sample['expected']) {
                throw new \RuntimeException('Reconciliation failed');
            }

            return ['mode' => 'rollback-only', 'expected' => $sample['expected'], 'actual' => $totals, 'active_occupancy_verified' => true];
        } finally {
            DB::rollBack();
        }
    }
}
