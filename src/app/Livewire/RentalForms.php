<?php

namespace App\Livewire;

use App\Modules\Rentals\Conflict;
use App\Modules\Reservations\Conflict as ScheduleConflict;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

trait RentalForms
{
    public string $failure = '';

    protected function localDates(array $input): array
    {
        foreach (['starts_at', 'ends_at', 'occurred_at', 'effective_at'] as $f) {
            if (! empty($input[$f])) {
                Validator::make([$f => $input[$f]], [$f => 'date_format:Y-m-d\TH:i'])->validate();
                $input[$f] = CarbonImmutable::createFromFormat('!Y-m-d\TH:i', $input[$f], 'Africa/Algiers')->toIso8601String();
            }
        }

        return $input;
    }

    protected function attempt(callable $work, string $prefix = 'form'): void
    {
        $this->failure = '';
        $this->resetErrorBag();
        try {
            $work();
        } catch (ValidationException $e) {
            foreach ($e->errors() as $f => $messages) {
                $this->addError($prefix ? $prefix.'.'.$f : $f, $messages[0]);
            }
        } catch (Conflict|ScheduleConflict $e) {
            $this->failure = $e->getMessage();
            if (isset($e->details['missing'])) {
                $this->failure .= ' '.implode(', ', array_map(fn ($f) => __('rental.fields.'.$f), $e->details['missing']));
            }
        }
    }
}
