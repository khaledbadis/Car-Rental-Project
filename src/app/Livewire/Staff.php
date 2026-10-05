<?php

namespace App\Livewire;

use App\Models\User;
use App\Modules\Foundation\Actions;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
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

    public bool $showEditor = false;

    public bool $showDelete = false;

    #[Locked]
    public ?int $deleting = null;

    public string $deleteReason = '';

    public function create(): void
    {
        Gate::authorize('staff.manage');
        $this->clear();
        $this->showEditor = true;
    }

    public function confirmDelete(int $id): void
    {
        Gate::authorize('staff.manage');
        User::findOrFail($id);
        $this->deleting = $id;
        $this->deleteReason = '';
        $this->resetValidation();
        $this->showDelete = true;
    }

    public function delete(Actions $a): void
    {
        $this->validate(['deleteReason' => 'required|string|max:500']);
        abort_unless($this->deleting, 404);
        try {
            $a->disableStaff(auth()->user(), $this->deleting, ['reason' => $this->deleteReason]);
            $this->showDelete = false;
            session()->flash('success', __('ui.saved'));
        } catch (ValidationException $e) {
            $this->addError('deleteReason', collect($e->errors())->flatten()->first());
        }
    }

    public function edit(int $id): void
    {
        Gate::authorize('staff.manage');
        $u = User::findOrFail($id);
        $this->editing = $id;
        $this->showEditor = true;
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
        $this->showEditor = false;
        session()->flash('success', __('ui.saved'));
    }

    public function render(): mixed
    {
        return view('livewire.staff', ['users' => app(Actions::class)->staff(auth()->user())])->layout('components.layouts.app');
    }
}
