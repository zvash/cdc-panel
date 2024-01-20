<?php

namespace App\Observers;

use App\Enums\AppraisalJobAssignmentStatus;
use App\Models\AppraisalJob;
use App\Models\AppraisalJobAssignment;
use App\Models\AppraisalJobChangeLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AppraisalJobAssignmentObserver
{
    /**
     * Handle the AppraisalJobAssignment "updated" event.
     */
    public function saving(AppraisalJobAssignment $appraisalJobAssignment)
    {
        $user = auth()->user();
        if (
            $user->id == $appraisalJobAssignment->appraiser_id
            && $appraisalJobAssignment->isDirty('status')
            && $appraisalJobAssignment->getOriginal('status') == AppraisalJobAssignmentStatus::Pending->value
        ) {
            DB::beginTransaction();
            try {
                $this->handleChangeLog($appraisalJobAssignment);
                DB::commit();
            } catch (\Throwable $th) {
                DB::rollBack();
                return false;
            }
        }
    }

    private function handleChangeLog(AppraisalJobAssignment $appraisalJobAssignment)
    {
        $action = $this->determineAction($appraisalJobAssignment);
        $description = "Appraisal job was {$action} by {$this->getLinkToTheAuthenticatedUser()}.";
        if ($action == 'declined' && $appraisalJobAssignment->reject_reason) {
            $description .= " Reason: {$appraisalJobAssignment->reject_reason}";
        }
        AppraisalJobChangeLog::query()->create([
            'appraisal_job_id' => $appraisalJobAssignment->appraisalJob->id,
            'user_id' => auth()->user()->id,
            'action' => $action,
            'description' => $description,
            'duration' => \Carbon\Carbon::now()->diffInSeconds($appraisalJobAssignment->created_at),
        ]);
    }

    private function determineAction(AppraisalJobAssignment $appraisalJobAssignment)
    {
        if ($appraisalJobAssignment->status == AppraisalJobAssignmentStatus::Declined) {
            return 'declined';
        } else if ($appraisalJobAssignment->status == AppraisalJobAssignmentStatus::Accepted) {
            return 'accepted';
        }
        return 'missed';
    }

    private function getLinkToTheAuthenticatedUser(): string
    {
        $user = auth()->user();
        return "<a href='/resources/users/{$user->id}' target='_blank'>{$user->name}</a>";
    }

    private function getLatestChangeLog(AppraisalJobAssignment $appraisalJobAssignment)
    {
        return $appraisalJobAssignment->appraisalJob->changeLogs()->latest()->first();
    }

    private function updateLastChangeLog(?AppraisalJobChangeLog $latestLog)
    {
        $latestLog?->update([
            'duration' => \Carbon\Carbon::now()->diffInSeconds($latestLog->created_at),
        ]);
    }
}
