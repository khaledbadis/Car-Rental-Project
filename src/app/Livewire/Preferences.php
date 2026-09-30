<?php

namespace App\Livewire;

use App\Modules\Foundation\Actions;
use App\Modules\Release\Profile;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithFileUploads;

class Preferences extends Component
{
    use WithFileUploads;

    public array $profile = [];

    public $avatar;

    public bool $removeAvatar = false;

    public function saveProfile(Profile $a): mixed
    {
        try {
            $user = $a->save(auth()->user(), $this->profile + ['avatar' => $this->avatar, 'remove_avatar' => $this->removeAvatar]);
            auth()->setUser($user);
            $this->avatar = null;
            $this->removeAvatar = false;
            session()->flash('success', __('ui.saved'));

            return redirect()->route('preferences');
        } catch (ValidationException $e) {
            foreach ($e->errors() as $key => $messages) {
                $this->addError($key === 'avatar' ? 'avatar' : 'profile.'.$key, $messages[0]);
            }
        }
    }

    public string $locale = 'fr';

    public string $theme = 'system';

    public string $current_password = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function mount(): void
    {
        $this->profile = auth()->user()->only(['name', 'email', 'phone', 'address']);
        $this->locale = auth()->user()->locale ?? 'fr';
        $this->theme = auth()->user()->theme ?? 'system';
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
