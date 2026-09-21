<?php

namespace App\Livewire;

use App\Modules\Catalog\Actions;
use Livewire\Attributes\On;
use Livewire\Component;

class Notifications extends Component
{
    #[On('documents-changed')]
    public function refreshAlerts(): void {}

    public function render(): mixed
    {
        return view('livewire.notifications', ['alerts' => auth()->user()->can('fleet.view') ? app(Actions::class)->alerts(auth()->user()) : collect()]);
    }
}
