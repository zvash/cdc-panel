<?php

namespace App\Jobs;

use App\Enums\AppraisalJobStatus;
use App\Enums\PauseResumeAction;
use App\Models\AppraisalJob;
use App\Models\AppraisalJobOnHoldHistory;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ResumeOnHoldAppraisalsIfTimeHasReached
{
    use Dispatchable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $jobs = AppraisalJob::query()
            ->where('is_on_hold', true)
            ->whereNotNull('on_hold_until')
            ->whereDate('on_hold_until', '<=', Carbon::now())
            ->get();

        foreach ($jobs as $job) {
            DB::beginTransaction();
            try {
                $jobId = $job->id;
                $createdBy = $job->created_by;
                $job->setAttribute('is_on_hold', false)
                    ->setAttribute('on_hold_until', null)
                    ->save();
                AppraisalJobOnHoldHistory::query()
                    ->create([
                        'appraisal_job_id' => $jobId,
                        'done_by' => $createdBy,
                        'action' => PauseResumeAction::Resume->value,
                    ]);
                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();
                Log::error($e->getMessage());
            }
        }
    }
}
