<?php

namespace App\Livewire;

use App\Models\Customer;
use App\Models\Inspection;
use App\Modules\Rentals\Actions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;

class RentalProfile extends Component
{
    use RentalForms,WithFileUploads;

    #[Locked]
    public int $id;

    #[Locked]
    public string $key;

    #[Locked]
    public string $photoKey;

    public array $form = [];

    public array $extension = [];

    public string $reason = '';

    public $photo;

    public string $inspectionId = '';

    public function mount(int $id): void
    {
        $this->id = $id;
        $this->loadRecord();
    }

    public function loadRecord(): void
    {
        $r = app(Actions::class)->find(auth()->user(), $this->id);
        $this->key = (string) Str::uuid();
        $this->photoKey = (string) Str::uuid();
        $this->form = ['driver_id' => $r->driver_id, 'version' => $r->version, 'occurred_at' => now('Africa/Algiers')->format('Y-m-d\TH:i'), 'mileage_km' => $r->vehicle->mileage_km, 'fuel_percent' => '', 'condition_confirmed' => false, 'condition_notes' => '', 'correction_reason' => '', 'document_override_reason' => ''];
        $this->extension = ['version' => $r->version, 'ends_at' => $r->ends_at->setTimezone('Africa/Algiers')->addDay()->format('Y-m-d\TH:i'), 'basis' => $r->pricing['basis'], 'discount_type' => $r->pricing['discount_type'], 'discount_value' => $r->pricing['discount_value'], 'negotiated_total' => '', 'document_override_reason' => '', 'reason' => ''];
    }

    public function inspect(Actions $a, string $kind): void
    {
        $this->attempt(function () use ($a, $kind) {
            $a->inspect(auth()->user(), $this->id, $kind, $this->localDates($this->form) + ['idempotency_key' => $this->key]);
            $this->loadRecord();
            session()->flash('success', __('ui.saved'));
        });
    }

    public function extendRental(Actions $a): void
    {
        $this->attempt(function () use ($a) {
            $a->extend(auth()->user(), $this->id, $this->localDates($this->extension) + ['idempotency_key' => $this->key]);
            $this->loadRecord();
            $this->dispatch('ledger-changed');
            session()->flash('success', __('ui.saved'));
        }, 'extension');
    }

    public function cancelRental(Actions $a): void
    {
        $this->attempt(function () use ($a) {
            $a->cancel(auth()->user(), $this->id, ['version' => $this->form['version'], 'reason' => $this->reason, 'idempotency_key' => $this->key]);
            $this->loadRecord();
            $this->dispatch('ledger-changed');
            session()->flash('success', __('rental.cancel_hint'));
        }, '');
    }

    public function updatedPhoto(): void
    {
        $this->photoKey = (string) Str::uuid();
    }

    public function addPhoto(Actions $a): void
    {
        $this->attempt(function () use ($a) {
            $this->validate(['inspectionId' => 'required|integer']);
            Inspection::where('rental_id', $this->id)->findOrFail($this->inspectionId);
            $a->photo(auth()->user(), (int) $this->inspectionId, ['file' => $this->photo, 'idempotency_key' => $this->photoKey]);
            $this->photo = null;
            $this->photoKey = (string) Str::uuid();
            session()->flash('success', __('ui.saved'));
        }, '');
    }

    public function render()
    {
        return view('livewire.rental-profile', ['record' => app(Actions::class)->find(auth()->user(), $this->id), 'drivers' => Customer::where('type', 'individual')->whereNull('archived_at')->orderBy('name')->get(), 'versions' => DB::table('rental_versions')->where('rental_id', $this->id)->orderByDesc('number')->get()])->layout('components.layouts.app');
    }
}
