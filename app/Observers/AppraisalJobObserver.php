<?php

namespace App\Observers;

use App\Models\AppraisalJob;
use App\Models\AppraisalJobChangeLog;
use App\Models\AppraisalJobFile;

class AppraisalJobObserver
{
    public function saving(AppraisalJob $appraisalJob)
    {
        if (!$appraisalJob->created_by) {
            $appraisalJob->created_by = auth()->user()->id;
        }
        if ($appraisalJob->id) {
            $this->handleChangeLog($appraisalJob);
        }
    }

    public function created(AppraisalJob $appraisalJob)
    {
        $this->handleChangeLog($appraisalJob);
    }

    private function handleChangeLog(AppraisalJob $appraisalJob): void
    {
        $changedFields = $this->getChangedFields($appraisalJob);
        if (
            !array_key_exists('is_on_hold', $changedFields)
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
        /** @var \App\Models\AppraisalJobChangeLog $latestLog */
        $latestLog = $this->getLatestChangeLog($appraisalJob);
        $this->updateLastChangeLog($latestLog);
        $action = $this->determineAction($changedFields);
        $description = "Appraisal job was {$action} by {$this->getLinkToTheAuthenticatedUser()}.";
        $changeLog = AppraisalJobChangeLog::query()->create([
            'appraisal_job_id' => $appraisalJob->id,
            'user_id' => auth()->user()->id,
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
                'user_id' => auth()->user()->id,
                'action' => $secondLatestLog->action,
                'description' => "Appraisal job went back to \"{$secondLatestLog->action}\" by {$this->getLinkToTheAuthenticatedUser()}",
            ]);
        }

    }

    private function getChangedFields(AppraisalJob $appraisalJob): array
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

    private function getLinkToTheAuthenticatedUser(): string
    {
        $user = auth()->user();
        return "<a href='/resources/users/{$user->id}' target='_blank'>{$user->name}</a>";
    }

    private function getLatestChangeLog(AppraisalJob $appraisalJob)
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

    private function updateLastChangeLog(?AppraisalJobChangeLog $latestLog)
    {
        $latestLog?->update([
            'duration' => \Carbon\Carbon::now()->diffInSeconds($latestLog->created_at),
        ]);
    }

    private function determineAction(array $changedFields): string
    {
        if (array_key_exists('is_on_hold', $changedFields)) {
            if ($changedFields['is_on_hold']['new_value']) {
                return 'put on hold';
            } else {
                return 'removed from hold';
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
        }
        return 'created';
    }
}
