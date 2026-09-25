<?php

namespace App\Livewire;

use App\Modules\Rentals\Actions;
use Livewire\Component;
use Livewire\WithPagination;

class Rentals extends Component
{
    use WithPagination;

    public string $status = '';

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        return view('livewire.rentals', ['records' => app(Actions::class)->query(auth()->user(), ['status' => $this->status])->paginate(15)])->layout('components.layouts.app');
    }
}
