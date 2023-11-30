<?php

namespace App\Nova\Actions;

use App\Enums\AppraisalJobStatus;
use App\Models\AppraisalJob;
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
        return 'Mark as Completed';
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
        if (
            $model->appraiser_id == $user->id
            && $model->status == AppraisalJobStatus::InProgress->value
            && !$model->is_on_hold
        ) {
            $model->setAttribute('status', AppraisalJobStatus::Completed)
                ->setAttribute('completed_at', Carbon::now())
                ->save();
            return Action::message('Appraisal job has been marked as completed.');
        }
        if ($model->is_on_hold) {
            return Action::danger('Appraisal job is on hold.');
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
