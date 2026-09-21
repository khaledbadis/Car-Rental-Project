<?php

namespace App\Livewire;

use App\Modules\Catalog\Actions;
use Livewire\Component;

class GlobalSearch extends Component
{
    public string $query = '';

    public function render(): mixed
    {
        $a = app(Actions::class);
        $results = [];
        if (mb_strlen(trim($this->query)) >= 2) {
            foreach (['vehicle', 'customer'] as $kind) {
                if (auth()->user()->can($kind === 'vehicle' ? 'fleet.view' : 'customers.view')) {
                    $results[$kind] = $a->query(auth()->user(), $kind, $this->query)->limit(5)->get();
                }
            }
        }

return view('livewire.global-search', compact('results'));
    }
}
