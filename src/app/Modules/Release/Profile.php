<?php

namespace App\Modules\Release;

use App\Models\User;
use App\Modules\Foundation\Actions;
use App\Support\Input;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class Profile
{
    public function save(User $actor, array $input): User
    {
        Gate::forUser($actor)->authorize('profile.manage');
        $input = Input::normalize($input);
        if (is_string($input['email'] ?? null)) {
            $input['email'] = strtolower($input['email']);
        }
        $v = Validator::make($input, [
            'username' => 'prohibited', 'name' => 'required|string|max:120',
            'email' => ['required', 'email', 'max:190', Rule::unique('users')->ignore($actor->id)],
            'phone' => 'nullable|string|max:50', 'address' => 'nullable|string|max:1000',
            'avatar' => 'nullable|image|mimes:jpg,jpeg,png|max:2048|dimensions:max_width=4096,max_height=4096',
            'remove_avatar' => 'nullable|boolean',
        ])->validate();
        $path = isset($v['avatar']) ? $v['avatar']->store('avatars', 'local') : null;
        if (isset($v['avatar']) && ! $path) {
            throw new \RuntimeException('Private avatar storage failed.');
        }
        try {
            [$user, $old] = DB::transaction(function () use ($actor, $v, $path) {
                $user = User::lockForUpdate()->findOrFail($actor->id);
                $old = $user->avatar_path;
                $user->fill(collect($v)->only(['name', 'email', 'phone', 'address'])->all());
                if ($user->isDirty('email')) {
                    $user->email_verified_at = null;
                }
                if ($path || ($v['remove_avatar'] ?? false)) {
                    $user->avatar_path = $path;
                }
                $changed = array_keys($user->getDirty());
                $user->save();
                app(Actions::class)->audit($actor, 'profile.updated', 'user', $user->id, null, ['fields' => $changed]);

                return [$user, $old];
            });
        } catch (\Throwable $e) {
            if ($path) {
                Storage::disk('local')->delete($path);
            }
            throw $e;
        }
        if ($old && $old !== $user->avatar_path) {
            Storage::disk('local')->delete($old);
        }

        return $user;
    }
}
