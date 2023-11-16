<?php

namespace App\Observers;

use App\Models\AppraisalJob;

class AppraisalJobObserver
{
    public function saving(AppraisalJob $appraisalJob)
    {
        if (!$appraisalJob->created_by) {
            $appraisalJob->created_by = auth()->user()->id;
        }
    }
}
