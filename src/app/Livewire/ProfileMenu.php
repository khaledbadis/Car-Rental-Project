<?php

namespace App\Livewire;

use App\Modules\Foundation\Actions;
use Livewire\Attributes\Locked;
use Livewire\Component;

class ProfileMenu extends Component
{
    #[Locked]
    public string $returnTo;

    public function mount(): void
    {
        $this->returnTo = '/'.ltrim(request()->getRequestUri(), '/');
    }

    public function choose(string $field, string $value, Actions $a): mixed
    {
        abort_unless(in_array($field, ['locale', 'theme']), 422);
        $u = auth()->user();
        $a->preferences($u, array_replace($u->only(['locale', 'theme']), [$field => $value]));

        return redirect($this->returnTo);
    }

    public function render()
    {
        return view('livewire.profile-menu');
    }
}
