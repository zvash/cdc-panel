<?php

namespace App\Observers;

use App\Models\User;
use App\Notifications\UserInvited;
use Illuminate\Support\Facades\Password;

class UserObserver
{
    public function saving(User $user)
    {
        if (!auth()->user()) {
            return;
        }
        if (!$user->id) {
            $password = substr(md5(rand()), 0, 8);
            $user->password = bcrypt($password);
        }
        if (
            $user->isDirty('email')
            && $user->getOriginal('email')
            && !auth()->user()->isSuperAdmin()
            && !auth()->user()->isSupervisor()) {
            $user->email = $user->getOriginal('email');
        }
        if ($user->id && $user->isDirty('role') && $user->id != auth()->user()->id) {
            if ($user->getOriginal('role')) {
                $user->removeRole($user->getOriginal('role'));
            }
            $user->assignRole($user->role);
        } else if ($user->id == auth()->user()->id) {
            $user->role = $user->getOriginal('role');
        }
    }

    public function created(User $user)
    {
        $user->assignRole($user->role);
        if ($user->id && $user->isDirty('role')) {
            $user->assignRole($user->role);
        }
        if ($user->id > 6) {
            if (!$user->roles()->count()) {
                $user->assignRole('appraiser');
            }
            $user->notify(new \App\Notifications\UserInvited());
        }
    }
}
