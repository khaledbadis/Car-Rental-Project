<?php

namespace App\Modules\Reservations;

use App\Models\Customer;
use App\Models\FinancialAccount;
use App\Models\Rental;
use App\Models\Reservation;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleCommitment;
use App\Modules\Foundation\Actions as Foundation;
use App\Support\Input;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class Actions
{
    public const BLOCKS = ['maintenance', 'cleaning', 'inspection', 'preparation', 'temporary', 'administrative'];

    public function authorize(User $u, string $permission = 'reservations.view'): void
    {
        Gate::forUser($u)->authorize($permission);
    }

    public function settings(): object
    {
        return DB::table('agency_settings')->where('id', 1)->first();
    }

    // Schedule writers take the agency lock before vehicle rows, matching catalog edits. Vehicle locks
    // are acquired in ID order, so reassignment cannot deadlock with a competing booking.
    private function lock(array $ids = []): void
    {
        DB::table('agency_settings')->where('id', 1)->lockForUpdate()->first();
        Vehicle::whereIn('id', array_filter($ids))->orderBy('id')->lockForUpdate()->get();
    }

    public function interval(array $input, bool $indefinite = false): array
    {
        $v = Validator::make(Input::normalize($input), ['starts_at' => 'required|date_format:Y-m-d\TH:i:sP', 'ends_at' => ($indefinite ? 'nullable' : 'required').'|date_format:Y-m-d\TH:i:sP|after:starts_at'])->validate();

        return [CarbonImmutable::parse($v['starts_at'])->utc(), empty($v['ends_at']) ? null : CarbonImmutable::parse($v['ends_at'])->utc()];
    }

    public function conflicts(int $vehicle, CarbonImmutable $start, ?CarbonImmutable $end, ?int $except = null, ?int $exceptRental = null)
    {
        return VehicleCommitment::where('vehicle_id', $vehicle)->whereNull('released_at')
            ->when($except, fn ($q) => $q->where(fn ($q) => $q->whereNull('reservation_id')->orWhere('reservation_id', '!=', $except)))
            ->when($exceptRental, fn ($q) => $q->where(fn ($q) => $q->whereNull('rental_id')->orWhere('rental_id', '!=', $exceptRental)))
            ->when($end, fn ($q) => $q->where('starts_at', '<', $end))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', $start)->orWhereIn('rental_id', Rental::where('status', 'active')->where('ends_at', '<', now())->select('id')))->orderBy('starts_at')->get();
    }

    public function documentIssues(Vehicle $vehicle, CarbonImmutable $end): array
    {
        $issues = [];
        foreach (['insurance', 'inspection'] as $type) {
            // Latest uploaded replacement is authoritative, even if it has no known expiry.
            $doc = $vehicle->documents()->where('type', $type)->latest('id')->first();
            if (! $doc || ! $doc->expires_at) {
                $issues[] = ['type' => $type, 'code' => 'document_missing'];
            } elseif ($doc->expires_at->format('Y-m-d') < $end->setTimezone('Africa/Algiers')->format('Y-m-d')) {
                $issues[] = ['type' => $type, 'code' => 'document_expired', 'expires_at' => $doc->expires_at->format('Y-m-d')];
            }
        }

        return $issues;
    }

    public function query(User $u, array $filters = [])
    {
        $this->authorize($u);
        $v = Validator::make(Input::normalize($filters), ['status' => 'nullable|in:tentative,confirmed,cancelled,converted', 'from' => 'nullable|date_format:Y-m-d', 'to' => ['nullable', 'date_format:Y-m-d', ...(! empty($filters['from']) ? ['after_or_equal:from'] : [])], 'vehicle_id' => 'nullable|integer', 'customer_id' => 'nullable|integer'])->validate();

        return Reservation::with(['customer:id,name', 'vehicle:id,registration,make,model', 'category:id,name'])
            ->when($v['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->when($v['vehicle_id'] ?? null, fn ($q, $id) => $q->where('vehicle_id', $id))
            ->when($v['customer_id'] ?? null, fn ($q, $id) => $q->where('customer_id', $id))
            ->when($v['from'] ?? null, fn ($q, $s) => $q->whereRaw("ends_at + preparation_minutes * interval '1 minute' > ?", [CarbonImmutable::parse($s, 'Africa/Algiers')->utc()]))
            ->when($v['to'] ?? null, fn ($q, $s) => $q->where('starts_at', '<', CarbonImmutable::parse($s, 'Africa/Algiers')->addDay()->utc()))->orderBy('starts_at')->orderBy('id');
    }

    public function find(User $u, int $id): Reservation
    {
        $this->authorize($u);

        return Reservation::with(['customer:id,name', 'vehicle:id,registration,make,model', 'category:id,name'])->findOrFail($id);
    }

    public function availability(User $u, array $input): array
    {
        $this->authorize($u);
        [$start,$end] = $this->interval($input);
        $v = Validator::make(Input::normalize($input), ['category_id' => 'nullable|integer|exists:vehicle_categories,id', 'transmission' => 'nullable|in:manual,automatic'])->validate();
        $buffer = (int) $this->settings()->preparation_minutes;

        return Vehicle::whereNull('archived_at')->when($v['category_id'] ?? null, fn ($q, $c) => $q->where('category_id', $c))->when($v['transmission'] ?? null, fn ($q, $t) => $q->where('transmission', $t))->orderBy('registration')->get()->map(function ($car) use ($start, $end, $buffer) {
            $conflicts = $this->conflicts($car->id, $start, $end->addMinutes($buffer));
            $docs = $this->documentIssues($car, $end);
            $service = $car->entered_service_at && $car->entered_service_at->format('Y-m-d') > $start->setTimezone('Africa/Algiers')->format('Y-m-d');

            return ['vehicle' => $car->only(['id', 'registration', 'make', 'model', 'category_id', 'transmission']), 'available' => $conflicts->isEmpty() && ! $docs && ! $service, 'conflicts' => $conflicts, 'document_issues' => $docs, 'before_service' => (bool) $service, 'preparation_minutes' => $buffer];
        })->all();
    }

    public function save(User $u, array $input, ?int $id = null): Reservation
    {
        $this->authorize($u, 'reservations.manage');
        [$start,$end] = $this->interval($input);
        $v = Validator::make(Input::normalize($input), [
            'customer_id' => 'required|integer|exists:customers,id', 'category_id' => 'nullable|integer|exists:vehicle_categories,id', 'vehicle_id' => 'nullable|integer|exists:vehicles,id', 'status' => 'required|in:tentative,confirmed', 'notes' => 'nullable|string|max:4000', 'document_override_reason' => 'nullable|string|max:500',
            'version' => ($id ? 'required' : 'nullable').'|integer|min:1', 'reason' => ($id ? 'required' : 'nullable').'|string|max:500',
        ])->validate();

        return DB::transaction(function () use ($u, $v, $start, $end, $id) {
            $this->lock();
            $r = $id ? Reservation::lockForUpdate()->findOrFail($id) : new Reservation;
            if ($id && $r->version !== (int) $v['version']) {
                throw new Conflict('stale');
            }
            if ($id && in_array($r->status, ['cancelled', 'converted'])) {
                throw new Conflict('closed');
            }
            if ($r->status === 'confirmed' && $v['status'] === 'tentative') {
                throw new Conflict('cannot_unconfirm');
            }
            if ($id && $r->customer_id !== (int) $v['customer_id'] && FinancialAccount::where('reservation_id', $id)->whereHas('entries')->exists()) {
                throw new Conflict('customer_locked');
            }
            $customer = Customer::lockForUpdate()->findOrFail($v['customer_id']);
            if ($customer->archived_at) {
                throw new Conflict('archived');
            }
            $vehicleId = ! empty($v['vehicle_id']) ? (int) $v['vehicle_id'] : null;
            if ($v['status'] === 'confirmed' && ! $vehicleId) {
                throw ValidationException::withMessages(['vehicle_id' => __('booking.vehicle_required')]);
            }
            $this->lock([$r->vehicle_id, $vehicleId]);
            $car = $vehicleId ? Vehicle::findOrFail($vehicleId) : null;
            if ($car && $car->archived_at) {
                throw new Conflict('archived');
            }
            $before = $r->exists ? $r->toArray() : null;
            $buffer = $r->status === 'confirmed' ? (int) $r->preparation_minutes : (int) $this->settings()->preparation_minutes;
            $issues = [];
            $override = null;
            if ($v['status'] === 'confirmed') {
                if ($car->entered_service_at && $car->entered_service_at->format('Y-m-d') > $start->setTimezone('Africa/Algiers')->format('Y-m-d')) {
                    throw new Conflict('before_service');
                }
                $conflicts = $this->conflicts($car->id, $start, $end->addMinutes($buffer), $id);
                if ($conflicts->isNotEmpty()) {
                    throw new Conflict('overlap', ['conflicts' => $conflicts->toArray()]);
                }
                $issues = $this->documentIssues($car, $end);
                if ($issues) {
                    // Only a previous override for this exact vehicle and period can be reused.
                    $same = $r->exists && $r->vehicle_id === $car->id && $r->starts_at->equalTo($start) && $r->ends_at->equalTo($end) && $r->document_issues === $issues && $r->document_override_reason;
                    if ($same) {
                        $override = $r->document_override_reason;
                    } else {
                        if (empty(trim($v['document_override_reason'] ?? ''))) {
                            throw new Conflict('documents', ['document_issues' => $issues]);
                        }
                        $this->authorize($u, 'vehicle-documents.override');
                        $override = trim($v['document_override_reason']);
                    }
                }
            }
            $r->fill(['customer_id' => $v['customer_id'], 'category_id' => ! empty($v['category_id']) ? (int) $v['category_id'] : null, 'vehicle_id' => $vehicleId, 'status' => $v['status'], 'starts_at' => $start, 'ends_at' => $end, 'preparation_minutes' => $v['status'] === 'confirmed' ? $buffer : 0, 'notes' => $v['notes'] ?? null, 'document_override_reason' => $override, 'document_issues' => $issues, 'version' => $id ? $r->version + 1 : 1]);
            if (! $id) {
                $r->created_by = $u->id;
            } $r->save();
            if ($r->status === 'confirmed') {
                VehicleCommitment::updateOrCreate(['reservation_id' => $r->id], ['vehicle_id' => $car->id, 'kind' => 'reservation', 'starts_at' => $start, 'ends_at' => $end->addMinutes($buffer), 'created_by' => $r->created_by]);
            }
            app(Foundation::class)->audit($u, $id ? 'reservation.updated' : 'reservation.created', 'reservation', $r->id, $before, $r->toArray(), $v['reason'] ?? null);
            if ($override && (! isset($same) || ! $same)) {
                app(Foundation::class)->audit($u, 'reservation.document_override', 'reservation', $r->id, null, ['vehicle_id' => $vehicleId, 'starts_at' => $start->toIso8601String(), 'ends_at' => $end->toIso8601String(), 'issues' => $issues], $override);
            }

            return $r->load(['customer:id,name', 'vehicle:id,registration,make,model', 'category:id,name']);
        });
    }

    public function cancel(User $u, int $id, array $input): Reservation
    {
        $this->authorize($u, 'reservations.cancel');
        $v = Validator::make(Input::normalize($input), ['version' => 'required|integer|min:1', 'reason' => 'required|string|max:500'])->validate();

        return DB::transaction(function () use ($u, $id, $v) {
            $this->lock();
            $r = Reservation::lockForUpdate()->findOrFail($id);
            $this->lock([$r->vehicle_id]);
            if ($r->version !== (int) $v['version']) {
                throw new Conflict('stale');
            }
            if (! in_array($r->status, ['tentative', 'confirmed'])) {
                throw new Conflict('closed');
            }
            $before = $r->toArray();
            $r->update(['status' => 'cancelled', 'cancelled_at' => now(), 'cancellation_reason' => $v['reason'], 'version' => $r->version + 1]);
            VehicleCommitment::where('reservation_id', $id)->update(['released_at' => now(), 'release_reason' => $v['reason']]);
            app(Foundation::class)->audit($u, 'reservation.cancelled', 'reservation', $id, $before, $r->toArray(), $v['reason']);

            return $r;
        });
    }

    public function block(User $u, array $input): VehicleCommitment
    {
        $this->authorize($u, 'blocks.manage');
        [$start,$end] = $this->interval($input, true);
        $v = Validator::make(Input::normalize($input), ['vehicle_id' => 'required|integer|exists:vehicles,id', 'kind' => ['required', Rule::in(self::BLOCKS)], 'reason' => 'required|string|max:500', 'emergency' => 'sometimes|boolean'])->validate();
        if (in_array($v['kind'], ['maintenance', 'administrative']) || ($v['emergency'] ?? false)) {
            $this->authorize($u, 'fleet.manage');
        }

        return DB::transaction(function () use ($u, $v, $start, $end) {
            $this->lock([$v['vehicle_id']]);
            $car = Vehicle::findOrFail($v['vehicle_id']);
            if ($car->archived_at) {
                throw new Conflict('archived');
            }
            $conflicts = $this->conflicts($car->id, $start, $end);
            if ($conflicts->isNotEmpty() && ! ($v['emergency'] ?? false)) {
                throw new Conflict('overlap', ['conflicts' => $conflicts->toArray()]);
            }
            $b = VehicleCommitment::create($v + ['starts_at' => $start, 'ends_at' => $end, 'created_by' => $u->id]);
            app(Foundation::class)->audit($u, 'block.created', 'block', $b->id, null, $b->toArray(), $v['reason']);

            return $b;
        });
    }

    public function release(User $u, int $id, array $input): VehicleCommitment
    {
        $this->authorize($u, 'blocks.manage');
        $v = Validator::make(Input::normalize($input), ['version' => 'required|integer|min:1', 'reason' => 'required|string|max:500'])->validate();

        return DB::transaction(function () use ($u, $id, $v) {
            $this->lock();
            $b = VehicleCommitment::lockForUpdate()->findOrFail($id);
            $this->lock([$b->vehicle_id]);
            if (in_array($b->kind, ['reservation', 'rental'])) {
                throw new Conflict('closed');
            }
            if (in_array($b->kind, ['maintenance', 'administrative']) || $b->emergency) {
                $this->authorize($u, 'fleet.manage');
            }
            if ($b->released_at || $b->version !== (int) $v['version']) {
                throw new Conflict('stale');
            }
            $before = $b->toArray();
            $b->update(['released_at' => now(), 'release_reason' => $v['reason'], 'version' => $b->version + 1]);
            app(Foundation::class)->audit($u, 'block.released', 'block', $id, $before, $b->toArray(), $v['reason']);

            return $b;
        });
    }

    public function blocks(User $u)
    {
        $this->authorize($u);

        return VehicleCommitment::with('vehicle:id,registration')->whereNotIn('kind', ['reservation', 'rental'])->orderByDesc('id');
    }

    public function affected(Reservation $r)
    {
        return $r->status === 'confirmed' ? $this->conflicts($r->vehicle_id, $r->starts_at, $r->ends_at->addMinutes($r->preparation_minutes), $r->id) : collect();
    }

    public function pickupStatus(Reservation $r): ?string
    {
        if ($r->status !== 'confirmed' || $r->starts_at->isFuture()) {
            return null;
        }

        return $r->starts_at->addMinutes((int) $this->settings()->no_show_minutes)->isPast() ? 'no_show' : 'pickup_overdue';
    }
}
