<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Pivot;

class AppraisalJobAssignment extends Pivot
{
    protected $table = 'appraisal_job_assignments';

    use HasFactory;
}
