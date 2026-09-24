<?php

namespace App\Support;

final class Input
{
    /**
     * Livewire hydrates field values after HTTP string-cleaning middleware runs.
     * Normalize at the shared action boundary so web, API and internal callers
     * validate and persist blanks consistently. Never trim password contents.
     */
    public static function normalize(array $input): array
    {
        foreach ($input as $key => $value) {
            if (is_array($value)) {
                $input[$key] = self::normalize($value);
            } elseif (is_string($value)) {
                if (! in_array($key, ['password', 'password_confirmation', 'current_password'], true)) {
                    $value = trim($value);
                }
                $input[$key] = $value === '' ? null : $value;
            }
        }

        return $input;
    }
}
