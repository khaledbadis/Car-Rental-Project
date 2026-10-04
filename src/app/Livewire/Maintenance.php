<?php

namespace App\Livewire;

use App\Models\Vehicle;
use App\Models\VehicleCommitment;
use App\Modules\Maintenance\Actions;
use App\Modules\Reservations\Conflict;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

class Maintenance extends Component
{
    use WithFileUploads, WithPagination;

    public array $form = [];

    #[Url]
    public string $vehicle_id = '';

    #[Locked]
    public string $requestKey;

    public string $failure = '';

    public string $releaseReason = '';

    public $attachmentRecord = '';

    public $file;

    public function mount(): void
    {
        $this->freshForm();
    }

    private function freshForm(): void
    {
        $this->form = ['vehicle_id' => $this->vehicle_id, 'vehicle_commitment_id' => '', 'service_type' => 'oil', 'serviced_on' => now('Africa/Algiers')->toDateString(), 'mileage_km' => '', 'cost' => '', 'notes' => '', 'next_due_on' => '', 'next_due_km' => ''];
        $this->requestKey = (string) Str::uuid();
    }

    public function updatedVehicleId(): void
    {
        $this->resetPage();
    }

    public function save(Actions $a): void
    {
        $this->resetErrorBag();
        $this->failure = '';
        try {
            $a->record(auth()->user(), $this->form + ['idempotency_key' => $this->requestKey]);
            $this->freshForm();
            session()->flash('success', __('ui.saved'));
        } catch (ValidationException $e) {
            foreach ($e->errors() as $k => $messages) {
                $this->addError('form.'.$k, $messages[0]);
            }
        } catch (Conflict $e) {
            $this->failure = $e->getMessage();
        }
    }

    public function upload(Actions $a): void
    {
        $this->validate(['attachmentRecord' => 'required|integer']);
        $a->upload(auth()->user(), (int) $this->attachmentRecord, ['file' => $this->file]);
        $this->reset(['file', 'attachmentRecord']);
        session()->flash('success', __('ui.saved'));
    }

    public function release(Actions $a, int $id, int $version): void
    {
        $this->validate(['releaseReason' => 'required|string|max:500']);
        $this->failure = '';
        try {
            $a->release(auth()->user(), $id, ['reason' => $this->releaseReason, 'version' => $version]);
            $this->releaseReason = '';
            session()->flash('success', __('ui.saved'));
        } catch (Conflict $e) {
            $this->failure = $e->getMessage();
        }
    }

    public function render()
    {
        $a = app(Actions::class);

        return view('livewire.maintenance', ['records' => $a->query(auth()->user(), ['vehicle_id' => $this->vehicle_id])->paginate(15), 'reminders' => $a->reminders(auth()->user()), 'vehicles' => Vehicle::orderBy('registration')->get(['id', 'registration']), 'blocks' => auth()->user()->can('maintenance.manage') ? VehicleCommitment::where('kind', 'maintenance')->with('vehicle:id,registration')->orderByDesc('id')->get() : collect()])->layout('components.layouts.app');
    }
}
