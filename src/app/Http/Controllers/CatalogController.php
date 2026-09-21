<?php

namespace App\Http\Controllers;

use App\Models\VehicleCategory;
use App\Modules\Catalog\Actions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class CatalogController
{
    public function __construct(private Actions $a) {}

    public function index(Request $r)
    {
        $kind = $r->route('kind');
        $v = $r->validate(['q' => 'nullable|string|max:120', 'status' => 'nullable|in:active,archived,all', 'category_id' => 'nullable|integer']);

        return $this->a->query($r->user(), $kind, $v['q'] ?? '', $v['status'] ?? 'active', $v['category_id'] ?? null)->paginate(12);
    }

    public function show(Request $r, int $id)
    {
        $kind = $r->route('kind');
        $record = $this->a->find($r->user(), $kind, $id);
        if ($kind === 'vehicle' || $r->user()->can('customer-documents.view')) {
            $record->load('documents');
        }
        if ($kind === 'customer') {
            $record->load(['notes.actor', 'driver']);
        } else {
            $record->load('category');
        }

        return ['data' => $record];
    }

    public function create(Request $r)
    {
        $kind = $r->route('kind');

        return response()->json(['data' => $this->a->save($r->user(), $kind, $r->all())], 201);
    }

    public function update(Request $r, int $id)
    {
        $kind = $r->route('kind');

        return ['data' => $this->a->save($r->user(), $kind, $r->all(), $id)];
    }

    public function archive(Request $r, int $id)
    {
        $kind = $r->route('kind');

        return ['data' => $this->a->archive($r->user(), $kind, $id, $r->all())];
    }

    public function upload(Request $r, int $id)
    {
        $kind = $r->route('kind');

        return response()->json(['data' => $this->a->upload($r->user(), $kind, $id, $r->all())], 201);
    }

    public function download(Request $r, int $id)
    {
        $d = $this->a->document($r->user(), $id);
        abort_unless(Storage::disk('local')->exists($d->path), 404);

        return Storage::disk('local')->download($d->path, 'document-'.$d->id.'.'.match ($d->mime) {
            'application/pdf' => 'pdf','image/png' => 'png',default => 'jpg'
        }, ['X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store']);
    }

    public function remove(Request $r, int $id)
    {
        $this->a->removeDocument($r->user(), $id, $r->all());

        return response()->noContent();
    }

    public function duplicates(Request $r)
    {
        return ['data' => $this->a->duplicates($r->user(), $r->all())];
    }

    public function note(Request $r, int $id)
    {
        return response()->json(['data' => $this->a->note($r->user(), $id, $r->all())], 201);
    }

    public function categories(Request $r)
    {
        Gate::forUser($r->user())->authorize('fleet.view');

        return ['data' => VehicleCategory::orderBy('name')->get()];
    }

    public function category(Request $r)
    {
        return response()->json(['data' => $this->a->category($r->user(), $r->all())], 201);
    }

    public function alerts(Request $r)
    {
        return ['data' => $this->a->alerts($r->user())];
    }
}
