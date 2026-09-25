<?php

namespace App\Modules\Rentals;

use App\Models\User;
use App\Models\Vehicle;
use App\Support\Input;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class Pricing
{
    public function quote(User $u, Vehicle $car, CarbonImmutable $start, CarbonImmutable $end, array $input, ?array $original = null): array
    {
        $v = Validator::make(Input::normalize($input), ['basis' => 'required|in:daily,weekly,monthly', 'discount_type' => 'required|in:none,percent,fixed', 'discount_value' => 'nullable', 'negotiated_total' => 'nullable', 'pricing_reason' => 'nullable|string|max:500', 'reason' => 'nullable|string|max:500'])->validate();
        $seconds = $end->getTimestamp() - $start->getTimestamp();
        if ($seconds <= 0) {
            throw new Conflict('dates');
        }
        $unit = match ($v['basis']) {
            'daily' => 86400,'weekly' => 604800,'monthly' => 2592000
        };
        $rate = $original ? ($original['rates'][$v['basis']] ?? null) : $car->{$v['basis'].'_rate'};
        $rates = $original['rates'] ?? ['daily' => $car->daily_rate, 'weekly' => $car->weekly_rate, 'monthly' => $car->monthly_rate];
        $custom = $v['negotiated_total'] ?? null;
        if ($custom !== null) {
            Gate::forUser($u)->authorize('pricing.override');
        }
        if (($rate === null || ($v['basis'] !== 'daily' && $seconds % $unit !== 0)) && $custom === null) {
            throw new Conflict('whole_period');
        }
        $quantity = max(1, (int) ceil($seconds / $unit));
        $standard = $rate === null ? 0 : Money::cents($rate) * $quantity;
        $base = $custom === null ? $standard : Money::cents($custom, 'negotiated_total');
        if ($base > 999999999999) {
            throw new Conflict('amount_limit');
        }
        $value = Money::cents($v['discount_value'] ?? '0', 'discount_value');
        if ($v['discount_type'] === 'percent' && $value > 10000) {
            throw new Conflict('discount_limit');
        }
        $discount = match ($v['discount_type']) {
            'none' => 0,'percent' => intdiv($base * $value + 5000, 10000),'fixed' => $value
        };
        if ($discount > $base) {
            throw new Conflict('discount_limit');
        }
        if (! $u->permits('pricing.override') && (($v['discount_type'] === 'percent' && $value > 1000) || ($v['discount_type'] === 'fixed' && $discount * 10 > $standard))) {
            throw new Conflict('discount_limit');
        }

        $reason = $v['pricing_reason'] ?? $v['reason'] ?? null;
        if (($discount > 0 || $custom !== null) && ! $reason) {
            throw ValidationException::withMessages(['pricing_reason' => __('rental.price_reason_required')]);
        }

        return ['basis' => $v['basis'], 'quantity' => $quantity, 'rates' => $rates, 'rate' => $rate, 'standard_cents' => $standard, 'base_cents' => $base, 'discount_type' => $v['discount_type'], 'discount_value' => Money::decimal($value), 'discount_cents' => $discount, 'total_cents' => $base - $discount, 'negotiated_total' => $custom, 'rounding' => 'half_up', 'reason' => $reason];
    }
}
