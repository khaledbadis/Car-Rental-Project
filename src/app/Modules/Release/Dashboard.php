<?php

namespace App\Modules\Release;

use App\Models\FinancialAccount;
use App\Models\Rental;
use App\Models\Reservation;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleCommitment;
use App\Modules\Maintenance\Actions;
use App\Modules\Rentals\Ledger;
use App\Modules\Reporting\Recognition;
use App\Modules\Reservations\Actions as Schedule;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

class Dashboard
{
    public function read(User $u, array $input): array
    {
        Gate::forUser($u)->authorize('dashboard.view');
        $v = Validator::make($input, ['from' => 'required|date_format:Y-m-d', 'to' => 'required|date_format:Y-m-d|after_or_equal:from'])->validate();
        $from = CarbonImmutable::parse($v['from'], 'Africa/Algiers')->startOfDay();
        $to = CarbonImmutable::parse($v['to'], 'Africa/Algiers')->endOfDay();
        abort_if($from->diffInDays($to) > 366, 422, __('release.range_limit'));
        $now = CarbonImmutable::now('Africa/Algiers');
        $cars = Vehicle::whereNull('archived_at')->get();
        $rentals = Rental::with(['vehicle:id,registration', 'customer:id,name'])->whereIn('status', ['draft', 'active'])->get();
        $bookings = Reservation::with(['vehicle:id,registration', 'customer:id,name'])->where('status', 'confirmed')->get();
        $blocks = VehicleCommitment::whereNull('released_at')->whereNull('reservation_id')->whereNull('rental_id')->where('starts_at', '<=', $now)->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', $now))->get();
        $fleet = ['rented' => 0, 'maintenance' => 0, 'blocked' => 0, 'reserved' => 0, 'available' => 0];
        $urgent = [];
        $schedule = app(Schedule::class);
        foreach ($cars as $car) {
            if ($rentals->where('vehicle_id', $car->id)->where('status', 'active')->isNotEmpty()) {
                $state = 'rented';
            } elseif ($blocks->where('vehicle_id', $car->id)->where('kind', 'maintenance')->isNotEmpty()) {
                $state = 'maintenance';
            } elseif ($blocks->where('vehicle_id', $car->id)->isNotEmpty()) {
                $state = 'blocked';
            } elseif ($schedule->conflicts($car->id, $now, $now->addSecond())->isNotEmpty()) {
                $state = 'reserved';
            } else {
                $state = 'available';
            }
            $fleet[$state]++;
            foreach ($schedule->documentIssues($car, $now->addDays(30)) as $issue) {
                $urgent[] = ['kind' => 'documents', 'label' => $car->registration, 'url' => route('vehicles.show', $car->id)];
                break;
            }
        }
        foreach ($rentals as $r) {
            if ($r->status === 'draft') {
                $conflicts = $schedule->conflicts($r->vehicle_id, $r->starts_at, $r->ends_at->addMinutes($r->preparation_minutes), null, $r->id);
                if ($conflicts->isNotEmpty() || $r->starts_at->lt($now)) {
                    $urgent[] = ['kind' => $conflicts->isNotEmpty() ? 'conflict' : 'pickup_late', 'label' => $r->vehicle->registration.' · '.$r->customer->name, 'url' => route('rentals.show', $r->id)];
                }
            }
            if ($r->status === 'active' && $r->ends_at->lt($now)) {
                $urgent[] = ['kind' => 'overdue', 'label' => $r->vehicle->registration.' · '.$r->customer->name, 'url' => route('rentals.show', $r->id)];
            }
        }
        foreach ($bookings as $r) {
            if ($schedule->affected($r)->isNotEmpty()) {
                $urgent[] = ['kind' => 'conflict', 'label' => $r->vehicle?->registration.' · '.$r->customer->name, 'url' => route('reservations.show', $r->id)];
            } elseif ($r->starts_at->lt($now)) {
                $urgent[] = ['kind' => 'pickup_late', 'label' => $r->vehicle?->registration.' · '.$r->customer->name, 'url' => route('reservations.show', $r->id)];
            }
        }
        if ($u->permits('maintenance.view')) {
            foreach (app(Actions::class)->reminders($u) as $reminder) {
                $urgent[] = ['kind' => 'maintenance', 'label' => $reminder['registration'].' · '.__('maintenance.'.$reminder['service_type']).' · '.__('maintenance.'.$reminder['status']), 'url' => route('maintenance', ['vehicle_id' => $reminder['vehicle_id']])];
            }
        }
        $priorities = ['maintenance' => 3, 'conflict' => 0, 'overdue' => 1, 'pickup_late' => 2, 'documents' => 3];
        usort($urgent, fn ($a, $b) => $priorities[$a['kind']] <=> $priorities[$b['kind']]);
        $activity = [];
        foreach ($bookings as $b) {
            if ($b->starts_at->betweenIncluded($from, $to)) {
                $activity[] = ['kind' => 'pickup', 'time' => $b->starts_at->toIso8601String(), 'label' => $b->vehicle?->registration.' · '.$b->customer->name, 'url' => route('reservations.show', $b->id)];
            }
        }
        foreach ($rentals as $r) {
            $at = $r->status === 'draft' ? $r->starts_at : $r->ends_at;
            if ($at->betweenIncluded($from, $to)) {
                $activity[] = ['kind' => $r->status === 'draft' ? 'pickup' : 'return', 'time' => $at->toIso8601String(), 'label' => $r->vehicle->registration.' · '.$r->customer->name, 'url' => route('rentals.show', $r->id)];
            }
        }
        usort($activity, fn ($a, $b) => strcmp($a['time'], $b['time']));
        $todayPickups = $bookings->filter(fn ($b) => $b->starts_at->setTimezone('Africa/Algiers')->isSameDay($now))->count() + $rentals->filter(fn ($r) => $r->status === 'draft' && $r->starts_at->setTimezone('Africa/Algiers')->isSameDay($now))->count();
        $todayReturns = $rentals->filter(fn ($r) => $r->status === 'active' && $r->ends_at->setTimezone('Africa/Algiers')->isSameDay($now))->count();
        $money = null;
        if ($u->permits('finance.manage')) {
            $debt = 0;
            $held = 0;
            $credit = 0;
            $balances = [];
            foreach (FinancialAccount::with('customer:id,name')->get() as $account) {
                $totals = app(Ledger::class)->totals($account);
                $debt += $totals['outstanding_cents'];
                $held += $totals['held_cents'];
                $credit += $totals['credit_cents'];
                if ($totals['outstanding_cents'] || $totals['held_cents'] || $totals['credit_cents']) {
                    $balances[] = ['customer' => $account->customer->name, 'url' => $account->rental_id ? route('rentals.show', $account->rental_id) : ($account->reservation_id ? route('reservations.show', $account->reservation_id) : route('customers.show', $account->customer_id)), 'outstanding' => $totals['outstanding_cents'], 'held' => $totals['held_cents'], 'credit' => $totals['credit_cents']];
                }
            }
            $series = [];
            for ($day = $from; $day->lte($to); $day = $day->addDay()) {
                $series[$day->toDateString()] = ['date' => $day->toDateString(), 'revenue' => 0, 'collections' => 0];
            }
            foreach (app(Recognition::class)->entries() as $entry) {
                foreach (['revenue', 'collections'] as $metric) {
                    $day = $entry[$metric.'_date'];
                    if ($day && isset($series[$day])) {
                        $series[$day][$metric] += $entry[$metric];
                    }
                }
            }
            $money = ['accounts' => $balances, 'outstanding' => $debt, 'held' => $held, 'credit' => $credit, 'series' => array_values($series), 'revenue' => array_sum(array_column($series, 'revenue')), 'collections' => array_sum(array_column($series, 'collections'))];
        }

        return ['fleet' => $fleet, 'total' => $cars->count(), 'today_pickups' => $todayPickups, 'today_returns' => $todayReturns, 'urgent' => $urgent, 'activity' => $activity, 'money' => $money, 'from' => $v['from'], 'to' => $v['to'], 'as_of' => $now->toIso8601String()];
    }
}
