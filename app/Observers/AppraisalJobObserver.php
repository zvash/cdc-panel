<?php

namespace App\Observers;

use App\Enums\AppraisalJobStatus;
use App\Models\AppraisalJob;
use App\Models\AppraisalJobChangeLog;
use App\Models\AppraisalJobFile;
use App\Models\User;
use App\Notifications\JobAssignedNoAction;
use App\Notifications\JobAssignmentAccepted;
use App\Notifications\JobAssignmentDropped;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class AppraisalJobObserver
{
    public function saving(AppraisalJob $appraisalJob)
    {
        if (!$appraisalJob->created_by) {
            $appraisalJob->created_by = auth()->user()->id;
        }
        if ($appraisalJob->id) {
            $this->handleChangeLog($appraisalJob, false);
        }
    }

    public function created(AppraisalJob $appraisalJob)
    {
        $this->handleChangeLog($appraisalJob, true);
    }

    public function deleting(AppraisalJob $appraisalJob)
    {
        $appraisalJob->changeLogs()->delete();
        $appraisalJob->assignments()->delete();
        $appraisalJob->rejections()->delete();
        $appraisalJob->files()->delete();

    }

    private function handleAppraiserAssignment(AppraisalJob $appraisalJob, bool $saved = false)
    {
        $user = auth()->user();
        $changedFields = $this->getChangedFields($appraisalJob);
        if (array_key_exists('appraiser_id', $changedFields)) {
            if ($changedFields['appraiser_id']['old_value'] == null) {
                $appraisalJob->status = \App\Enums\AppraisalJobStatus::InProgress;
                if ($saved) {
                    $appraisalJob->saveQuietly();
                }
                //send assignment email
                if (
                    $changedFields['appraiser_id']['new_value']
                    && $user->hasManagementAccess()
                    && $user->id != $changedFields['appraiser_id']['new_value']
                ) {
                    $appraiser = User::query()->find($changedFields['appraiser_id']['new_value']);
                    $appraiser->notify(new JobAssignedNoAction($appraisalJob));
                }
            } else if ($changedFields['appraiser_id']['new_value'] == null) {
                $appraisalJob->status = \App\Enums\AppraisalJobStatus::Pending;
                if ($saved) {
                    $appraisalJob->saveQuietly();
                }

                //send rejection email
                if ($user->id == $changedFields['appraiser_id']['old_value']) {
                    $creator = User::query()->find($appraisalJob->created_by);
                    $creator->notify(new JobAssignmentDropped($appraisalJob, $user));
                }
            }
            if (User::query()->find(auth()->user()->id)->hasManagementAccess()) {
                $appraisalJob->assignments()
                    ->where('status', \App\Enums\AppraisalJobAssignmentStatus::Pending->value)
                    ->delete();
            }
        }
    }

    private function handleChangeLog(AppraisalJob $appraisalJob, bool $saved = true): void
    {
        $changedFields = $this->getChangedFields($appraisalJob);
        if (
            !array_key_exists('is_on_hold', $changedFields)
            && !array_key_exists('mail_sent_at', $changedFields)
            && !array_key_exists('appraiser_id', $changedFields)
            && !array_key_exists('status', $changedFields)
            && !array_key_exists('created_by', $changedFields)
        ) {
            return;
        }
        if (
            array_key_exists('status', $changedFields)
            && $changedFields['status']['new_value']->value == $changedFields['status']['old_value']
        ) {
            return;
        }

        $this->handleAppraiserAssignment($appraisalJob, $saved);
        $user = auth()->user();
        if (!$user) {
            $user = User::query()->find($appraisalJob->created_by);
        }
        /** @var \App\Models\AppraisalJobChangeLog $latestLog */
        $latestLog = $this->getLatestChangeLog($appraisalJob);
        $this->updateLastChangeLog($latestLog);
        $action = $this->determineAction($changedFields);
        $description = "Appraisal job was {$action} by {$this->getLinkToTheAuthenticatedUser($appraisalJob)}.";
        $changeLog = AppraisalJobChangeLog::query()->create([
            'appraisal_job_id' => $appraisalJob->id,
            'user_id' => $user->id,
            'action' => $action,
            'description' => $description,
        ]);
        if ($action == 'removed from hold') {
            $changeLog->update([
                'duration' => 0,
            ]);
            $secondLatestLog = $this->getLatestValidLogBeforePuttingTheJobOnHold($appraisalJob);
            AppraisalJobChangeLog::query()->create([
                'appraisal_job_id' => $appraisalJob->id,
                'user_id' => $user->id,
                'action' => $secondLatestLog->action,
                'description' => "Appraisal job went back to \"{$secondLatestLog->action}\" by {$this->getLinkToTheAuthenticatedUser($appraisalJob)}",
            ]);
        }
    }

    private
    function getChangedFields(AppraisalJob $appraisalJob): array
    {
        $changedFields = [];
        foreach ($appraisalJob->getDirty() as $field => $value) {
            $changedFields[$field] = [
                'old_value' => $appraisalJob->getOriginal($field),
                'new_value' => $value,
            ];
        }
        return $changedFields;
    }

    private
    function getLinkToTheAuthenticatedUser(AppraisalJob $appraisalJob): string
    {
        $user = auth()->user();
        if (!$user) {
            $user = User::query()->find($appraisalJob->created_by);
        }
        return "<a href='/resources/users/{$user->id}' target='_blank'>{$user->name}</a>";
    }

    private
    function getLatestChangeLog(AppraisalJob $appraisalJob)
    {
        return $appraisalJob->changeLogs()
            ->whereNotIn('action', ['accepted', 'declined'])
            ->latest()->first();
    }

    private function getLatestValidLogBeforePuttingTheJobOnHold(AppraisalJob $appraisalJob)
    {
        $putOnHoldId = $appraisalJob->changeLogs()->where('action', 'put on hold')->latest()->first()->id;
        return $appraisalJob->changeLogs()
            ->where('id', '<', $putOnHoldId)
            ->whereNotIn('action', ['accepted', 'declined'])
            ->latest()
            ->first();
    }

    private
    function updateLastChangeLog(?AppraisalJobChangeLog $latestLog)
    {
        $latestLog?->update([
            'duration' => \Carbon\Carbon::now()->diffInSeconds($latestLog->created_at),
        ]);
    }

    private
    function determineAction(array $changedFields): string
    {
        if (array_key_exists('is_on_hold', $changedFields)) {
            if ($changedFields['is_on_hold']['new_value']) {
                return 'put on hold';
            } else {
                return 'removed from hold';
            }
        }

        if (array_key_exists('mail_sent_at', $changedFields)) {
            return 'mailed to client';
        }

        if (
            !array_key_exists('status', $changedFields)
            || (
                array_key_exists('status', $changedFields)
                && $changedFields['status']['old_value'] != AppraisalJobStatus::Cancelled->value
            )
        ) {
            if (array_key_exists('appraiser_id', $changedFields)) {
                if ($changedFields['appraiser_id']['old_value'] == null) {
                    return 'assigned and put in progress';
                } else if ($changedFields['appraiser_id']['new_value'] == null) {
                    return 'sent back to pending when assignment removed';
                } else {
                    return 'reassigned';
                }
            }
        }

        if (array_key_exists('status', $changedFields)) {
            if ($changedFields['status']['new_value'] == \App\Enums\AppraisalJobStatus::Assigned) {
                return 'assigned';
            }
            if ($changedFields['status']['new_value'] == \App\Enums\AppraisalJobStatus::Pending) {
                if ($changedFields['status']['old_value'] == \App\Enums\AppraisalJobStatus::Assigned->value) {
                    return 'sent back to pending when rejected';
                }
                if ($changedFields['status']['old_value'] == \App\Enums\AppraisalJobStatus::Cancelled->value) {
                    return 'reinstated';
                }
            }
            if ($changedFields['status']['new_value'] == \App\Enums\AppraisalJobStatus::InProgress) {
                if ($changedFields['status']['old_value'] == \App\Enums\AppraisalJobStatus::Assigned->value) {
                    return 'put in progress';
                }
                return 'rejected after review';
            }
            if ($changedFields['status']['new_value'] == \App\Enums\AppraisalJobStatus::InReview) {
                return 'submitted for review';
            }
            if ($changedFields['status']['new_value'] == \App\Enums\AppraisalJobStatus::Completed) {
                return 'completed';
            }
            if ($changedFields['status']['new_value'] == \App\Enums\AppraisalJobStatus::Cancelled) {
                return 'cancelled';
            }
        }
        return 'created';
    }
}
