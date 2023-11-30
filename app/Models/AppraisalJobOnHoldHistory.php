<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AppraisalJobOnHoldHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'appraisal_job_id',
        'done_by',
        'action',
        'reason',
    ];
}
