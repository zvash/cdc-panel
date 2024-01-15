<?php

namespace App\Nova\Actions;

use App\Enums\AppraisalJobAssignmentStatus;
use App\Enums\AppraisalJobStatus;
use App\Models\AppraisalJob;
use App\Models\User;
use App\Notifications\JobAssignmentAccepted;
use App\Notifications\JobAssignmentRejected;
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

    private $source = 'detail';

    public function __construct()
    {
        $request = request()->all();
        if (isset($request['display'])) {
            $this->source = $request['display'];
        }
    }

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
            $this->putJobBackToPendingIfNeeded($fields);
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return Action::danger('An error occurred while processing your request.');
        }

        if ($fields->response == 'decline') {
            $creator = User::query()->find($this->model->created_by);
            $creator->notify(new JobAssignmentRejected($this->model, $user));
        }

        if ($fields->response == 'decline' && $this->source == 'detail') {
            return Action::redirect('/resources/appraisal-jobs/');
        }
        return Action::message('Response recorded.');
    }

    private function putJobBackToPendingIfNeeded($fields)
    {
        if ($fields->response == 'decline') {
            if ($this->model->assignments()
                ->where('status', AppraisalJobAssignmentStatus::Pending)->exists()) {
                return;
            } else {
                $this->model->setAttribute('status', AppraisalJobStatus::Pending)->save();
            }
        }
    }

    private function acceptJob($user)
    {
        $job = AppraisalJob::query()
            ->where('id', $this->model->id)
            ->whereNull('appraiser_id')
            ->first();

        if (!$job) {
            DB::rollBack();
            throw new \Exception('An appraiser has already accepted this job.');
        }

        $job->setAttribute('appraiser_id', $user->id)
            ->setAttribute('status', AppraisalJobStatus::InProgress)
            ->setAttribute('accepted_at', now())
            ->save();

        $this->model->assignments()
            ->where('appraiser_id', '<>', $user->id)
            ->where('status', AppraisalJobAssignmentStatus::Pending)
            ->get()
            ->each(function ($assignment) {
                $assignment->setAttribute('status', AppraisalJobAssignmentStatus::Missed)->save();
            });

        $creator = User::query()->find($job->created_by);
        $creator->notify(new JobAssignmentAccepted($job, $user));
    }

    private function updateAssignmentStatus($user, $fields)
    {
        $status = $fields->response == 'accept'
            ? AppraisalJobAssignmentStatus::Accepted
            : AppraisalJobAssignmentStatus::Declined;
        $assignment = $this->model->assignments()
            ->where('appraiser_id', $user->id)
            ->first();
        if ($assignment) {
            $assignment->setAttribute('status', $status)->save();
        } else {
            throw new \Exception('Cannot find the assignment.');
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
