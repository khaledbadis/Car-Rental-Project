<?php

namespace App\Http\Controllers;

use App\Modules\Maintenance\Actions;
use Illuminate\Http\Request;

class MaintenanceController extends Controller
{
    public function index(Request $r, Actions $a)
    {
        return $a->query($r->user(), $r->all())->paginate(20);
    }

    public function create(Request $r, Actions $a)
    {
        return response()->json(['data' => $a->record($r->user(), $r->all())], 201);
    }

    public function reminders(Request $r, Actions $a)
    {
        return ['data' => $a->reminders($r->user())];
    }

    public function release(Request $r, Actions $a, int $id)
    {
        return ['data' => $a->release($r->user(), $id, $r->all())];
    }

    public function upload(Request $r, Actions $a, int $id)
    {
        return response()->json(['data' => $a->upload($r->user(), $id, $r->all())], 201);
    }

    public function download(Request $r, Actions $a, int $id)
    {
        return $a->download($r->user(),$id);
    }
}
