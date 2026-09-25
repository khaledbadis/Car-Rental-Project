<?php

namespace App\Livewire;

use App\Models\Rental;
use App\Modules\Reservations\Actions;
use Carbon\CarbonImmutable;
use Livewire\Component;
use Livewire\WithPagination;

class Reservations extends Component
{
    use WithPagination;

    public string $status = '';

    public string $from = '';

    public string $to = '';

    public string $mode = 'list';

    public string $week = '';

    public function mount(): void
    {
        app(Actions::class)->authorize(auth()->user());
        $this->week = now('Africa/Algiers')->startOfWeek()->toDateString();
    }

    public function updated($key): void
    {
        $this->resetPage();
    }

    public function shiftWeek(int $direction): void
    {
        $this->week = CarbonImmutable::parse($this->week)->addDays($direction < 0 ? -7 : 7)->toDateString();
    }

    public function render()
    {
        $a = app(Actions::class);
        $this->validate(['week' => 'required|date_format:Y-m-d', 'mode' => 'in:list,calendar']);
        $days = collect(range(0, 6))->map(fn ($n) => CarbonImmutable::parse($this->week, 'Africa/Algiers')->addDays($n));
        $filters = ['status' => $this->status, 'from' => $this->mode === 'calendar' ? $days->first()->toDateString() : $this->from, 'to' => $this->mode === 'calendar' ? $days->last()->toDateString() : $this->to];
        $query = $a->query(auth()->user(), $filters);
        $records = $this->mode === 'calendar' ? $query->get() : $query->paginate(15);
        $blocks = $this->mode === 'calendar' ? $a->blocks(auth()->user())->whereNull('released_at')->where('starts_at', '<', $days->last()->addDay()->utc())->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', $days->first()->utc()))->get() : collect();

        $rentals = $this->mode === 'calendar' && auth()->user()->can('rentals.view') ? Rental::with(['customer:id,name', 'vehicle:id,registration'])->whereIn('status', ['draft', 'active'])->where('starts_at', '<', $days->last()->addDay()->utc())->where(fn ($q) => $q->whereRaw("ends_at + preparation_minutes * interval '1 minute' > ?", [$days->first()->utc()])->orWhere('status', 'active'))->get() : collect();

        return view('livewire.reservations', compact('records', 'days', 'blocks', 'a', 'rentals'))->layout('components.layouts.app');
    }
}
