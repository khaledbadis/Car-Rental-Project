<?php

namespace App\Modules\Reporting;

use App\Models\Expense;
use App\Models\Rental;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleCommitment;
use App\Support\Input;
use Carbon\CarbonImmutable as Date;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class Report
{
    public function filters(array $input): array
    {
        $v = Validator::make(Input::normalize($input), ['from' => 'required|date_format:Y-m-d', 'to' => 'required|date_format:Y-m-d|after_or_equal:from', 'vehicle_id' => 'nullable|integer|exists:vehicles,id'])->validate();
        if (Date::parse($v['from'])->diffInDays(Date::parse($v['to'])) > 365) {
            throw ValidationException::withMessages(['to' => __('release.range_limit')]);
        }

        return $v;
    }

    public static function merge(array $intervals): array
    {
        usort($intervals, fn ($a, $b) => $a[0] <=> $b[0]);
        $out = [];
        foreach ($intervals as [$s,$e]) {
            if ($e <= $s) {
                continue;
            }$last = count($out) - 1;
            if ($last >= 0 && $s <= $out[$last][1]) {
                $out[$last][1] = max($e, $out[$last][1]);
            } else {
                $out[] = [$s, $e];
            }
        }

        return $out;
    }

    private static function seconds(array $intervals): int
    {
        return array_sum(array_map(fn ($i) => $i[1] - $i[0], $intervals));
    }

    public function read(User $u, array $input): array
    {
        Gate::forUser($u)->authorize('finance.manage');
        $v = $this->filters($input);
        $now = Date::now();
        $start = Date::parse($v['from'], 'Africa/Algiers')->timestamp;
        $end = min(Date::parse($v['to'], 'Africa/Algiers')->addDay()->timestamp, $now->timestamp);
        $cars = Vehicle::when($v['vehicle_id'] ?? null, fn ($q, $id) => $q->whereKey($id))->orderBy('registration')->get();
        $rentals = Rental::whereIn('status', ['active', 'returned'])->get()->groupBy('vehicle_id');
        $blocks = VehicleCommitment::whereIn('kind', ['maintenance', 'administrative'])->get()->groupBy('vehicle_id');
        $rows = [];
        foreach ($cars as $car) {
            $from = $car->entered_service_at ? max($start, Date::parse($car->entered_service_at->toDateString(), 'Africa/Algiers')->timestamp) : $start;
            $until = $car->archived_at ? min($end, $car->archived_at->timestamp) : $end;
            $excluded = [];
            foreach ($blocks->get($car->id, collect()) as $b) {
                $stop = min($b->ends_at?->timestamp ?? $until, $b->released_at?->timestamp ?? $until, $until);
                $excluded[] = [max($from, $b->starts_at->timestamp), $stop];
            }$excluded = self::merge($excluded);
            $rented = [];
            $count = 0;
            $missing = false;
            $outsideService = false;
            foreach ($rentals->get($car->id, collect()) as $r) {
                $day = $r->starts_at->setTimezone('Africa/Algiers')->toDateString();
                if ($day >= $v['from'] && $day <= $v['to']) {
                    $count++;
                }if (! $r->handed_over_at) {
                    if ($r->starts_at->timestamp < $end && ($r->returned_at?->timestamp ?? $now->timestamp) > $start) {
                        $missing = true;
                    }

                    continue;
                }
                $actualStart = max($start, $r->handed_over_at->timestamp);
                $actualEnd = min($end, $r->returned_at?->timestamp ?? $now->timestamp);
                if ($actualEnd > $actualStart && ($actualStart < $from || $actualEnd > $until)) {
                    $outsideService = true;
                }
                $rented[] = [$actualStart, $actualEnd];
            }
            $valid = array_filter($rented, fn ($i) => $i[1] > $i[0]);
            $merged = self::merge($valid);
            $overlap = $outsideService || self::seconds($valid) !== self::seconds($merged);
            foreach ($merged as [$s,$e]) {
                foreach ($excluded as [$bs,$be]) {
                    if (max($s, $bs) < min($e, $be)) {
                        $overlap = true;
                    }
                }
            }
            $eligible = max(0, $until - $from) - self::seconds($excluded);
            $seconds = self::seconds($merged);
            $status = ! $car->entered_service_at ? 'missing_service_date' : ($missing ? 'missing_handover' : ($overlap ? 'overlap' : ($eligible === 0 ? 'no_eligible_hours' : 'ok')));
            $rows[$car->id] = ['vehicle_id' => $car->id, 'registration' => $car->registration, 'rental_count' => $count, 'rented_hours' => round($seconds / 3600, 2), 'eligible_hours' => $car->entered_service_at ? round($eligible / 3600, 2) : null, 'utilization' => $status === 'ok' ? round(100 * $seconds / $eligible, 2) : null, 'utilization_status' => $status, 'revenue' => 0, 'collections' => 0, 'expenses' => 0, 'maintenance_cost' => 0, 'contribution' => 0];
        }
        $unallocated = ['revenue' => 0, 'collections' => 0];
        $cancellation = 0;
        $daily = [];
        for ($d = Date::parse($v['from']); $d->toDateString() <= $v['to']; $d = $d->addDay()) {
            $daily[$d->toDateString()] = ['date' => $d->toDateString(), 'revenue' => 0, 'collections' => 0, 'expenses' => 0];
        }
        foreach (app(Recognition::class)->entries() as $e) {
            if (($v['vehicle_id'] ?? null) && $e['vehicle_id'] != (int) $v['vehicle_id']) {
                continue;
            }
            foreach (['revenue', 'collections'] as $metric) {
                $day = $e[$metric.'_date'];
                if (! $day || ! isset($daily[$day])) {
                    continue;
                }$amount = $e[$metric];
                $daily[$day][$metric] += $amount;
                if (isset($rows[$e['vehicle_id']])) {
                    $rows[$e['vehicle_id']][$metric] += $amount;
                } else {
                    $unallocated[$metric] += $amount;
                }if ($metric === 'revenue' && $e['standalone_cancellation']) {
                    $cancellation += $amount;
                }
            }
        }
        $categories = array_fill_keys(Expenses::CATEGORIES, 0);
        foreach (Expense::whereBetween('incurred_on', [$v['from'], $v['to']])->when($v['vehicle_id'] ?? null, fn ($q, $id) => $q->where('vehicle_id', $id))->get() as $e) {
            $rows[$e->vehicle_id]['expenses'] += $e->amount_cents;
            if ($e->category === 'maintenance') {
                $rows[$e->vehicle_id]['maintenance_cost'] += $e->amount_cents;
            }$categories[$e->category] += $e->amount_cents;
            $daily[$e->incurred_on->toDateString()]['expenses'] += $e->amount_cents;
        }
        foreach ($rows as &$row) {
            $row['contribution'] = $row['revenue'] - $row['expenses'];
        }unset($row);
        $totals = ['revenue' => array_sum(array_column($daily, 'revenue')), 'collections' => array_sum(array_column($daily, 'collections')), 'expenses' => array_sum($categories), 'rental_count' => array_sum(array_column($rows, 'rental_count')), 'standalone_cancellation' => $cancellation];
        $totals['contribution'] = $totals['revenue'] - $totals['expenses'];

        return ['from' => $v['from'], 'to' => $v['to'], 'vehicle_id' => $v['vehicle_id'] ?? null, 'as_of' => $now->toIso8601String(), 'totals' => $totals, 'vehicles' => array_values($rows), 'unallocated' => $unallocated, 'categories' => $categories, 'daily' => array_values($daily)];
    }
}
