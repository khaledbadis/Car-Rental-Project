<?php

namespace App\Http\Controllers;

use App\Modules\Release\Dashboard;
use App\Modules\Release\Documents;
use App\Modules\Release\Profile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class ReleaseController extends Controller
{
    public function dashboard(Request $r, Dashboard $a)
    {
        return ['data' => $a->read($r->user(), $r->all())];
    }

    public function profile(Request $r, Profile $a)
    {
        return ['data' => $a->save($r->user(), $r->all())];
    }

    public function avatar(Request $r)
    {
        Gate::forUser($r->user())->authorize('profile.manage');
        $path = $r->user()->avatar_path;
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return response()->file(Storage::disk('local')->path($path), ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function receipt(Request $r, Documents $a, int $id)
    {
        $receipt = $a->receipt($r->user(), $id);

        return $r->is('api/*') ? ['data' => $receipt] : response()->view('print.receipt', ['receipt' => $receipt])->header('Cache-Control', 'private, no-store');
    }

    public function contract(Request $r, Documents $a, int $id, int $version)
    {
        $data = $a->contract($r->user(), $id, $version);

        return $r->is('api/*') ? ['data' => $data] : response()->view('print.contract', $data)->header('Cache-Control', 'private, no-store');
    }
}
