<?php

namespace App\Policies;

use App\Models\User;
use App\Traits\Policy\HandlesBeforePolicyGate;
use App\Traits\Policy\NovaPolicyProvider;
use Illuminate\Auth\Access\HandlesAuthorization;

class InvitationPolicy
{
    use HandlesAuthorization, HandlesBeforePolicyGate, NovaPolicyProvider;

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

    /**
     * Determine whether the user can access Nova or not?
     *
     * @param \App\Models\User $user
     * @return mixed
     */
    public function viewNova(User $user)
    {
        return $user->getRoleNames()->isNotEmpty();
    }
}
