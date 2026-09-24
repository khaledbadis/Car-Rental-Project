<?php

namespace App\Livewire;

use App\Models\Vehicle;
use App\Models\VehicleCategory;
use App\Modules\Reservations\Actions;
use App\Modules\Reservations\Conflict;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithPagination;

class Availability extends Component
{
    use WithPagination;

    public array $search = [];

    public array $results = [];

    public bool $searched = false;

    public array $blockForm = ['vehicle_id' => '', 'kind' => 'cleaning', 'starts_at' => '', 'ends_at' => '', 'reason' => '', 'emergency' => false];

    public string $releaseReason = '';

    public string $failure = '';

    public function mount(): void
    {
        app(Actions::class)->authorize(auth()->user());
        $this->search = ['starts_at' => now('Africa/Algiers')->format('Y-m-d\TH:i'), 'ends_at' => now('Africa/Algiers')->addDay()->format('Y-m-d\TH:i'), 'category_id' => '', 'transmission' => ''];
    }

    private function errors(ValidationException $e, string $prefix): void
    {
        foreach ($e->errors() as $k => $v) {
            $this->addError($prefix.'.'.$k, $v[0]);
        }
    }

    public function searchVehicles(Actions $a): void
    {
        $this->resetErrorBag();
        try {
            $this->results = $a->availability(auth()->user(), ReservationEditor::dates($this->search));
            $this->searched = true;
        } catch (ValidationException $e) {
            $this->errors($e, 'search');
        }
    }

    public function createBlock(Actions $a): void
    {
        $this->resetErrorBag();
        $this->failure = '';
        try {
            $a->block(auth()->user(), ReservationEditor::dates($this->blockForm));
            $this->reset('blockForm');
            $this->searched = false;
            $this->results = [];
            session()->flash('success', __('ui.saved'));
        } catch (Conflict $e) {
            $this->failure = $e->getMessage();
        } catch (ValidationException $e) {
            $this->errors($e, 'blockForm');
        }
    }

    public function release(Actions $a, int $id, int $version): void
    {
        $this->failure = '';
        $this->validate(['releaseReason' => 'required|string|max:500']);
        try {
            $a->release(auth()->user(), $id, ['reason' => $this->releaseReason, 'version' => $version]);
            $this->releaseReason = '';
            $this->searched = false;
            $this->results = [];
            session()->flash('success', __('ui.saved'));
        } catch (Conflict $e) {
            $this->failure = $e->getMessage();
        }
    }

    public function render()
    {
        $a = app(Actions::class);
        $a->authorize(auth()->user());

        return view('livewire.availability', ['vehicles' => Vehicle::whereNull('archived_at')->orderBy('registration')->get(), 'categories' => VehicleCategory::orderBy('name')->get(), 'blocks' => $a->blocks(auth()->user())->paginate(15)])->layout('components.layouts.app');
    }
}
