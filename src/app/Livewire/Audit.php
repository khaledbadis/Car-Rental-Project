<?php

namespace App\Livewire;

use App\Modules\Foundation\Actions;
use Livewire\Component;
use Livewire\WithPagination;

class Audit extends Component
{
    use WithPagination;

    public function render(): mixed
    {
        return view('livewire.audit', ['events' => app(Actions::class)->events(auth()->user())])->layout('components.layouts.app');
    }
}
