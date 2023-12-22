<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AppraisalJobChangeLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'appraisal_job_id',
        'user_id',
        'action',
        'duration',
        'description',
    ];

    public function appraisalJob()
    {
        return $this->belongsTo(AppraisalJob::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
