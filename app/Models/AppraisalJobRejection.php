<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AppraisalJobRejection extends Model
{
    use HasFactory;

    protected $fillable = [
        'appraisal_job_id',
        'user_id',
        'reason',
        'file',
    ];

    public function appraisalJob()
    {
        return $this->belongsTo(AppraisalJob::class);
    }

    public function rejectedBy()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
