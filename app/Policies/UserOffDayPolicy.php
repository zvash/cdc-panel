<?php

namespace App\Policies;

use App\Models\User;
use App\Traits\Policy\HandlesBeforePolicyGate;
use App\Traits\Policy\NovaPolicyProvider;
use Illuminate\Auth\Access\HandlesAuthorization;

class UserOffDayPolicy
{
    use HandlesAuthorization, HandlesBeforePolicyGate;

    /**
     * The policies that we want to register.
     *
     * @return array
     */
    protected function policies()
    {
        return [
            'create',
            'update',
            'view',
            'delete',
            'restore',
            'forceDelete',
        ];
    }

    public function viewAny(User $user)
    {
        return true;
    }

    public function view()
    {
        return true;
    }
}
