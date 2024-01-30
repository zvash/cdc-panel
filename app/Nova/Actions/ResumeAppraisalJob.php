<?php

namespace App\Nova\Actions;

use App\Enums\PauseResumeAction;
use App\Models\AppraisalJob;
use App\Models\AppraisalJobOnHoldHistory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Konsulting\NovaActionButtons\ShowAsButton;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Http\Requests\NovaRequest;

class ResumeAppraisalJob extends Action
{
    use InteractsWithQueue, Queueable, ShowAsButton;

    public function name()
    {
        return 'Release';
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
        if ($user->hasManagementAccess()) {
            DB::beginTransaction();
            try {
                $model->setAttribute('is_on_hold', false)
                    ->setAttribute('on_hold_until', null)
                    ->save();
                AppraisalJobOnHoldHistory::query()
                    ->create([
                        'appraisal_job_id' => $model->id,
                        'done_by' => $user->id,
                        'action' => PauseResumeAction::Resume->value,
                    ]);
                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();
                return Action::danger('Cannot process your request.');
            }
            return Action::message('Appraisal job has been released.');
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
