<?php

namespace App\Nova\Actions;

use App\Enums\AppraisalJobAssignmentStatus;
use App\Enums\AppraisalJobStatus;
use App\Models\AppraisalJob;
use App\Models\AppraisalJobAssignment;
use App\Models\Office;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Konsulting\NovaActionButtons\ShowAsButton;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\BooleanGroup;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;

class AssignAppraiserAction extends Action
{
    use InteractsWithQueue, Queueable, ShowAsButton;

    /**
     * @var AppraisalJob $model
     */
    public $model;

    public function name(): string
    {
        return 'Assign';
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
        $this->model = AppraisalJob::query()->find($models->first()->id);
        if ($this->model->appraiser_id != null) {
            return Action::danger('An appraiser has already accepted this job.');
        }
        $selectedAppraisers = collect($fields->appraisers)->filter(fn($item) => $item == true)->keys();
        AppraisalJobAssignment::query()->where('appraisal_job_id', $this->model->id)
            ->whereNotIn('appraiser_id', $selectedAppraisers)
            ->delete();
        AppraisalJobAssignment::query()
            ->upsert($selectedAppraisers->map(fn($appraiserId) => [
                'appraisal_job_id' => $this->model->id,
                'appraiser_id' => $appraiserId,
                'assigned_by' => auth()->user()->id,
                'status' => AppraisalJobAssignmentStatus::Pending
            ])->toArray(), ['appraisal_job_id', 'appraiser_id'], ['status']);

//        $selectedAppraisers->each(function ($appraiserId) {
//            $assignment = AppraisalJobAssignment::query()
//                ->where('appraisal_job_id', $this->model->id)
//                ->where('appraiser_id', $appraiserId)
//                ->first();
//            if ($assignment) {
//                $assignment->setAttribute('status', AppraisalJobAssignmentStatus::Pending)->save();
//            } else {
//                AppraisalJobAssignment::query()->create([
//                    'appraisal_job_id' => $this->model->id,
//                    'appraiser_id' => $appraiserId,
//                    'assigned_by' => auth()->user()->id,
//                    'status' => AppraisalJobAssignmentStatus::Pending
//                ]);
//            }
//        });

        $this->model->setAttribute('status', AppraisalJobStatus::Assigned)->save();
        return Action::message("job assignment updated successfully based on your selection.");
    }

    /**
     * Get the fields available on the action.
     *
     * @param \Laravel\Nova\Http\Requests\NovaRequest $request
     * @return array
     */
    public function fields(NovaRequest $request)
    {
        $appraisers = [];
        $offeredTo = [];
        if ($this->model && $this->model->office != null) {
            //$appraisers = $this->model->office->getAllAppraisersWithRemainingCapacity()->pluck('name', 'id')->toArray();
            $appraisers = User::getAllAppraisersWithRemainingCapacity()->pluck('name', 'id')->toArray();
            $offeredTo = AppraisalJobAssignment::query()
                ->where('appraisal_job_id', $this->model->id)
                ->pluck('appraisal_job_id', 'appraiser_id')
                ->map(fn($item) => true)
                ->toArray();
        }
        return [
            BooleanGroup::make('Appraisers', 'appraisers')
                ->options($appraisers)
                ->default($offeredTo)
        ];
    }
}
