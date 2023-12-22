<?php

namespace App\Nova\Actions;

use App\Models\AppraisalJobRejection;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Konsulting\NovaActionButtons\ShowAsButton;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Http\Requests\NovaRequest;

class RejectAfterReview extends Action
{
    use InteractsWithQueue, Queueable, ShowAsButton;


    public function name()
    {
        return 'Reject';
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
        $model = $models->first();
        /** @var \App\Models\AppraisalJob $appraisalJob */
        $appraisalJob = \App\Models\AppraisalJob::query()->find($model->id);
        //check if the appraisal job is in review
        if ($appraisalJob->status != \App\Enums\AppraisalJobStatus::InReview->value) {
            return Action::danger('Appraisal job is not in review.');
        }

        //check if the user is a reviewer
        /** @var \App\Models\User $appraiser */
        $appraiser = $appraisalJob->appraiser;
        $reviewers = [];
        if ($appraiser && $appraiser->reviewers) {
            $reviewers = $appraiser->reviewers;
        }

        if (!in_array(auth()->user()->id, $reviewers)) {
            return Action::danger('You are not authorized to perform this action.');
        }

        DB::beginTransaction();
        try {
            $appraisalJob->setAttribute('status', \App\Enums\AppraisalJobStatus::InProgress)
                ->setAttribute('reviewed_at', \Carbon\Carbon::now())
                ->setAttribute('left_in_progress_at', null)
                ->setAttribute('reviewer_id', auth()->user()->id)
                ->save();

            AppraisalJobRejection::query()
                ->create([
                    'user_id' => auth()->user()->id,
                    'appraisal_job_id' => $appraisalJob->id,
                    'reviewer_id' => auth()->user()->id,
                    'reason' => $fields->reason,
                ]);
            DB::commit();
            return Action::message('Appraisal job has been rejected.');
        } catch (\Exception $e) {
            DB::rollBack();
            return Action::danger('An error occurred while rejecting the appraisal job. ' . $e->getMessage());
        }
    }

    /**
     * Get the fields available on the action.
     *
     * @param \Laravel\Nova\Http\Requests\NovaRequest $request
     * @return array
     */
    public function fields(NovaRequest $request)
    {
        return [
            \Laravel\Nova\Fields\Textarea::make('Reason', 'reason')
                ->rules('required'),
        ];
    }
}
