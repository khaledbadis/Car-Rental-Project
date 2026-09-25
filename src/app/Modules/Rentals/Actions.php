<?php

namespace App\Modules\Rentals;

use App\Models\Customer;
use App\Models\Inspection;
use App\Models\InspectionPhoto;
use App\Models\Rental;
use App\Models\Reservation;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleCommitment;
use App\Modules\Foundation\Actions as Audit;
use App\Modules\Reservations\Actions as Schedule;
use App\Modules\Reservations\Conflict as ScheduleConflict;
use App\Support\Input;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class Actions
{
    public function query(User $u, array $filters = [])
    {
        Gate::forUser($u)->authorize('rentals.view');
        $v = Validator::make(Input::normalize($filters), ['status' => 'nullable|in:draft,active,returned,cancelled,overdue,debt', 'customer_id' => 'nullable|integer', 'vehicle_id' => 'nullable|integer'])->validate();

        return Rental::with(['customer:id,name', 'vehicle:id,registration,make,model', 'account'])->when($v['status'] ?? null, function ($q, $s) {
            if ($s === 'overdue') {
                $q->where('status', 'active')->where('ends_at', '<', now());
            } elseif ($s === 'debt') {
                $q->whereHas('account', fn ($q) => $q->whereRaw('(SELECT COALESCE(SUM(charge_delta-payment_delta),0) FROM ledger_entries WHERE financial_account_id=financial_accounts.id)>0'));
            } else {
                $q->where('status', $s);
            }
        })->when($v['customer_id'] ?? null, fn ($q, $id) => $q->where('customer_id', $id))->when($v['vehicle_id'] ?? null, fn ($q, $id) => $q->where('vehicle_id', $id))->orderByDesc('id');
    }

    public function find(User $u, int $id): Rental
    {
        Gate::forUser($u)->authorize('rentals.view');

        return Rental::with(['customer', 'driver', 'vehicle', 'account', 'inspections.photos'])->findOrFail($id);
    }

    private function lockVehicle(int $id): Vehicle
    {
        return Vehicle::lockForUpdate()->findOrFail($id);
    }

    private function version(Rental $r, array $v): void
    {
        if ($r->version !== (int) $v['version']) {
            throw new Conflict('stale');
        }
    }

    private function documents(User $u, Vehicle $car, CarbonImmutable $end, ?string $reason, int $rentalId): void
    {
        $issues = app(Schedule::class)->documentIssues($car, $end);
        if (! $issues) {
            return;
        }
        if (! $reason) {
            throw new ScheduleConflict('documents', ['document_issues' => $issues]);
        }Gate::forUser($u)->authorize('vehicle-documents.override');
        app(Audit::class)->audit($u, 'rental.document_override', 'rental', $rentalId, null, ['vehicle_id' => $car->id, 'through' => $end->toIso8601String(), 'issues' => $issues], $reason);
    }

    private function snapshot(Customer $customer, ?Customer $driver, Vehicle $car): array
    {
        return ['customer' => $customer->only(['id', 'type', 'name', 'phone', 'address', 'contact_person', 'tax_identifier']), 'driver' => $driver?->only(['id', 'name', 'birth_date', 'identity_type', 'identity_number', 'identity_issue_date', 'identity_expiry_date', 'licence_number', 'licence_issue_date', 'licence_expiry_date', 'licence_country']), 'vehicle' => $car->only(['id', 'registration', 'make', 'model', 'year', 'category_id'])];
    }

    private function contract(User $u, Rental $r, string $reason): void
    {
        DB::table('rental_versions')->insert(['rental_id' => $r->id, 'number' => $r->contract_version, 'snapshot' => json_encode(['parties' => $r->snapshot, 'pricing' => $r->pricing, 'starts_at' => $r->starts_at->toIso8601String(), 'ends_at' => $r->ends_at->toIso8601String(), 'mileage_allowance' => $r->mileage_allowance, 'terms' => $r->terms], JSON_THROW_ON_ERROR), 'actor_id' => $u->id, 'reason' => $reason, 'created_at' => now()]);
    }

    public function create(User $u, array $input): Rental
    {
        Gate::forUser($u)->authorize('rentals.manage');

        return app(Operations::class)->run($u, 'rental.create', $input, function ($in) use ($u) {
            $v = Validator::make($in, ['reservation_id' => 'nullable|integer|exists:reservations,id', 'reservation_version' => 'required_with:reservation_id|integer|min:1', 'customer_id' => 'required_without:reservation_id|nullable|integer|exists:customers,id', 'vehicle_id' => 'required_without:reservation_id|nullable|integer|exists:vehicles,id', 'driver_id' => 'nullable|integer|exists:customers,id', 'mileage_allowance' => 'nullable|integer|min:0|max:9999999', 'terms' => 'nullable|string|max:4000', 'document_override_reason' => 'nullable|string|max:500'])->validate();
            $reservation = ! empty($v['reservation_id']) ? Reservation::lockForUpdate()->findOrFail($v['reservation_id']) : null;
            if ($reservation) {
                if ($reservation->version !== (int) $v['reservation_version']) {
                    throw new Conflict('stale');
                }
                if ($reservation->status !== 'confirmed') {
                    throw new Conflict('reservation_state');
                }
                if (Rental::where('reservation_id', $reservation->id)->exists()) {
                    throw new Conflict('reservation_state');
                }
                $customerId = $reservation->customer_id;
                $vehicleId = $reservation->vehicle_id;
                $start = $reservation->starts_at;
                $end = $reservation->ends_at;
                $buffer = $reservation->preparation_minutes;
            } else {
                [$start,$end] = app(Schedule::class)->interval($in);
                $customerId = $v['customer_id'];
                $vehicleId = $v['vehicle_id'];
                $buffer = app(Schedule::class)->settings()->preparation_minutes;
            }
            $car = $this->lockVehicle($vehicleId);
            $customer = Customer::lockForUpdate()->findOrFail($customerId);
            if ($car->archived_at || $customer->archived_at) {
                throw new Conflict('archived');
            }
            $driverId = $customer->type === 'individual' ? $customer->id : ($v['driver_id'] ?? $customer->driver_id);
            $driver = $driverId ? Customer::lockForUpdate()->findOrFail($driverId) : null;
            if ($driver && ($driver->type !== 'individual' || $driver->archived_at)) {
                throw new Conflict('driver_ineligible');
            }
            if ($car->entered_service_at && $car->entered_service_at->format('Y-m-d') > $start->setTimezone('Africa/Algiers')->format('Y-m-d')) {
                throw new ScheduleConflict('before_service');
            }
            if (app(Schedule::class)->conflicts($car->id, $start, $end->addMinutes($buffer), $reservation?->id)->isNotEmpty()) {
                throw new ScheduleConflict('overlap');
            }
            $pricing = app(Pricing::class)->quote($u, $car, $start, $end, $in);
            $r = Rental::create(['status' => 'draft', 'version' => 1, 'contract_version' => 1, 'reservation_id' => $reservation?->id, 'customer_id' => $customer->id, 'driver_id' => $driver?->id, 'vehicle_id' => $car->id, 'starts_at' => $start, 'ends_at' => $end, 'preparation_minutes' => $buffer, 'pricing' => $pricing, 'snapshot' => $this->snapshot($customer, $driver, $car), 'mileage_allowance' => $v['mileage_allowance'] ?? null, 'terms' => $v['terms'] ?? null, 'created_by' => $u->id]);
            $this->documents($u, $car, $end, $v['document_override_reason'] ?? null, $r->id);
            if ($reservation) {
                VehicleCommitment::where('reservation_id', $reservation->id)->update(['reservation_id' => null, 'rental_id' => $r->id, 'kind' => 'rental']);
                $reservation->update(['status' => 'converted', 'version' => $reservation->version + 1]);
                $account = app(Ledger::class)->account('reservation', $reservation->id);
                $account->update(['rental_id' => $r->id]);
            } else {
                $account = app(Ledger::class)->account('rental', $r->id);
                VehicleCommitment::create(['vehicle_id' => $car->id, 'rental_id' => $r->id, 'kind' => 'rental', 'starts_at' => $start, 'ends_at' => $end->addMinutes($buffer), 'created_by' => $u->id]);
            }
            app(Ledger::class)->base($u, $account, $pricing['total_cents'], __('rental.initial_price'));
            $this->contract($u, $r, __('rental.initial_agreement'));
            app(Audit::class)->audit($u, 'rental.created', 'rental', $r->id, null, ['reservation_id' => $reservation?->id, 'vehicle_id' => $car->id, 'pricing' => $pricing], $pricing['reason']);

            return $r;
        }, fn ($id) => Rental::findOrFail($id));
    }

    private function eligibleDriver(Rental $r, CarbonImmutable $at): void
    {
        $customer = Customer::lockForUpdate()->findOrFail($r->customer_id);
        $driver = $r->driver_id ? Customer::lockForUpdate()->findOrFail($r->driver_id) : null;
        if ($customer->archived_at || ! $driver || $driver->archived_at || $driver->type !== 'individual') {
            throw new Conflict('driver_ineligible');
        }
        $fields = ['name', 'birth_date', 'phone', 'address', 'identity_type', 'identity_number', 'identity_issue_date', 'identity_expiry_date', 'licence_number', 'licence_issue_date', 'licence_expiry_date', 'licence_country'];
        $missing = [];
        foreach ($fields as $f) {
            if (! $driver->$f) {
                $missing[] = $f;
            }
        }
        if ($customer->type === 'company') {
            foreach (['name', 'phone', 'address', 'contact_person'] as $f) {
                if (! $customer->$f) {
                    $missing[] = 'company_'.$f;
                }
            }
        }
        foreach (['identity', 'licence'] as $type) {
            if (! $driver->documents()->where('type', $type)->exists()) {
                $missing[] = $type.'_scan';
            }
        }
        if ($missing) {
            throw new Conflict('driver_incomplete', ['missing' => $missing]);
        }
        $date = $at->setTimezone('Africa/Algiers')->format('Y-m-d');
        if ($driver->licence_expiry_date->format('Y-m-d') < $date || ($driver->identity_expiry_date && $driver->identity_expiry_date->format('Y-m-d') < $date)) {
            throw new Conflict('driver_expired');
        }
        if ($driver->birth_date->format('Y-m-d') >= $date || $driver->licence_issue_date->format('Y-m-d') > $date || ($driver->identity_issue_date && $driver->identity_issue_date->format('Y-m-d') > $date)) {
            throw new Conflict('driver_ineligible');
        }
    }

    public function inspect(User $u, int $id, string $kind, array $input): Rental
    {
        Gate::forUser($u)->authorize('rentals.manage');
        abort_unless(in_array($kind, ['handover', 'return']), 404);

        return app(Operations::class)->run($u, 'rental.'.$kind.':'.$id, $input, function ($in) use ($u, $id, $kind) {
            $v = Validator::make($in, ['driver_id' => 'nullable|integer|exists:customers,id', 'version' => 'required|integer|min:1', 'occurred_at' => 'required|date_format:Y-m-d\TH:i:sP|before_or_equal:now', 'mileage_km' => 'required|integer|min:0|max:9999999', 'fuel_percent' => 'required|integer|min:0|max:100', 'condition_confirmed' => 'required|accepted', 'condition_notes' => 'required|string|max:4000', 'correction_reason' => 'nullable|string|max:500', 'document_override_reason' => 'nullable|string|max:500'])->validate();
            $r = Rental::lockForUpdate()->findOrFail($id);
            $this->version($r, $v);
            $car = $this->lockVehicle($r->vehicle_id);
            $at = CarbonImmutable::parse($v['occurred_at'])->utc();
            if ($kind === 'handover') {
                if ($r->status !== 'draft' || $at->gte($r->ends_at)) {
                    throw new Conflict('state');
                }
                if ($car->archived_at) {
                    throw new Conflict('archived');
                }
                if ($r->customer->type === 'company') {
                    $r->driver_id = $v['driver_id'] ?? $r->driver_id ?? $r->customer->driver_id;
                    $r->unsetRelation('driver');
                }
                $this->eligibleDriver($r, $at);
                if (Rental::where('vehicle_id', $car->id)->where('status', 'active')->where('id', '!=', $id)->exists()) {
                    throw new Conflict('physically_out');
                }
                if (app(Schedule::class)->conflicts($car->id, $at, $r->ends_at->addMinutes($r->preparation_minutes), null, $r->id)->isNotEmpty()) {
                    throw new ScheduleConflict('overlap');
                }
                $this->documents($u, $car, $r->ends_at, $v['document_override_reason'] ?? null, $r->id);
                $min = $car->mileage_km;
                if ($v['mileage_km'] < $min) {
                    throw new Conflict('mileage_error');
                }
                VehicleCommitment::where('rental_id', $id)->update(['starts_at' => $at]);
                $r->fill(['status' => 'active', 'handed_over_at' => $at, 'snapshot' => $this->snapshot($r->customer, $r->driver, $car), 'contract_version' => $r->contract_version + 1]);
            } else {
                if ($r->status !== 'active' || $at->lt($r->handed_over_at)) {
                    throw new Conflict('state');
                }
                $start = Inspection::where('rental_id', $id)->where('kind', 'handover')->firstOrFail();
                if ($v['mileage_km'] < $start->mileage_km || $v['mileage_km'] < $car->mileage_km) {
                    Gate::forUser($u)->authorize('pricing.override');
                    if (empty($v['correction_reason'])) {
                        throw new Conflict('mileage_error');
                    }
                    app(Audit::class)->audit($u, 'rental.mileage_corrected', 'rental', $id, ['mileage' => $start->mileage_km], ['mileage' => $v['mileage_km']], $v['correction_reason']);
                }
                VehicleCommitment::where('rental_id', $id)->update(['released_at' => now(), 'release_reason' => 'Vehicle returned']);
                if ($r->preparation_minutes > 0) {
                    VehicleCommitment::create(['vehicle_id' => $car->id, 'kind' => 'preparation', 'starts_at' => $at, 'ends_at' => $at->addMinutes($r->preparation_minutes), 'emergency' => true, 'reason' => __('rental.return_preparation', ['id' => $id]), 'created_by' => $u->id]);
                }
                $r->fill(['status' => 'returned', 'returned_at' => $at]);
            }
            $r->version++;
            $r->save();
            $car->update(['mileage_km' => $v['mileage_km'], 'version' => $car->version + 1]);
            Inspection::create(['rental_id' => $id, 'kind' => $kind, 'occurred_at' => $at, 'mileage_km' => $v['mileage_km'], 'fuel_percent' => $v['fuel_percent'], 'condition_notes' => $v['condition_notes'], 'correction_reason' => $v['correction_reason'] ?? null, 'actor_id' => $u->id, 'created_at' => now()]);
            if ($kind === 'handover') {
                $this->contract($u, $r, __('rental.handover_snapshot'));
            }
            app(Audit::class)->audit($u, 'rental.'.$kind, 'rental', $id, null, ['status' => $r->status, 'occurred_at' => $at->toIso8601String(), 'mileage_km' => $v['mileage_km'], 'fuel_percent' => $v['fuel_percent']]);

            return $r;
        }, fn ($result) => Rental::findOrFail($result));
    }

    public function extend(User $u, int $id, array $input): Rental
    {
        Gate::forUser($u)->authorize('rentals.manage');

        return app(Operations::class)->run($u, 'rental.extend:'.$id, $input, function ($in) use ($u, $id) {
            $v = Validator::make($in, ['version' => 'required|integer|min:1', 'ends_at' => 'required|date_format:Y-m-d\TH:i:sP', 'reason' => 'required|string|max:500', 'document_override_reason' => 'nullable|string|max:500'])->validate();
            $r = Rental::lockForUpdate()->findOrFail($id);
            $this->version($r, $v);
            if (! in_array($r->status, ['draft', 'active'])) {
                throw new Conflict('state');
            }
            $end = CarbonImmutable::parse($v['ends_at'])->utc();
            if ($end->lte($r->ends_at) || ($r->status === 'active' && $end->lte(now()))) {
                throw new Conflict('dates');
            }
            $car = $this->lockVehicle($r->vehicle_id);
            if (app(Schedule::class)->conflicts($car->id, $r->handed_over_at ?? $r->starts_at, $end->addMinutes($r->preparation_minutes), null, $id)->isNotEmpty()) {
                throw new ScheduleConflict('overlap');
            }
            $this->documents($u, $car, $end, $v['document_override_reason'] ?? null, $id);
            $pricing = app(Pricing::class)->quote($u, $car, $r->starts_at, $end, $in, $r->pricing);
            $before = $r->only(['ends_at', 'pricing', 'contract_version']);
            $r->update(['ends_at' => $end, 'pricing' => $pricing, 'version' => $r->version + 1, 'contract_version' => $r->contract_version + 1]);
            VehicleCommitment::where('rental_id', $id)->update(['ends_at' => $end->addMinutes($r->preparation_minutes)]);
            app(Ledger::class)->base($u, $r->account, $pricing['total_cents'], $v['reason']);
            $this->contract($u, $r, $v['reason']);
            app(Audit::class)->audit($u, 'rental.extended', 'rental', $id, $before, $r->only(['ends_at', 'pricing', 'contract_version']), $v['reason']);

            return $r;
        }, fn ($result) => Rental::findOrFail($result));
    }

    public function cancel(User $u, int $id, array $input): Rental
    {
        Gate::forUser($u)->authorize('rentals.manage');

        return app(Operations::class)->run($u, 'rental.cancel:'.$id, $input, function ($in) use ($u, $id) {
            $v = Validator::make($in, ['version' => 'required|integer|min:1', 'reason' => 'required|string|max:500'])->validate();
            $r = Rental::lockForUpdate()->findOrFail($id);
            $this->version($r, $v);
            if ($r->status !== 'draft') {
                throw new Conflict('state');
            }
            $this->lockVehicle($r->vehicle_id);
            $r->update(['status' => 'cancelled', 'cancellation_reason' => $v['reason'], 'version' => $r->version + 1]);
            VehicleCommitment::where('rental_id', $id)->update(['released_at' => now(), 'release_reason' => $v['reason']]);
            app(Ledger::class)->base($u, $r->account, 0, $v['reason']);
            app(Audit::class)->audit($u, 'rental.cancelled', 'rental', $id, null, ['status' => 'cancelled'], $v['reason']);

            return $r;
        }, fn ($result) => Rental::findOrFail($result));
    }

    public function photo(User $u, int $inspectionId, array $input): InspectionPhoto
    {
        Gate::forUser($u)->authorize('rentals.manage');
        $inspection = Inspection::findOrFail($inspectionId);
        $limit = min(10, (int) app(Schedule::class)->settings()->upload_limit_mb) * 1024;
        $v = Validator::make($input, ['file' => 'required|file|mimes:jpg,jpeg,png|max:'.$limit, 'idempotency_key' => 'required|uuid'])->validate();
        $file = $v['file'];
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file->getRealPath());
        if (! in_array($mime, ['image/jpeg', 'image/png'])) {
            throw ValidationException::withMessages(['file' => __('rental.image_only')]);
        }
        $hash = hash_file('sha256', $file->getRealPath());
        $path = null;
        try {
            return app(Operations::class)->run($u, 'inspection.photo:'.$inspectionId, ['idempotency_key' => $v['idempotency_key'], 'sha256' => $hash], function () use ($u, $file, $mime, $inspection, &$path) {
                $path = $file->store('inspections', 'local');
                if (! $path) {
                    throw new \RuntimeException('Private photo storage failed');
                }
                $p = InspectionPhoto::create(['inspection_id' => $inspection->id, 'path' => $path, 'mime' => $mime, 'size' => $file->getSize(), 'actor_id' => $u->id, 'created_at' => now()]);
                app(Audit::class)->audit($u, 'inspection.photo_added', 'rental', $inspection->rental_id, null, ['photo_id' => $p->id]);

                return $p;
            }, fn ($id) => InspectionPhoto::findOrFail($id));
        } catch (\Throwable $e) {
            if ($path) {
                Storage::disk('local')->delete($path);
            }throw $e;
        }
    }
}
