<?php

namespace App\Modules\Catalog;

use App\Models\Customer;
use App\Models\CustomerNote;
use App\Models\Document;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleCategory;
use App\Modules\Foundation\Actions as Foundation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class Actions
{
    public function model(string $kind): string
    {
        return match ($kind) {
            'vehicle' => Vehicle::class,'customer' => Customer::class,default => abort(404)
        };
    }

    public function authorize(User $actor, string $kind, string $ability = 'view'): void
    {
        Gate::forUser($actor)->authorize(($kind === 'vehicle' ? 'fleet' : 'customers').'.'.$ability);
    }

    public function query(User $actor, string $kind, string $search = '', string $status = 'active', ?int $category = null)
    {
        $this->authorize($actor, $kind);
        $q = $this->model($kind)::query();
        if ($status === 'active') {
            $q->whereNull('archived_at');
        } elseif ($status === 'archived') {
            $q->whereNotNull('archived_at');
        }
        if ($search !== '') {
            $needle = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], mb_substr($search, 0, 120)).'%';
            $q->where(function ($q) use ($kind, $needle) {
                foreach ($kind === 'vehicle' ? ['registration', 'make', 'model'] : ['name', 'phone'] as $col) {
                    $q->orWhere($col, 'ilike', $needle);
                }
            });
        }
        if ($kind === 'vehicle') {
            $q->with('category');
            if ($category) {
                $q->where('category_id', $category);
            }
        }

        return $q->orderByDesc('id');
    }

    public function find(User $actor, string $kind, int $id): Model
    {
        $this->authorize($actor, $kind);

        return $this->model($kind)::findOrFail($id);
    }

    public function category(User $actor, array $input): VehicleCategory
    {
        Gate::forUser($actor)->authorize('fleet.manage');
        $v = Validator::make($input, ['name' => 'required|string|max:80|unique:vehicle_categories'])->validate();

        return DB::transaction(function () use ($actor, $v) {
            $c = VehicleCategory::create($v);
            app(Foundation::class)->audit($actor, 'category.created', 'category', $c->id, null, $v);

            return $c;
        });
    }

    private function normalized(?string $value): ?string
    {
        return $value ? mb_strtoupper(preg_replace('/[\s\-]+/u', '', trim($value))) : null;
    }

    public function save(User $actor, string $kind, array $input, ?int $id = null): Model
    {
        $this->authorize($actor, $kind, 'manage');
        $class = $this->model($kind);
        $rules = $kind === 'vehicle' ? [
            'registration' => 'required|string|max:60', 'make' => 'required|string|max:80', 'model' => 'required|string|max:100', 'year' => 'required|integer|min:1950|max:'.(now()->year + 1), 'category_id' => 'required|integer|exists:vehicle_categories,id', 'fuel_type' => ['required', Rule::in(config('catalog.options.fuel'))], 'transmission' => ['required', Rule::in(config('catalog.options.transmission'))], 'mileage_km' => 'required|integer|min:0|max:9999999', 'daily_rate' => 'required|numeric|min:0|max:9999999999.99|decimal:0,2', 'weekly_rate' => 'nullable|numeric|min:0|max:9999999999.99|decimal:0,2', 'monthly_rate' => 'nullable|numeric|min:0|max:9999999999.99|decimal:0,2', 'entered_service_at' => 'nullable|date_format:Y-m-d', 'notes' => 'nullable|string|max:4000',
        ] : [
            'type' => ['required', Rule::in(['individual', 'company'])], 'name' => 'required|string|max:160', 'phone' => 'nullable|string|max:40', 'address' => 'nullable|string|max:1000', 'birth_date' => 'nullable|date_format:Y-m-d|before_or_equal:today', 'identity_type' => ['nullable', Rule::in(config('catalog.options.identity_type'))], 'identity_number' => 'nullable|string|max:80', 'identity_issue_date' => 'nullable|date_format:Y-m-d', 'identity_expiry_date' => 'nullable|date_format:Y-m-d', 'licence_number' => 'nullable|string|max:80', 'licence_issue_date' => 'nullable|date_format:Y-m-d', 'licence_expiry_date' => 'nullable|date_format:Y-m-d', 'licence_country' => 'nullable|string|size:2|alpha', 'contact_person' => 'nullable|string|max:160', 'tax_identifier' => 'nullable|string|max:80', 'driver_id' => ['nullable', 'integer', Rule::exists('customers', 'id')->where('type', 'individual')->whereNull('archived_at')],
        ];
        if ($id) {
            $rules['version'] = 'required|integer|min:1';
            $rules['reason'] = 'required|string|max:500';
        }
        $v = Validator::make($input, $rules)->validate();
        if ($kind === 'vehicle') {
            $v['registration'] = $this->normalized($v['registration']);
        } else {
            foreach (['phone', 'address', 'birth_date', 'identity_type', 'identity_number', 'identity_issue_date', 'identity_expiry_date', 'licence_number', 'licence_issue_date', 'licence_expiry_date', 'licence_country', 'contact_person', 'tax_identifier', 'driver_id'] as $field) {
                $v[$field] = $v[$field] ?? null;
            }
            $v['identity_number'] = $this->normalized($v['identity_number']);
            $v['licence_number'] = $this->normalized($v['licence_number']);
            $v['licence_country'] = $this->normalized($v['licence_country']);
            $v['phone_normalized'] = preg_replace('/\D/', '', $v['phone'] ?? '') ?: null;
            if ($v['type'] === 'company') {
                foreach (['birth_date', 'identity_type', 'identity_number', 'identity_issue_date', 'identity_expiry_date', 'licence_number', 'licence_issue_date', 'licence_expiry_date', 'licence_country'] as $f) {
                    $v[$f] = null;
                }
            } else {
                $v['driver_id'] = null;
                $v['contact_person'] = null;
                $v['tax_identifier'] = null;
            }
            Validator::make($v, ['identity_type' => 'required_with:identity_number', 'licence_country' => 'required_with:licence_number', 'identity_expiry_date' => ['nullable', 'date', ...(! empty($v['identity_issue_date']) ? ['after_or_equal:identity_issue_date'] : [])], 'licence_expiry_date' => ['nullable', 'date', ...(! empty($v['licence_issue_date']) ? ['after_or_equal:licence_issue_date'] : [])]])->validate();
        }

        return DB::transaction(function () use ($actor, $kind, $class, $id, $v) {
            // One low-volume catalogue write lock also serializes normalized uniqueness checks.
            DB::table('agency_settings')->where('id', 1)->lockForUpdate()->first();
            $record = $id ? $class::lockForUpdate()->findOrFail($id) : new $class;
            abort_if($record->archived_at, 409, __('catalog.archived_readonly'));
            if ($id) {
                abort_if($record->version !== (int) $v['version'], 409, __('catalog.stale'));
            }
            if ($kind === 'vehicle') {
                Validator::make($v, ['registration' => ['required', Rule::unique('vehicles')->ignore($id)]])->validate();
            } else {
                Validator::make($v, ['identity_number' => ['nullable', Rule::unique('customers')->where('identity_type', $v['identity_type'])->ignore($id)], 'licence_number' => ['nullable', Rule::unique('customers')->where('licence_country', $v['licence_country'])->ignore($id)]])->validate();
                if ($v['type'] === 'company' && $v['driver_id']) {
                    Validator::make($v, ['driver_id' => ['different:id', Rule::exists('customers', 'id')->where('type', 'individual')->whereNull('archived_at')]])->validate();
                    abort_if($id && (int) $v['driver_id'] === $id, 422, __('catalog.driver_in_use'));
                }
                if ($id && $record->type === 'individual' && $v['type'] === 'company') {
                    abort_if(Customer::where('driver_id', $id)->exists(), 409, __('catalog.driver_in_use'));
                }
            }
            $before = $record->exists ? $record->only($kind === 'vehicle' ? array_keys(config('catalog.vehicle')) : ['name', 'type']) : null;
            $record->fill(collect($v)->except(['version', 'reason'])->all());
            $record->version = $id ? $record->version + 1 : 1;
            $record->save();
            app(Foundation::class)->audit($actor, $kind.($id ? '.updated' : '.created'), $kind, $record->id, $before, $record->only($kind === 'vehicle' ? array_keys(config('catalog.vehicle')) : ['name', 'type']), $v['reason'] ?? null);

            return $record;
        });
    }

    public function archive(User $actor, string $kind, int $id, array $input): Model
    {
        $this->authorize($actor, $kind, 'archive');
        $v = Validator::make($input, ['reason' => 'required|string|max:500', 'version' => 'required|integer'])->validate();

        return DB::transaction(function () use ($actor, $kind, $id, $v) {
            DB::table('agency_settings')->where('id', 1)->lockForUpdate()->first();
            $record = $this->model($kind)::lockForUpdate()->findOrFail($id);
            abort_if($record->version !== (int) $v['version'], 409, __('catalog.stale'));
            if (! $record->archived_at) {
                $record->update(['archived_at' => now(), 'version' => $record->version + 1]);
                app(Foundation::class)->audit($actor, $kind.'.archived', $kind, $id, null, ['archived' => true], $v['reason']);
            }

            return $record;
        });
    }

    public function duplicates(User $actor, array $input, ?int $except = null)
    {
        $this->authorize($actor, 'customer');
        $v = Validator::make($input, ['name' => 'nullable|string|max:160', 'phone' => 'nullable|string|max:40', 'identity_number' => 'nullable|string|max:80', 'licence_number' => 'nullable|string|max:80'])->validate();
        $phone = preg_replace('/\D/', '', $v['phone'] ?? '');
        $name = trim($v['name'] ?? '');
        $identity = $this->normalized($v['identity_number'] ?? null);
        $licence = $this->normalized($v['licence_number'] ?? null);
        if (! $phone && ! $name && ! $identity && ! $licence) {
            return collect();
        }

        return Customer::where('id', '!=', $except ?? 0)->where(function ($q) use ($phone, $name, $identity, $licence) {
            if ($phone) {
                $q->orWhere('phone_normalized', $phone);
            }if ($name) {
                $q->orWhereRaw('lower(name) = lower(?)', [$name]);
            }if ($identity) {
                $q->orWhere('identity_number', $identity);
            }if ($licence) {
                $q->orWhere('licence_number', $licence);
            }
        })->limit(10)->get(['id', 'name', 'phone', 'archived_at']);
    }

    public function note(User $actor, int $id, array $input): CustomerNote
    {
        $this->authorize($actor, 'customer', 'manage');
        $record = Customer::findOrFail($id);
        abort_if($record->archived_at, 409, __('catalog.archived_readonly'));
        $v = Validator::make($input, ['category' => ['required', Rule::in(['late_return', 'unpaid', 'accident', 'damage', 'other'])], 'body' => 'required|string|max:4000'])->validate();

        return DB::transaction(function () use ($actor, $id, $v) {
            $owner = Customer::lockForUpdate()->findOrFail($id);
            abort_if($owner->archived_at, 409, __('catalog.archived_readonly'));
            $note = CustomerNote::create($v + ['actor_id' => $actor->id, 'customer_id' => $id]);
            app(Foundation::class)->audit($actor, 'customer.note_added', 'customer', $id, null, ['note_id' => $note->id]);

            return $note;
        });
    }

    public function upload(User $actor, string $kind, int $id, array $input): Document
    {
        Gate::forUser($actor)->authorize($kind === 'vehicle' ? 'fleet.manage' : 'customers.manage');
        $record = $this->find($actor, $kind, $id);
        abort_if($record->archived_at, 409, __('catalog.archived_readonly'));
        $limit = min(10, (int) DB::table('agency_settings')->value('upload_limit_mb')) * 1024;
        $v = Validator::make($input, ['type' => ['required', Rule::in(config('catalog.documents.'.$kind))], 'file' => 'required|file|mimes:pdf,jpg,jpeg,png|max:'.$limit, 'expires_at' => 'nullable|date_format:Y-m-d'])->validate();
        $detectedMime = (new \finfo(FILEINFO_MIME_TYPE))->file($v['file']->getRealPath());
        if (! in_array($detectedMime, ['application/pdf', 'image/jpeg', 'image/png'], true)) {
            throw ValidationException::withMessages(['file' => __('validation.mimes', ['attribute' => __('catalog.file')])]);
        }
        $path = $v['file']->store('documents', 'local');
        if (! $path) {
            throw new \RuntimeException('Private file storage failed');
        }
        try {
            return DB::transaction(function () use ($actor, $kind, $id, $v, $path, $detectedMime) {
                $owner = $this->model($kind)::lockForUpdate()->findOrFail($id);
                abort_if($owner->archived_at, 409, __('catalog.archived_readonly'));
                $doc = Document::create([$kind.'_id' => $id, 'type' => $v['type'], 'path' => $path, 'original_name' => mb_substr(basename($v['file']->getClientOriginalName()), 0, 180), 'mime' => $detectedMime, 'size' => $v['file']->getSize(), 'expires_at' => $v['expires_at'] ?? null, 'uploaded_by' => $actor->id]);
                app(Foundation::class)->audit($actor, 'document.uploaded', $kind, $id, null, ['document_id' => $doc->id, 'type' => $doc->type]);

                return $doc;
            });
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($path);
            throw $e;
        }
    }

    public function document(User $actor, int $id): Document
    {
        $doc = Document::whereNull('removed_at')->findOrFail($id);
        Gate::forUser($actor)->authorize($doc->vehicle_id ? 'fleet.view' : 'customer-documents.view');

        return $doc;
    }

    public function removeDocument(User $actor, int $id, array $input): void
    {
        Gate::forUser($actor)->authorize('documents.remove');
        $v = Validator::make($input, ['reason' => 'required|string|max:500'])->validate();
        $doc = DB::transaction(function () use ($actor, $id, $v) {
            $d = Document::whereNull('removed_at')->lockForUpdate()->findOrFail($id);
            $d->update(['removed_at' => now()]);
            app(Foundation::class)->audit($actor, 'document.removed', 'document', $id, ['type' => $d->type], null, $v['reason']);

            return $d;
        });
        Storage::disk('local')->delete($doc->path);
    }

    public function alerts(User $actor)
    {
        Gate::forUser($actor)->authorize('fleet.view');

        return Document::whereNull('removed_at')->whereIn('type', ['insurance', 'inspection'])->whereIn('vehicle_id', Vehicle::whereNull('archived_at')->select('id'))->whereDate('expires_at', '<=', now('Africa/Algiers')->addDays(30)->toDateString())->orderBy('expires_at')->get();
    }
}
