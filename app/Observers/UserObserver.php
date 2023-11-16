<?php

namespace App\Observers;

use App\Models\User;

class UserObserver
{
    public function saving(User $user)
    {
        if (
            $user->isDirty('email')
            && $user->getOriginal('email')
            && !auth()->user()->isSuperAdmin()
            && !auth()->user()->isSupervisor()) {
            $user->email = $user->getOriginal('email');
        }
    }
}
