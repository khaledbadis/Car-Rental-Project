<?php

namespace App\Modules\Foundation;

use App\Models\User;
use App\Modules\Release\Dashboard;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class Notifications
{
    public function read(User $u): array
    {
        Gate::forUser($u)->authorize('dashboard.view');
        $today = now('Africa/Algiers')->toDateString();
        $alerts = app(Dashboard::class)->read($u, ['from' => $today, 'to' => $today], false)['urgent'];
        $read = DB::table('notification_reads')->where('user_id', $u->id)->pluck('fingerprint')->flip();
        $items = [];
        foreach ($alerts as $alert) {
            $key = hash('sha256', $today.'|'.$alert['kind'].'|'.$alert['url'].'|'.($alert['notification_key'] ?? ''));
            $items[$key] = $alert + ['id' => $key, 'read' => $read->has($key)];
        }

        return array_values($items);
    }

    public function mark(User $u, ?string $id = null): void
    {
        $items = $this->read($u);
        if ($id !== null) {
            abort_unless(collect($items)->contains('id', $id), 404);
        }
        foreach ($items as $item) {
            if (! $item['read'] && ($id === null || $item['id'] === $id)) {
                DB::table('notification_reads')->insertOrIgnore(['user_id' => $u->id, 'fingerprint' => $item['id'], 'read_at' => now()]);
            }
        }
    }
}
