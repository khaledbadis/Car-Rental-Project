<?php

namespace App\Livewire;

use App\Modules\Rentals\Ledger;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

class LedgerPanel extends Component
{
    use RentalForms;

    #[Locked]
    public string $owner;

    #[Locked]
    public int $ownerId;

    #[Locked]
    public string $key;

    public array $form = [];

    public function mount(string $owner, int $ownerId): void
    {
        $this->owner = $owner;
        $this->ownerId = $ownerId;
        $this->clearForm();
    }

    public function clearForm(): void
    {
        $this->key = (string) Str::uuid();
        $this->form = ['kind' => 'payment', 'amount' => '', 'category' => 'other', 'method' => 'cash', 'reference' => '', 'effective_at' => now('Africa/Algiers')->format('Y-m-d\TH:i'), 'reason' => '', 'reverses_id' => ''];
    }

    public function postEntry(Ledger $l): void
    {
        $this->attempt(function () use ($l) {
            $l->post(auth()->user(), $this->owner, $this->ownerId, $this->localDates($this->form) + ['idempotency_key' => $this->key]);
            $this->clearForm();
            $this->dispatch('ledger-changed');
            session()->flash('success', __('ui.saved'));
        });
    }

    #[On('ledger-changed')]
    public function refreshLedger(): void {}

    public function render()
    {
        return view('livewire.ledger-panel', app(Ledger::class)->read(auth()->user(), $this->owner, $this->ownerId));
    }
}
