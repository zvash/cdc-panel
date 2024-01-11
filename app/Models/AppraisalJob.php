<?php

namespace App\Models;

use App\Enums\AppraisalJobStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;
use Spatie\MediaLibrary\Conversions\Conversion;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\FileAdder;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * @method void prepareToAttachMedia(Media $media, FileAdder $fileAdder)
 */
class AppraisalJob extends Model implements HasMedia
{
    use InteractsWithMedia;
    use HasFactory;

    protected $casts = [
        'due_date' => 'date',
        'completed_at' => 'datetime',
    ];

    protected $appends = [
        'progress',
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

    public function rejections(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(AppraisalJobRejection::class, 'appraisal_job_id');
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

    public function changeLogs(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(AppraisalJobChangeLog::class);
    }

    public function nextValidStatus(): AppraisalJobStatus
    {
        if ($this->status == AppraisalJobStatus::Pending->value) {
            return AppraisalJobStatus::Assigned;
        }
        if ($this->status == AppraisalJobStatus::Assigned->value) {
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

    public function getProgressAttribute(): float|int
    {
        $progress = 0;
        if ($this->status == AppraisalJobStatus::Assigned->value) {
            $progress = 0.33;
        } else if ($this->status == AppraisalJobStatus::InProgress->value) {
            $progress = 0.66;
        } else if ($this->status == AppraisalJobStatus::InReview->value) {
            $progress = 0.66;
        } else if ($this->status == AppraisalJobStatus::Completed->value) {
            $progress = 1;
        }
        return $progress;
    }
}
