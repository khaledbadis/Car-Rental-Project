<?php

namespace App\Livewire;

use App\Modules\Foundation\Actions;
use Livewire\Component;

class Preferences extends Component
{
    public string $locale = 'fr';

    public string $theme = 'system';

    public string $current_password = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function mount(): void
    {
        $this->locale = auth()->user()->locale;
        $this->theme = auth()->user()->theme;
    }

    public function save(Actions $actions): mixed
    {
        $actions->preferences(auth()->user(), $this->only(['locale', 'theme']));
        app()->setLocale(auth()->user()->locale);
        session()->flash('success', __('ui.saved'));

        return redirect()->route('preferences');
    }

    public function changePassword(Actions $actions): mixed
    {
        $actions->changePassword(auth()->user(), $this->only(['current_password', 'password', 'password_confirmation']));
        session()->regenerate();
        session()->flash('success', __('ui.saved'));

        return redirect()->route('dashboard');
    }

    public function render(): mixed
    {
        return view('livewire.preferences')->layout('components.layouts.app');
    }
}
