<?php

namespace App\Policies;

use App\Models\AppraisalJobAssignment;
use App\Models\User;
use App\Traits\Policy\HandlesBeforePolicyGate;
use App\Traits\Policy\NovaPolicyProvider;
use Illuminate\Auth\Access\HandlesAuthorization;

class AppraisalJobAssignmentPolicy
{
    use HandlesAuthorization, HandlesBeforePolicyGate, NovaPolicyProvider;

    protected function policies()
    {
        return [
            'create',
            'update',
            'view',
            'delete',
            'restore',
            'forceDelete',
            'viewSelf',
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

    public function view(User $user, AppraisalJobAssignment $model)
    {
        return true;
    }

    public function update(User $user, AppraisalJobAssignment $model)
    {
        return true;
    }
}
