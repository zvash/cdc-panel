<?php

namespace App\Policies;

use App\Enums\AppraisalJobAssignmentStatus;
use App\Models\AppraisalJob;
use App\Models\User;
use App\Traits\Policy\HandlesBeforePolicyGate;
use App\Traits\Policy\NovaPolicyProvider;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Support\Facades\Log;

class AppraisalJobPolicy
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

    public function view(User $user, AppraisalJob $model)
    {
        return $user->hasManagementAccess()
            || $user->id === $model->appraiser_id
            || $user->id === $model->reviewer_id
            || $model->assignments()
                ->where('appraiser_id', $user->id)
                ->whereIn('status', [AppraisalJobAssignmentStatus::Pending, AppraisalJobAssignmentStatus::Accepted])
                ->exists();
    }

    public function update(User $user, AppraisalJob $model)
    {
        Log::info('AppraisalJobPolicy@update', [
            'user' => $user->id,
            'model' => $model->id,
        ]);
        return $user->hasManagementAccess()
            || $user->id === $model->appraiser_id
            || $model->assignments()
                ->where('appraiser_id', $user->id)
                ->whereIn('status', [AppraisalJobAssignmentStatus::Pending, AppraisalJobAssignmentStatus::Accepted])
                ->exists();
    }
}
