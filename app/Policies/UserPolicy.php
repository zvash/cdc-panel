<?php

namespace App\Policies;

use App\Models\User;
use App\Traits\Policy\HandlesBeforePolicyGate;
use App\Traits\Policy\NovaPolicyProvider;
use Illuminate\Auth\Access\HandlesAuthorization;

class UserPolicy
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
            'view',
            'create',
            'update',
            'delete',
            'restore',
            'forceDelete',
            'inviteAdmin',
            'inviteAppraiser',
            'viewSelf',
            'updateSelf',
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

    /**
     * Determine whether the user can attach any roles to user.
     *
     * @param \App\Models\User $user
     * @return mixed
     */
    public function attachAnyRole(User $user)
    {
        return $user->hasPermissionTo($this->getPermission('attachRole'));
    }

    /**
     * Determine whether the user can attach role to user.
     *
     * @param \App\Models\User $user
     * @return mixed
     */
    public function attachRole(User $user)
    {
        return $user->hasPermissionTo($this->getPermission('attachRole'));
    }

    /**
     * Determine whether the user can detach role to user.
     *
     * @param \App\Models\User $user
     * @return mixed
     */
    public function detachRole(User $user)
    {
        return $user->hasPermissionTo($this->getPermission('detachRole'));
    }
}
