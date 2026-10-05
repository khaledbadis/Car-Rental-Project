<?php

namespace App\Livewire;

use App\Models\Vehicle;
use App\Modules\Reporting\Expenses as Actions;
use App\Modules\Reservations\Conflict;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

class Expenses extends Component
{
    use WithFileUploads,WithPagination;

    public array $form = [];

    public string $vehicle_id = '';

    public string $reason = '';

    public string $failure = '';

    public $file;

    public $attachmentExpense = '';

    #[Locked]
    public string $key;

    #[Locked]
    public array $reverseKeys = [];

    public function mount(): void
    {
        $this->freshForm();
    }

    private function freshForm(): void
    {
        $this->form = ['vehicle_id' => '', 'category' => 'other', 'amount' => '', 'incurred_on' => now('Africa/Algiers')->toDateString(), 'description' => ''];
        $this->key = (string) Str::uuid();
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
            $a->post(auth()->user(), $this->form + ['idempotency_key' => $this->key]);
            $this->freshForm();
            session()->flash('success', __('ui.saved'));
        } catch (ValidationException $e) {
            foreach ($e->errors() as $k => $v) {
                $this->addError('form.'.$k, $v[0]);
            }
        } catch (Conflict $e) {
            $this->failure = $e->getMessage();
        }
    }

    public function reverse(Actions $a, int $id): void
    {
        $this->validate(['reason' => 'required|string|max:500']);
        $this->failure = '';
        $this->reverseKeys[$id] ??= (string) Str::uuid();
        try {
            $a->reverse(auth()->user(), $id, ['reason' => $this->reason, 'idempotency_key' => $this->reverseKeys[$id]]);
            session()->flash('success', __('ui.saved'));
        } catch (Conflict $e) {
            $this->failure = $e->getMessage();
        }
    }

    public function upload(Actions $a): void
    {
        $this->validate(['attachmentExpense' => 'required|integer']);
        $a->upload(auth()->user(), (int) $this->attachmentExpense, ['file' => $this->file]);
        $this->reset(['file', 'attachmentExpense']);
        session()->flash('success', __('ui.saved'));
    }

    public function render()
    {
        return view('livewire.expenses', ['records' => app(Actions::class)->query(auth()->user(), ['vehicle_id' => $this->vehicle_id])->paginate(15), 'vehicles' => Vehicle::orderBy('registration')->get(['id', 'registration'])])->layout('components.layouts.app');
    }
}
