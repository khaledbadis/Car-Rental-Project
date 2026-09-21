<?php

namespace App\Livewire;

use App\Models\User;
use App\Modules\Foundation\Actions;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

class Staff extends Component
{
    use WithPagination;

    #[Locked]
    public ?int $editing = null;

    public string $name = '';

    public string $email = '';

    public string $role = 'agent';

    public bool $active = true;

    public string $password = '';

    public string $reason = '';

    public function edit(int $id): void
    {
        Gate::authorize('staff.manage');
        $u = User::findOrFail($id);
        $this->editing = $id;
        foreach (['name', 'email', 'role', 'active'] as $k) {
            $this->$k = $u->$k;
        }$this->password = '';
        $this->reason = '';
        $this->resetValidation();
    }

    public function clear(): void
    {
        $this->reset(['editing', 'name', 'email', 'role', 'active', 'password', 'reason']);
        $this->resetValidation();
    }

    public function save(Actions $actions): void
    {
        $actions->saveStaff(auth()->user(), $this->only(['name', 'email', 'role', 'active', 'password', 'reason']), $this->editing);
        $this->clear();
        session()->flash('success', __('ui.saved'));
    }

    public function render(): mixed
    {
        return view('livewire.staff', ['users' => app(Actions::class)->staff(auth()->user())])->layout('components.layouts.app');
    }
}
