<?php

namespace App\Modules\Foundation;

use App\Models\User;
use App\Support\Input;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class Actions
{
    public function audit(?User $actor, string $action, string $type, string|int $id, ?array $before, ?array $after, ?string $reason = null): void
    {
        DB::table('audit_events')->insert(['actor_id' => $actor?->id, 'action' => $action, 'subject_type' => $type, 'subject_id' => (string) $id, 'before' => $before === null ? null : json_encode($before), 'after' => $after === null ? null : json_encode($after), 'reason' => $reason, 'created_at' => now()]);
    }

    public function staff(User $actor): mixed
    {
        Gate::forUser($actor)->authorize('staff.manage');

        return User::orderBy('name')->paginate(20);
    }

    public function saveStaff(User $actor, array $input, ?int $id = null): User
    {
        Gate::forUser($actor)->authorize('staff.manage');
        if (isset($input['email']) && is_string($input['email'])) {
            $input['email'] = strtolower(trim($input['email']));
        }

        return DB::transaction(function () use ($actor, $input, $id) {
            // Serialize staff administration, including simultaneous demotions of different managers.
            DB::table('agency_settings')->where('id', 1)->lockForUpdate()->first();
            $user = $id ? User::lockForUpdate()->findOrFail($id) : new User;
            $v = Validator::make(Input::normalize($input), ['name' => 'required|string|max:120', 'email' => ['required', 'email', 'max:190', Rule::unique('users')->ignore($user->id)], 'role' => ['required', Rule::in(array_keys(config('access.roles')))], 'active' => 'required|boolean', 'password' => [$id ? 'nullable' : 'required', 'string', 'min:12', 'max:128'], 'reason' => 'required|string|max:500'])->validate();
            if ($user->exists && $user->role === 'manager' && $user->active && (! $v['active'] || $v['role'] !== 'manager') && User::where('role', 'manager')->where('active', true)->count() <= 1) {
                throw ValidationException::withMessages(['role' => __('ui.last_manager')]);
            }
            if ($user->id === $actor->id && (! $v['active'] || $v['role'] !== 'manager')) {
                throw ValidationException::withMessages(['role' => __('ui.self_change')]);
            }
            $before = $user->exists ? $user->only(['name', 'email', 'role', 'active']) : null;
            $user->fill(collect($v)->only(['name', 'email', 'role', 'active'])->all());
            $reset = ! empty($v['password']);
            if ($reset) {
                $user->password = $v['password'];
                $user->must_change_password = true;
                $user->remember_token = null;
            }
            $user->save();
            if ($reset || ! $user->active || ($before && $before['role'] !== $user->role)) {
                $user->tokens()->delete();
                DB::table('sessions')->where('user_id', $user->id)->delete();
            }
            $this->audit($actor, $before ? 'staff.updated' : 'staff.created', 'user', $user->id, $before, $user->only(['name', 'email', 'role', 'active']), $v['reason']);
            if ($reset) {
                $this->audit($actor, 'password.reset', 'user', $user->id, null, null, $v['reason']);
            }

            return $user;
        });
    }

    public function preferences(User $actor, array $input): User
    {
        $v = Validator::make(Input::normalize($input), ['locale' => ['required', Rule::in(['fr', 'ar', 'en'])], 'theme' => ['required', Rule::in(['light', 'dark', 'system'])]])->validate();
        $actor->update($v);

        return $actor;
    }

    public function changePassword(User $actor, array $input): void
    {
        $v = Validator::make(Input::normalize($input), ['current_password' => 'required|string', 'password' => 'required|string|min:12|max:128|confirmed'])->validate();
        if (! Hash::check($v['current_password'], $actor->password)) {
            throw ValidationException::withMessages(['current_password' => __('ui.invalid_password')]);
        }
        if (Hash::check($v['password'], $actor->password)) {
            throw ValidationException::withMessages(['password' => __('ui.different_password')]);
        }
        DB::transaction(function () use ($actor, $v) {
            $actor->update(['password' => $v['password'], 'must_change_password' => false, 'remember_token' => null]);
            $actor->tokens()->delete();
            DB::table('sessions')->where('user_id', $actor->id)->delete();
            $this->audit($actor, 'password.changed', 'user', $actor->id, null, null);
        });
    }

    public function settings(User $actor): array
    {
        Gate::forUser($actor)->authorize('settings.manage');

        return (array) DB::table('agency_settings')->find(1);
    }

    public function saveSettings(User $actor, array $input): array
    {
        Gate::forUser($actor)->authorize('settings.manage');
        $v = Validator::make(Input::normalize($input), ['preparation_minutes' => 'required|integer|min:0|max:1440', 'late_grace_minutes' => 'required|integer|min:0|max:1440', 'no_show_minutes' => 'required|integer|min:0|max:1440', 'upload_limit_mb' => 'required|integer|min:1|max:10', 'reason' => 'required|string|max:500'])->validate();

        return DB::transaction(function () use ($actor, $v) {
            $before = (array) DB::table('agency_settings')->where('id', 1)->lockForUpdate()->first();
            $data = collect($v)->except('reason')->all();
            DB::table('agency_settings')->where('id', 1)->update($data + ['updated_at' => now()]);
            $this->audit($actor, 'settings.updated', 'agency', 1, $before, $data, $v['reason']);

            return $this->settings($actor);
        });
    }

    public function events(User $actor): mixed
    {
        Gate::forUser($actor)->authorize('audit.view');

        return DB::table('audit_events')->leftJoin('users', 'users.id', '=', 'audit_events.actor_id')->select('audit_events.*', 'users.name as actor_name')->orderByDesc('audit_events.id')->paginate(20);
    }
}
