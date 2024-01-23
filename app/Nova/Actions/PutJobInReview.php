<?php

namespace App\Nova\Actions;

use App\Enums\AppraisalJobStatus;
use App\Models\AppraisalJob;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Collection;
use Konsulting\NovaActionButtons\ShowAsButton;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Http\Requests\NovaRequest;

class PutJobInReview extends Action
{
    use InteractsWithQueue, Queueable, ShowAsButton;

    public function name()
    {
        return 'Submit to Reviewer';
    }

    /**
     * Perform the action on the given models.
     *
     * @param  \Laravel\Nova\Fields\ActionFields  $fields
     * @param  \Illuminate\Support\Collection  $models
     * @return mixed
     */
    public function handle(ActionFields $fields, Collection $models)
    {
        $user = auth()->user();
        $model = AppraisalJob::query()->find($models->first()->id);
        $canSendToReview = $model->appraiser_id == $user->id
            && $model->inferReviewer()
            && $model->status == AppraisalJobStatus::InProgress->value;

        if (!$model->is_on_hold && $canSendToReview) {
            $model->setAttribute('status', AppraisalJobStatus::InReview)
                ->setAttribute('left_in_progress_at', Carbon::now())
                ->save();

            //TODO: Notify reviewers
            return Action::message('Appraisal job has been sent to reviewers.');
        }

        if ($model->is_on_hold) {
            return Action::danger('Appraisal job is on hold.');
        }

        return Action::danger('You are not authorized to perform this action.');
    }

    /**
     * Get the fields available on the action.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return array
     */
    public function fields(NovaRequest $request)
    {
        return [];
    }
}
