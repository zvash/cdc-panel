<?php

namespace App\Nova\Actions;

use App\Enums\AppraisalJobAssignmentStatus;
use App\Enums\AppraisalJobStatus;
use App\Models\AppraisalJob;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Konsulting\NovaActionButtons\ShowAsButton;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Http\Requests\NovaRequest;
use NormanHuth\NovaRadioField\Radio;

class RespondToAssignment extends Action
{
    use InteractsWithQueue, Queueable, ShowAsButton;

    /**
     * @var AppraisalJob $model
     */
    public $model;

    private $errorMessage = 'Cannot process your request.';

    public function name(): string
    {
        return 'Respond';
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
        $user = auth()->user();
        $jobId = $models->first()->id;
        $this->model = AppraisalJob::query()->find($jobId);
        Log::info('processable', [
            'isUserAssigned' => !$this->isUserAssigned($user),
            'isJobAccepted' => $this->isJobAccepted(),
            'isAppraisalJobOnHold' => $this->isAppraisalJobOnHold(),
            'notEnoughCapacity' => $this->notEnoughCapacity($fields, $user),
        ]);
        if (
            !$this->model
            || !$this->isUserAssigned($user)
            || $this->isJobAccepted()
            || $this->isAppraisalJobOnHold()
            || $this->notEnoughCapacity($fields, $user)
        ) {
            return Action::danger($this->errorMessage);
        }

        return $this->processResponse($fields, $user);
    }

    private function notEnoughCapacity($fields, $user)
    {
        if ($fields->response == 'decline') {
            return false;
        }
        if ($user->getRemainingCapacityAsInt() < 1) {
            $this->errorMessage = 'You have reached your maximum capacity.';
            return true;
        }
    }

    private function isAppraisalJobOnHold()
    {
        return $this->model->is_on_hold;
    }

    private function isUserAssigned($user)
    {
        return $this->model->assignments()
            ->where('appraiser_id', $user->id)
            ->exists();
    }

    private function isJobAccepted()
    {
        return $this->model->appraiser_id != null;
    }

    private function processResponse($fields, $user)
    {
        DB::beginTransaction();

        try {
            if ($fields->response == 'accept') {
                $this->acceptJob($user);
            }

            $this->updateAssignmentStatus($user, $fields);
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('error message', ['msg' => $e->getMessage()]);
            return Action::danger('An error occurred while processing your request.');
        }

        return Action::message('Response recorded.');
    }

    private function acceptJob($user)
    {
        $updated = DB::update(
            'UPDATE appraisal_jobs SET appraiser_id = ?, status = ?, accepted_at = NOW() WHERE id = ? AND appraiser_id IS NULL',
            [
                $user->id,
                AppraisalJobStatus::InProgress->value,
                $this->model->id
            ]);

        if ($updated == 0) {
            DB::rollBack();
            throw new \Exception('An appraiser has already accepted this job.');
        }

        $this->model->assignments()
            ->where('appraiser_id', '<>', $user->id)
            ->where('status', AppraisalJobAssignmentStatus::Pending)
            ->update([
                'status' => AppraisalJobAssignmentStatus::Missed
            ]);
    }

    private function updateAssignmentStatus($user, $fields)
    {
        $this->model->assignments()
            ->where('appraiser_id', $user->id)
            ->update([
                'status' => $fields->response == 'accept'
                    ? AppraisalJobAssignmentStatus::Accepted
                    : AppraisalJobAssignmentStatus::Declined
            ]);
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
            Radio::make('Response', 'response')
                ->options([
                    'accept' => 'Accept',
                    'decline' => 'Decline',
                ])
                ->radioHelpTexts([
                    'accept' => __('Appraisal job status will be set to "In Progress"'),
                    'decline' => __('This appraisal job will be removed from your list.'),
                ])
                ->required()
                ->addLabelStyles(['width' => '15rem']),
        ];
    }
}
