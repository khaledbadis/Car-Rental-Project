<?php

namespace App\Livewire;

use App\Modules\Foundation\Notifications as Actions;
use Livewire\Attributes\On;
use Livewire\Component;

class Notifications extends Component
{
    #[On('documents-changed')]
    public function refreshAlerts(): void {}

    public function mark(?string $id = null): void
    {
        app(Actions::class)->mark(auth()->user(), $id);
    }

    public function render(): mixed
    {
        return view('livewire.notifications', ['alerts' => auth()->user()->can('dashboard.view') ? collect(app(Actions::class)->read(auth()->user())) : collect()]);
    }
}
