<?php

namespace App\Services;

use App\Enums\RoleSlug;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class UserService
{
    /**
     * Admin-controlled registration (spec §4.1). The account gets an unusable
     * random password and the new user receives a "set your password" link,
     * so the admin never sees or transmits anyone's password.
     *
     * @param  array{name: string, email: string, role: string}  $data
     */
    public function create(array $data): User
    {
        $user = DB::transaction(function () use ($data) {
            $user = new User([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Str::random(64),
            ]);
            $user->role()->associate(Role::for(RoleSlug::from($data['role'])));
            $user->save();

            return $user;
        });

        // Sent after commit: never email a link for an account that rolled back.
        Password::sendResetLink(['email' => $user->email]);

        return $user;
    }

    /**
     * @param  array{name?: string, email?: string, role?: string, is_active?: bool}  $data
     */
    public function update(User $user, array $data): User
    {
        return DB::transaction(function () use ($user, $data) {
            $user->fill(array_intersect_key($data, array_flip(['name', 'email'])));

            if (isset($data['role'])) {
                $user->role()->associate(Role::for(RoleSlug::from($data['role'])));
            }

            $wasDeactivated = isset($data['is_active']) && $user->is_active && ! $data['is_active'];
            if (isset($data['is_active'])) {
                $user->is_active = $data['is_active'];
            }

            $user->save();

            if ($wasDeactivated) {
                $this->endSessions($user);
            }

            return $user;
        });
    }

    /** Users are deactivated, never deleted, so their history stays intact. */
    public function deactivate(User $user): void
    {
        $this->update($user, ['is_active' => false]);
    }

    /** Log the user out everywhere (database session driver). */
    private function endSessions(User $user): void
    {
        DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
    }
}
