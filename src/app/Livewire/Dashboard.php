<?php

namespace App\Livewire;

use Carbon\CarbonImmutable;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Dashboard extends Component
{
    public string $from;

    public string $to;

    #[Locked]
    public array $period = [];

    public function mount(): void
    {
        $this->from = now('Africa/Algiers')->startOfMonth()->toDateString();
        $this->to = now('Africa/Algiers')->endOfMonth()->toDateString();
        $this->period = ['from' => $this->from, 'to' => $this->to];
    }

    public function apply(): void
    {
        $this->validate(['from' => 'required|date_format:Y-m-d', 'to' => 'required|date_format:Y-m-d|after_or_equal:from']);
        if (CarbonImmutable::parse($this->from)->diffInDays(CarbonImmutable::parse($this->to)) > 365) {
            $this->addError('to', __('release.range_limit'));

            return;
        }
        $this->period = ['from' => $this->from, 'to' => $this->to];
    }

    public function render()
    {
        return view('livewire.dashboard', ['data' => app(\App\Modules\Release\Dashboard::class)->read(auth()->user(), $this->period)])->layout('components.layouts.app');
    }
}
