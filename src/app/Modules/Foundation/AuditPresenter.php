<?php

namespace App\Modules\Foundation;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class AuditPresenter
{
    public function label(string $field): string
    {
        $name = Str::afterLast($field, '.');
        foreach (['validation.attributes.', 'ui.', 'release.', 'catalog.', 'rental.', 'booking.', 'maintenance.', 'report.'] as $prefix) {
            if (is_string(__($prefix.$name)) && __($prefix.$name) !== $prefix.$name) {
                return __($prefix.$name);
            }
        }

        return Str::headline($field);
    }

    public function changes(?string $before, ?string $after): array
    {
        $a = Arr::dot(json_decode($before ?? '[]', true) ?: []);
        $b = Arr::dot(json_decode($after ?? '[]', true) ?: []);
        $rows = [];
        foreach (array_unique(array_merge(array_keys($a), array_keys($b))) as $key) {
            if (preg_match('/password|token|secret|path|request_hash|idempotency/i', $key) || ($a[$key] ?? null) === ($b[$key] ?? null)) {
                continue;
            }
            if ((str_starts_with($key, 'changed_fields.') || str_starts_with($key, 'fields.'))) {
                $rows[] = ['field' => $this->label((string) ($b[$key] ?? $a[$key])), 'before' => '—', 'after' => __('shell.updated')];

                continue;
            }
            $rows[] = ['field' => $this->label($key), 'before' => $this->value($key, $a[$key] ?? null), 'after' => $this->value($key, $b[$key] ?? null)];
        }

        return $rows;
    }

    private function value(string $key, mixed $value): string
    {
        if ($value === null || $value === []) {
            return '—';
        }
        if (is_bool($value)) {
            return __('shell.'.($value ? 'yes' : 'no'));
        }
        if (str_contains($key, 'changed_fields')) {
            return $this->label((string) $value);
        }
        if (str_ends_with($key, '_cents') && is_numeric($value)) {
            return number_format($value / 100, 2, '.', ' ').' DZD';
        }
        if (is_array($value)) {
            return implode(', ', array_map(fn ($v) => is_scalar($v) ? (string) $v : '—', $value));
        }

        return (string) $value;
    }
}
