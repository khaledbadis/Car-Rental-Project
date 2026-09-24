<?php

namespace App\Http\Controllers;

use App\Modules\Reservations\Actions;
use Illuminate\Http\Request;

class ReservationController extends Controller
{
    public function index(Request $r, Actions $a)
    {
        return $a->query($r->user(), $r->all())->paginate(25);
    }

    public function show(Request $r, Actions $a, int $id)
    {
        $record = $a->find($r->user(), $id);

        return ['data' => $record, 'conflicts' => $a->affected($record), 'pickup_status' => $a->pickupStatus($record)];
    }

    public function create(Request $r, Actions $a)
    {
        return response()->json(['data' => $a->save($r->user(), $r->all())], 201);
    }

    public function update(Request $r, Actions $a, int $id)
    {
        return ['data' => $a->save($r->user(), $r->all(), $id)];
    }

    public function cancel(Request $r, Actions $a, int $id)
    {
        return ['data' => $a->cancel($r->user(), $id, $r->all())];
    }

    public function availability(Request $r, Actions $a)
    {
        return ['data' => $a->availability($r->user(), $r->all())];
    }

    public function blocks(Request $r, Actions $a)
    {
        return $a->blocks($r->user())->paginate(25);
    }

    public function block(Request $r, Actions $a)
    {
        return response()->json(['data' => $a->block($r->user(), $r->all())], 201);
    }

    public function release(Request $r, Actions $a, int $id)
    {
        return ['data' => $a->release($r->user(),$id,$r->all())];
    }
}
