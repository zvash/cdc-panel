<?php

namespace App\Observers;

use App\Models\User;

class UserObserver
{
    public function saving(User $user)
    {
        if (!$user->id) {
            $password = 'passwords';//substr(md5(rand()), 0, 8);
            $user->password = bcrypt($password);
        }
        if (
            $user->isDirty('email')
            && $user->getOriginal('email')
            && !auth()->user()->isSuperAdmin()
            && !auth()->user()->isSupervisor()) {
            $user->email = $user->getOriginal('email');
        }
    }

//    public function created(User $user)
//    {
//        $url = route('nova.password.email');
//        $user->sendPasswordResetNotification("1234567890");
//    }
}
