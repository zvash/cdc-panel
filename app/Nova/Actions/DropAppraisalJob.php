<?php

namespace App\Nova\Actions;

use App\Models\AppraisalJob;
use App\Nova\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Collection;
use Konsulting\NovaActionButtons\ShowAsButton;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Http\Requests\NovaRequest;

class DropAppraisalJob extends Action
{
    use InteractsWithQueue, Queueable, ShowAsButton;

    public function name()
    {
        return 'Drop';
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
        $model = AppraisalJob::query()->find($models->first()->id);
        if ($model->appraiser_id == $user->id) {
            $model->setAttribute('appraiser_id', null);
            if (
                $user
                && $user->reviewers
                && is_array($user->reviewers)
                && count($user->reviewers)
                && $user->reviewers[0] * 1 == $model->inferReviewer()
            ) {
                $model->setAttribute('reviewer_id', null);
            }
            $model->save();
            return Action::message('Appraisal job has been dropped.');
        }
        return Action::danger('You are not allowed to drop this appraisal job.');
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
