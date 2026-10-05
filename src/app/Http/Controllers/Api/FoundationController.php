<?php

namespace App\Http\Controllers\Api;

use App\Modules\Foundation\Actions;
use App\Modules\Foundation\Notifications;
use Illuminate\Http\Request;

class FoundationController
{
    public function __construct(private Actions $actions) {}

    public function disable(Request $r, int $id): mixed
    {
        return ['data' => $this->actions->disableStaff($r->user(), $id, $r->all())];
    }

    public function notifications(Request $r): mixed
    {
        return ['data' => app(Notifications::class)->read($r->user())];
    }

    public function readNotifications(Request $r): mixed
    {
        $data = $r->validate(['id' => 'nullable|string|size:64']);
        app(Notifications::class)->mark($r->user(), $data['id'] ?? null);

        return response()->noContent();
    }

    public function me(Request $r): mixed
    {
        return ['data' => $r->user(), 'permissions' => $r->user()->permissions()];
    }

    public function preferences(Request $r): mixed
    {
        return ['data' => $this->actions->preferences($r->user(), $r->all())];
    }

    public function password(Request $r): mixed
    {
        $this->actions->changePassword($r->user(), $r->all());

        return response()->noContent();
    }

    public function staff(Request $r): mixed
    {
        return $this->actions->staff($r->user());
    }

    public function create(Request $r): mixed
    {
        return response()->json(['data' => $this->actions->saveStaff($r->user(), $r->all())], 201);
    }

    public function update(Request $r, int $id): mixed
    {
        return ['data' => $this->actions->saveStaff($r->user(), $r->all(), $id)];
    }

    public function settings(Request $r): mixed
    {
        return ['data' => $this->actions->settings($r->user())];
    }

    public function saveSettings(Request $r): mixed
    {
        return ['data' => $this->actions->saveSettings($r->user(), $r->all())];
    }

    public function audit(Request $r): mixed
    {
        return $this->actions->events($r->user());
    }

    public function logout(Request $r): mixed
    {
        $r->user()->currentAccessToken()?->delete();

        return response()->noContent();
    }
}
