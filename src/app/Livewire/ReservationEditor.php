<?php

namespace App\Livewire;

use App\Models\Customer;
use App\Models\Vehicle;
use App\Models\VehicleCategory;
use App\Modules\Reservations\Actions;
use App\Modules\Reservations\Conflict;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;

class ReservationEditor extends Component
{
    #[Locked]
    public ?int $id = null;

    public array $form = [];

    public string $reason = '';

    public string $failure = '';

    public function mount(?int $id = null): void
    {
        $this->id = $id;
        $this->loadRecord();
    }

    public function loadRecord(): void
    {
        $a = app(Actions::class);
        $a->authorize(auth()->user());
        if ($this->id) {
            $r = $a->find(auth()->user(), $this->id);
            $this->form = $r->only(['customer_id', 'category_id', 'vehicle_id', 'status', 'notes', 'version']);
            foreach (['starts_at', 'ends_at'] as $f) {
                $this->form[$f] = $r->$f->setTimezone('Africa/Algiers')->format('Y-m-d\TH:i');
            }
        } else {
            $this->form = ['customer_id' => '', 'category_id' => '', 'vehicle_id' => '', 'status' => 'tentative', 'starts_at' => now('Africa/Algiers')->addDay()->format('Y-m-d\T10:00'), 'ends_at' => now('Africa/Algiers')->addDays(2)->format('Y-m-d\T10:00'), 'notes' => ''];
        }
        $this->form['document_override_reason'] = '';
    }

    public static function dates(array $input): array
    {
        foreach (['starts_at', 'ends_at'] as $f) {
            if (! empty($input[$f])) {
                Validator::make([$f => $input[$f]], [$f => 'date_format:Y-m-d\TH:i'])->validate();
                $date = CarbonImmutable::createFromFormat('!Y-m-d\TH:i', $input[$f], 'Africa/Algiers');
                $input[$f] = $date->toIso8601String();
            }
        }

        return $input;
    }

    public function save(Actions $a): void
    {
        $this->resetErrorBag();
        $this->failure = '';
        try {
            $r = $a->save(auth()->user(), self::dates($this->form) + ['reason' => $this->reason], $this->id);
            $this->redirectRoute('reservations.show', ['id' => $r->id]);
        } catch (Conflict $e) {
            $this->failure = $e->getMessage();
        } catch (ValidationException $e) {
            foreach ($e->errors() as $k => $v) {
                $this->addError($k === 'reason' ? 'reason' : 'form.'.$k, $v[0]);
            }
        }
    }

    public function cancel(Actions $a): void
    {
        $this->failure = '';
        $this->validate(['reason' => 'required|string|max:500']);
        try {
            $a->cancel(auth()->user(), $this->id, ['version' => $this->form['version'], 'reason' => $this->reason]);
            $this->loadRecord();
            session()->flash('success', __('booking.cancelled_notice'));
        } catch (Conflict $e) {
            $this->failure = $e->getMessage();
        }
    }

    public function render()
    {
        $a = app(Actions::class);
        $a->authorize(auth()->user());
        $record = $this->id ? $a->find(auth()->user(), $this->id) : null;

        return view('livewire.reservation-editor', ['record' => $record, 'customers' => Customer::orderBy('name')->get(['id', 'name', 'archived_at']), 'vehicles' => Vehicle::orderBy('registration')->get(['id', 'registration', 'make', 'model', 'archived_at']), 'categories' => VehicleCategory::orderBy('name')->get(), 'conflicts' => $record ? $a->affected($record) : collect(), 'pickup' => $record ? $a->pickupStatus($record) : null])->layout('components.layouts.app');
    }
}
