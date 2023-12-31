<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppraisalJobFile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'appraisal_job_id',
        'file',
        'comment',
    ];

    public function appraisalJob(): BelongsTo
    {
        return $this->belongsTo(AppraisalJob::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
