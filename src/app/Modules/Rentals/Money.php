<?php

namespace App\Modules\Rentals;

use Illuminate\Validation\ValidationException;

final class Money
{
    public static function cents(mixed $value, string $field = 'amount'): int
    {
        if (! is_scalar($value) || ! preg_match('/^\d{1,10}(?:\.\d{1,2})?$/', (string) $value)) {
            throw ValidationException::withMessages([$field => __('rental.invalid_money')]);
        }
        [$whole,$fraction] = array_pad(explode('.', (string) $value), 2, '');

        return (int) $whole * 100 + (int) str_pad($fraction, 2, '0');
    }

    public static function decimal(int $cents): string
    {
        return ($cents < 0 ? '-' : '').intdiv(abs($cents), 100).'.'.str_pad((string) (abs($cents) % 100), 2, '0', STR_PAD_LEFT);
    }

    public static function display(int $cents): string
    {
        return number_format(intdiv(abs($cents), 100), 0, ',', ' ').','.str_pad((string) (abs($cents) % 100), 2, '0', STR_PAD_LEFT).' DA';
    }
}
