<?php

namespace App\Livewire;

use App\Models\Vehicle;
use App\Modules\Reporting\Report;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Reports extends Component
{
    public string $from;

    public string $to;

    public string $vehicle_id = '';

    #[Locked]
    public array $period = [];

    public function mount(): void
    {
        $this->from = now('Africa/Algiers')->startOfMonth()->toDateString();
        $this->to = now('Africa/Algiers')->toDateString();
        $this->period = ['from' => $this->from, 'to' => $this->to];
    }

    public function apply(Report $a): void
    {
        $this->period = $a->filters($this->only(['from', 'to', 'vehicle_id']));
    }

    public function render()
    {
        return view('livewire.reports', ['data' => app(Report::class)->read(auth()->user(), $this->period), 'vehicles' => Vehicle::orderBy('registration')->get(['id', 'registration'])])->layout('components.layouts.app');
    }
}
