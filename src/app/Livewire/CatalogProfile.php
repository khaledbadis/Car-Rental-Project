<?php

namespace App\Livewire;

use App\Models\Customer;
use App\Models\VehicleCategory;
use App\Modules\Catalog\Actions;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;

class CatalogProfile extends Component
{
    use WithFileUploads;

    #[Locked]
    public string $kind;

    #[Locked]
    public int $id;

    public array $form = [];

    public string $reason = '';

    public string $noteBody = '';

    public string $noteCategory = 'other';

    public $file;

    public string $documentType = '';

    public ?string $expiresAt = null;

    public string $removalReason = '';

    public function mount(string $kind, int $id): void
    {
        $this->kind = $kind;
        $this->id = $id;
        $this->loadRecord();
        $this->documentType = config('catalog.documents.'.$kind)[0];
    }

    public function loadRecord(): void
    {
        $r = app(Actions::class)->find(auth()->user(), $this->kind, $this->id);
        $this->form = $r->only(array_keys(config('catalog.'.$this->kind)));
        foreach (config('catalog.'.$this->kind) as $k => $type) {
            if ($type === 'date' && $r->$k) {
                $this->form[$k] = $r->$k->format('Y-m-d');
            }
        }$this->form['version'] = $r->version;
    }

    public function save(Actions $a): void
    {
        try {
            $a->save(auth()->user(), $this->kind, $this->form + ['reason' => $this->reason], $this->id);
            $this->loadRecord();
            $this->reason = '';
            session()->flash('success', __('ui.saved'));
        } catch (ValidationException $e) {
            foreach ($e->errors() as $k => $v) {
                $this->addError($k === 'reason' ? 'reason' : 'form.'.$k, $v[0]);
            }
        }
    }

    public function archive(Actions $a): void
    {
        $a->archive(auth()->user(), $this->kind, $this->id, ['reason' => $this->reason, 'version' => $this->form['version']]);
        $this->loadRecord();
        session()->flash('success', __('ui.saved'));
    }

    public function addNote(Actions $a): void
    {
        $this->validate(['noteBody' => 'required|string|max:4000']);
        $a->note(auth()->user(), $this->id, ['body' => $this->noteBody, 'category' => $this->noteCategory]);
        $this->noteBody = '';
    }

    public function saveDocument(Actions $a): void
    {
        try {
            $a->upload(auth()->user(), $this->kind, $this->id, ['file' => $this->file, 'type' => $this->documentType, 'expires_at' => $this->expiresAt]);
            $this->reset(['file', 'expiresAt']);
            $this->dispatch('documents-changed');
            session()->flash('success', __('ui.saved'));
        } catch (ValidationException $e) {
            foreach ($e->errors() as $k => $v) {
                $this->addError($k === 'expires_at' ? 'expiresAt' : ($k === 'type' ? 'documentType' : $k), $v[0]);
            }
        }
    }

    public function removeDocument(Actions $a, int $id): void
    {
        $this->validate(['removalReason' => 'required|string|max:500']);
        $record = $a->find(auth()->user(), $this->kind, $this->id);
        abort_unless($record->documents()->whereKey($id)->exists(), 404);
        $a->removeDocument(auth()->user(), $id, ['reason' => $this->removalReason]);
        $this->removalReason = '';
        $this->dispatch('documents-changed');
    }

    public function render(): mixed
    {
        $a = app(Actions::class);
        $record = $a->find(auth()->user(), $this->kind, $this->id);

        return view('livewire.catalog-profile', ['record' => $record, 'bookings' => auth()->user()->can('reservations.view') ? app(\App\Modules\Reservations\Actions::class)->query(auth()->user(), [$this->kind.'_id' => $this->id])->get() : collect(), 'blocks' => $this->kind === 'vehicle' && auth()->user()->can('reservations.view') ? app(\App\Modules\Reservations\Actions::class)->blocks(auth()->user())->where('vehicle_id', $this->id)->whereNull('released_at')->get() : collect(), 'categories' => VehicleCategory::orderBy('name')->get(), 'drivers' => $this->kind === 'customer' ? Customer::where('type', 'individual')->whereNull('archived_at')->orderBy('name')->get(['id', 'name']) : collect(), 'duplicates' => $this->kind === 'customer' ? $a->duplicates(auth()->user(), array_intersect_key($this->form, array_flip(['name', 'phone', 'identity_number', 'licence_number'])), $this->id) : collect()])->layout('components.layouts.app');
    }
}
