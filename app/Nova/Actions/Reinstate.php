<?php

namespace App\Nova\Actions;

use App\Models\AppraisalJob;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Collection;
use Konsulting\NovaActionButtons\ShowAsButton;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Http\Requests\NovaRequest;

class Reinstate extends Action
{
    use InteractsWithQueue, Queueable, ShowAsButton;

    public function name()
    {
        return 'Reinstate';
    }

    /**
     * Perform the action on the given models.
     *
     * @param \Laravel\Nova\Fields\ActionFields $fields
     * @param \Illuminate\Support\Collection $models
     * @return mixed
     */
    public function handle(ActionFields $fields, Collection $models)
    {
        $user = auth()->user();
        $user = \App\Models\User::query()->find($user->id);
        if (!$user->hasManagementAccess()) {
            return Action::danger('You are not allowed to cancel this appraisal job.');
        }
        $model = AppraisalJob::query()->find($models->first()->id);
        $model->setAttribute('status', \App\Enums\AppraisalJobStatus::Pending)
            ->setAttribute('appraiser_id', null)
            ->setAttribute('reviewer_id', null)
            ->setAttribute('accepted_at', null)
            ->setAttribute('left_in_progress_at', null)
            ->setAttribute('reviewed_at', null)
            ->setAttribute('completed_at', null)
            ->setAttribute('completed_at', null)
            ->save();
        $model->assignments()->delete();
        return Action::message('Appraisal job has been reinstated. You can now assign an appraiser.');
    }

    /**
     * Get the fields available on the action.
     *
     * @param \Laravel\Nova\Http\Requests\NovaRequest $request
     * @return array
     */
    public function fields(NovaRequest $request)
    {
        return [];
    }
}
