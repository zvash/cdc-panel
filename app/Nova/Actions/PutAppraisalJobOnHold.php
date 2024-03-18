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
use Illuminate\Support\Facades\Log;
use Konsulting\NovaActionButtons\ShowAsButton;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\Date;
use Laravel\Nova\Http\Requests\NovaRequest;

class PutAppraisalJobOnHold extends Action
{
    use InteractsWithQueue, Queueable, ShowAsButton;

    /**
     * @var AppraisalJob $model
     */
    public $model;

    public function name()
    {
        return 'Hold';
    }

    public function setModel($model): static
    {
        $this->model = $model;
        return $this;
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
        $model = AppraisalJob::query()->find($models->first()->id);
        $user = auth()->user();
        if ($user && ($user->hasManagementAccess() || $user->id == $model->appraiser_id)) {
            DB::beginTransaction();
            try {
                $model->setAttribute('is_on_hold', true);
                if ($fields->on_hold_until) {
                    $model->setAttribute('on_hold_until', $fields->on_hold_until);
                }
                $model->save();
                AppraisalJobOnHoldHistory::query()
                    ->create([
                        'appraisal_job_id' => $model->id,
                        'done_by' => $user->id,
                        'action' => PauseResumeAction::Pause->value,
                    ]);
                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();
                return Action::danger('Cannot process your request.');
            }

            return Action::message('Appraisal job has been put on hold.');
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
        return [
            Date::make('On Hold Until', 'on_hold_until')
                ->nullable()
                ->rules('nullable', 'after:today')
                ->default($this->model?->on_hold_until),
        ];
    }
}
