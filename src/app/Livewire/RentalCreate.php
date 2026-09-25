<?php

namespace App\Livewire;

use App\Models\Customer;
use App\Models\Vehicle;
use App\Modules\Rentals\Actions;
use App\Modules\Reservations\Actions as Reservations;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Component;

class RentalCreate extends Component
{
    use RentalForms;

    #[Locked]
    public ?int $reservationId = null;

    #[Locked]
    public string $key;

    public array $form = [];

    public function mount(?int $reservationId = null): void
    {
        Gate::authorize('rentals.manage');
        $this->reservationId = $reservationId;
        $this->key = (string) Str::uuid();
        $this->form = ['customer_id' => '', 'vehicle_id' => '', 'driver_id' => '', 'starts_at' => now('Africa/Algiers')->format('Y-m-d\TH:i'), 'ends_at' => now('Africa/Algiers')->addDay()->format('Y-m-d\TH:i'), 'basis' => 'daily', 'discount_type' => 'none', 'discount_value' => '0', 'negotiated_total' => '', 'mileage_allowance' => '', 'terms' => '', 'document_override_reason' => ''];
        if ($reservationId) {
            $r = app(Reservations::class)->find(auth()->user(), $reservationId);
            $this->form['reservation_version'] = $r->version;
        }
    }

    public function save(Actions $a): void
    {
        $this->attempt(function () use ($a) {
            $input = $this->localDates($this->form);
            if ($this->reservationId) {
                $input['reservation_id'] = $this->reservationId;
            }$r = $a->create(auth()->user(), $input + ['idempotency_key' => $this->key]);
            $this->redirectRoute('rentals.show', ['id' => $r->id]);
        });
    }

    public function render()
    {
        Gate::authorize('rentals.manage');

        return view('livewire.rental-create', ['customers' => Customer::whereNull('archived_at')->orderBy('name')->get(['id', 'name', 'type']), 'vehicles' => Vehicle::whereNull('archived_at')->orderBy('registration')->get(['id', 'registration', 'make', 'model']), 'drivers' => Customer::where('type', 'individual')->whereNull('archived_at')->orderBy('name')->get(['id', 'name'])]);
    }
}
