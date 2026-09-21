<?php

namespace App\Livewire;

use App\Modules\Foundation\Actions;
use Livewire\Component;

class Settings extends Component
{
    public $preparation_minutes = 120;

    public $late_grace_minutes = 60;

    public $no_show_minutes = 120;

    public $upload_limit_mb = 10;

    public string $reason = '';

    public function mount(Actions $a): void
    {
        foreach ($a->settings(auth()->user()) as $k => $v) {
            if (property_exists($this, $k)) {
                $this->$k = $v;
            }
        }
    }

    public function save(Actions $a): void
    {
        $a->saveSettings(auth()->user(), $this->only(['preparation_minutes', 'late_grace_minutes', 'no_show_minutes', 'upload_limit_mb', 'reason']));
        $this->reason = '';
        session()->flash('success', __('ui.saved'));
    }

    public function render(): mixed
    {
        return view('livewire.settings')->layout('components.layouts.app');
    }
}
