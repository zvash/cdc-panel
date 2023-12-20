<?php

namespace App\Nova\Actions;

use App\Enums\AppraisalJobStatus;
use App\Models\AppraisalJob;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Konsulting\NovaActionButtons\ShowAsButton;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Http\Requests\NovaRequest;

class MarkAsCompleted extends Action
{
    use InteractsWithQueue, Queueable, ShowAsButton;

    public function name()
    {
        return 'Complete';
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
        $model = AppraisalJob::query()->find($models->first()->id);
        $canMarkAsCompletedByAppraiser = $model->appraiser_id == $user->id
            && !$user->reviewers
            && $model->status == AppraisalJobStatus::InProgress->value;

        $appraiser = User::query()->find($model->appraiser_id);
        $canMarkAsCompletedByReviewer = $appraiser && $appraiser->reviewers
            && in_array($user->id, $appraiser->reviewers)
            && $model->status == AppraisalJobStatus::InReview->value;
        $canMarkAsCompleted = $canMarkAsCompletedByAppraiser || $canMarkAsCompletedByReviewer;
        if (!$model->is_on_hold && $canMarkAsCompleted) {
            $model->setAttribute('status', AppraisalJobStatus::Completed)
                ->setAttribute('completed_at', Carbon::now());
            if ($canMarkAsCompletedByAppraiser) {
                $model->setAttribute('left_in_progress_at', Carbon::now());
            } else if ($canMarkAsCompletedByReviewer) {
                $model->setAttribute('reviewed_at', Carbon::now())
                    ->setAttribute('reviewer_id', $user->id);
            }
            $model->save();
            //TODO: Notify stakeholders
            return Action::message('Appraisal job has been marked as completed.');
        }
        if ($model->is_on_hold) {
            return Action::danger('Appraisal job is on hold.');
        }

        if ($appraiser && $appraiser->reviewers && $model->status == AppraisalJobStatus::InProgress->value) {
            return Action::danger('Job needs to be reviewed first.');
        }

        return Action::danger('You are not authorized to perform this action.');
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
