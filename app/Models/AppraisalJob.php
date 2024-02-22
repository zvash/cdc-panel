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
        'appraiser_paid_at' => 'datetime',
        'reviewer_paid_at' => 'datetime',
        'admin_paid_at' => 'datetime',
    ];

    protected $appends = [
        'progress',
        'admin_fee_tax',
        'admin_fee_total',
        'appraiser_fee',
        'appraiser_fee_tax',
        'appraiser_fee_total',
        'reviewer_fee',
        'reviewer_fee_tax',
        'reviewer_fee_total',
    ];

    private $taxRate = null;

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

    public function inferReviewer()
    {
        if ($this->reviewer_id) {
            return $this->reviewer_id;
        }
        if ($this->appraiser_id) {
            $appraiser = User::query()->find($this->appraiser_id);
            if ($appraiser && $appraiser->reviewers && is_array($appraiser->reviewers) && count($appraiser->reviewers)) {
                return $appraiser->reviewers[0] * 1;
            }
        }
        return null;
    }

    public function inferCommission()
    {
        if ($this->commission !== null) {
            return $this->commission;
        }
        if ($this->appraiser_id) {
            $appraiser = User::query()->find($this->appraiser_id);
            if ($appraiser && $appraiser->commission) {
                return $appraiser->commission;
            }
        }
        return 0;
    }

    public function inferReviewerCommission()
    {
        if ($this->reviewer_commission !== null) {
            return $this->reviewer_commission;
        }
        $reviewerId = $this->inferReviewer();
        if ($reviewerId) {
            $reviewer = User::query()->find($this->reviewer_id);
            if ($reviewer && $reviewer->reviewer_commission) {
                return $reviewer->reviewer_commission;
            }
        }
        return 0;
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
            if ($this->reviewer_id || ($this->appraiser_id && User::query()->find($this->appraiser_id)?->reviewers)) {
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

    private function calculateFeePortion($fee, $commission): float
    {
        if (!$fee || !$commission) {
            return 0;
        }
        return round($fee * ($commission / 100), 3);
    }

    private function calculateTaxPortion($fee, $taxRate): float
    {
        if (!$fee || !$taxRate) {
            return 0;
        }
        return round($fee * ($taxRate / 100), 3);
    }

    private function calculateTotal($fee, $commission, $tax): float
    {
        $feePortion = $this->calculateFeePortion($fee, $commission);
        $taxPortion = $this->calculateTaxPortion($fee, $tax);
        return round($feePortion + $taxPortion, 3);
    }

    private function fetchTaxRate()
    {
        if ($this->taxRate !== null) {
            return $this->taxRate;
        }
        if ($this->province) {
            $provinceTax = ProvinceTax::query()->whereHas('province', function ($query) {
                $query->where('name', $this->province);
            })->first();
            $this->taxRate = $provinceTax?->total;
        }
        return $this->taxRate ?? 0;
    }
    public function getAdminFeeTaxAttribute(): float
    {
        return $this->calculateTaxPortion($this->admin_fee, $this->fetchTaxRate());
    }

    public function getAdminFeeTotalAttribute(): float
    {
        return $this->calculateTotal($this->admin_fee, 100, $this->fetchTaxRate());
    }

    public function getAppraiserFeeAttribute(): float
    {
        return $this->calculateFeePortion($this->fee_quoted, $this->inferCommission());
    }

    public function getAppraiserFeeTaxAttribute(): float
    {
        return $this->calculateTaxPortion($this->fee_quoted, $this->fetchTaxRate());
    }

    public function getAppraiserFeeTotalAttribute(): float
    {
        return $this->calculateTotal($this->fee_quoted, $this->inferCommission(), $this->fetchTaxRate());
    }

    public function getReviewerFeeAttribute(): float
    {
        return $this->calculateFeePortion($this->fee_quoted, $this->inferReviewerCommission());
    }

    public function getReviewerFeeTaxAttribute(): float
    {
        return $this->calculateTaxPortion($this->fee_quoted, $this->fetchTaxRate());
    }

    public function getReviewerFeeTotalAttribute(): float
    {
        return $this->calculateTotal($this->fee_quoted, $this->inferReviewerCommission(), $this->fetchTaxRate());
    }
}
