<?php

namespace App\Models;

use App\Enums\AppraisalJobStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AppraisalJob extends Model
{
    use HasFactory;

    protected $casts = [
        'due_date' => 'date',
    ];

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function appraisalType()
    {
        return $this->belongsTo(AppraisalType::class);
    }

    public function appraiser()
    {
        return $this->belongsTo(User::class, 'appraiser_id');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function assignments(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(AppraisalJobAssignment::class);
    }

    /**
     * Get the office that owns the AppraisalJob
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function office(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Office::class);
    }

    public function files(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(AppraisalJobFile::class);
    }

    public function nextValidStatus(): AppraisalJobStatus
    {
        if ($this->status == AppraisalJobStatus::Pending->value) {
            return AppraisalJobStatus::InProgress;
        }
        if ($this->status == AppraisalJobStatus::InProgress->value) {
            if ($this->appraiser_id && User::query()->find($this->appraiser_id)->reviewers) {
                return AppraisalJobStatus::InReview;
            }
            return AppraisalJobStatus::Completed;
        }
        if ($this->status == AppraisalJobStatus::InReview->value) {
            return AppraisalJobStatus::Completed;
        }
        return AppraisalJobStatus::Pending;
    }
}
