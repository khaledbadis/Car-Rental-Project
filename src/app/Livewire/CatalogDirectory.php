<?php

namespace App\Livewire;

use App\Models\Customer;
use App\Models\VehicleCategory;
use App\Modules\Catalog\Actions;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

class CatalogDirectory extends Component
{
    use WithPagination;

    #[Locked]
    public string $kind;

    public string $search = '';

    public string $status = 'active';

    public string $category = '';

    public bool $creating = false;

    public array $form = [];

    public string $categoryName = '';

    public function mount(string $kind): void
    {
        $this->kind = $kind;
        app(Actions::class)->authorize(auth()->user(), $kind);
        $this->search = mb_substr((string) request('q', ''), 0, 120);
        $this->clearForm();
    }

    public function clearForm(): void
    {
        $this->form = array_fill_keys(array_keys(config('catalog.'.$this->kind)), null);
        if ($this->kind === 'customer') {
            $this->form['type'] = 'individual';
        } else {
            $this->form['mileage_km'] = 0;
            $this->form['fuel_type'] = 'petrol';
            $this->form['transmission'] = 'manual';
        }
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function updatedCategory(): void
    {
        $this->resetPage();
    }

    public function save(Actions $a): mixed
    {
        try {
            $r = $a->save(auth()->user(), $this->kind, $this->form);
        } catch (ValidationException $e) {
            foreach ($e->errors() as $k => $v) {
                $this->addError('form.'.$k, $v[0]);
            }

return null;
        }

return redirect()->route($this->kind.'s.show', $r->id);
    }

    public function addCategory(Actions $a): void
    {
        try {
            $c = $a->category(auth()->user(), ['name' => $this->categoryName]);
            $this->form['category_id'] = $c->id;
            $this->categoryName = '';
        } catch (ValidationException $e) {
            $this->addError('categoryName', collect($e->errors())->flatten()->first());
        }
    }

    public function render(): mixed
    {
        $a = app(Actions::class);

        return view('livewire.catalog-directory', ['records' => $a->query(auth()->user(), $this->kind, $this->search, $this->status, $this->category ? (int) $this->category : null)->paginate(12), 'categories' => VehicleCategory::orderBy('name')->get(), 'drivers' => $this->kind === 'customer' ? Customer::where('type', 'individual')->whereNull('archived_at')->orderBy('name')->get(['id', 'name']) : collect(), 'duplicates' => $this->kind === 'customer' ? $a->duplicates(auth()->user(), array_intersect_key($this->form, array_flip(['name', 'phone', 'identity_number', 'licence_number']))) : collect()])->layout('components.layouts.app');
    }
}
