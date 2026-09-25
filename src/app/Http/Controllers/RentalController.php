<?php

namespace App\Http\Controllers;

use App\Models\InspectionPhoto;
use App\Modules\Rentals\Actions;
use App\Modules\Rentals\Ledger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class RentalController extends Controller
{
    public function index(Request $r, Actions $a)
    {
        return $a->query($r->user(), $r->all())->paginate(20);
    }

    public function show(Request $r, Actions $a, int $id)
    {
        $rental = $a->find($r->user(), $id);

        return ['data' => $rental, 'finances' => app(Ledger::class)->read($r->user(), 'rental', $id), 'versions' => DB::table('rental_versions')->where('rental_id', $id)->orderBy('number')->get()];
    }

    public function create(Request $r, Actions $a)
    {
        return response()->json(['data' => $a->create($r->user(), $r->all())], 201);
    }

    public function handover(Request $r, Actions $a, int $id)
    {
        return ['data' => $a->inspect($r->user(), $id, 'handover', $r->all())];
    }

    public function checkin(Request $r, Actions $a, int $id)
    {
        return ['data' => $a->inspect($r->user(), $id, 'return', $r->all())];
    }

    public function extend(Request $r, Actions $a, int $id)
    {
        return ['data' => $a->extend($r->user(), $id, $r->all())];
    }

    public function cancel(Request $r, Actions $a, int $id)
    {
        return ['data' => $a->cancel($r->user(), $id, $r->all())];
    }

    public function ledger(Request $r, Ledger $l, int $id)
    {
        return $l->read($r->user(), $r->route('owner'), $id);
    }

    public function post(Request $r, Ledger $l, int $id)
    {
        return response()->json(['data' => $l->post($r->user(), $r->route('owner'), $id, $r->all())], 201);
    }

    public function photo(Request $r, Actions $a, int $id)
    {
        return response()->json(['data' => $a->photo($r->user(), $id, $r->all())], 201);
    }

    public function download(Request $r, int $id)
    {
        Gate::forUser($r->user())->authorize('rentals.manage');
        $p = InspectionPhoto::findOrFail($id);

        return Storage::disk('local')->download($p->path,'inspection-'.$id.($p->mime === 'image/png' ? '.png' : '.jpg'),['Content-Type' => $p->mime, 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store']);
    }
}
