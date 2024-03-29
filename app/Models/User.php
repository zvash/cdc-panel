<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Notifications\PasswordReset;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $appends = [
        'remaining_capacity',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'preferred_appraisal_types' => 'array',
        'reviewers' => 'array'
    ];

    public static function getAllAppraisersWithRemainingCapacity()
    {
        return User::query()
            ->withCount('appraisalJobs')
            ->whereHas('roles', function ($query) {
                $query->where('name', 'Appraiser');
            })
            ->get()
            ->filter(function ($user) {
                return $user->getRemainingCapacityAsInt() > 0;
            });
    }

    public static function getAvailableAppraisersWithRemainingCapacity()
    {
        $today = now()->format('Y-m-d');
        return User::query()
            ->withCount('appraisalJobs')
            ->whereHas('roles', function ($query) {
                $query->where('name', 'Appraiser');
            })
            ->whereDoesntHave('offDays', function ($query) use ($today) {
                $query->whereDate('off_date', $today);
            })
            ->get()
            ->filter(function ($user) {
                return $user->getRemainingCapacityAsInt() > 0;
            });
    }

    public function __call($method, $parameters)
    {
        if (
            preg_match('/^is[A-Z][\w]*/', $method)
            && !method_exists($this, 'scope' . ucwords($method))
        ) {
            return $this->roleDetection($method);
        }
        return parent::__call($method, $parameters);
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(Invitation::class, 'invited_by');
    }

    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class, 'office_id');
    }

    public function appraisalJobs(): HasMany
    {
        return $this->hasMany(AppraisalJob::class, 'appraiser_id');
    }

    public function changeLogs(): HasMany
    {
        return $this->hasMany(AppraisalJobChangeLog::class);
    }

    public function offDays(): HasMany
    {
        return $this->hasMany(UserOffDay::class, 'user_id');
    }

    /**
     * Detect user has specific role or not?
     *
     * @param string $method
     * @return bool
     */
    private function roleDetection(string $method): bool
    {
        return $this->roles->filter(function ($role) use ($method) {
            return $role->{$method}();
        })->isNotEmpty();
    }

    public function hasManagementAccess()
    {
        return $this->isSupervisor()
            || $this->isSuperAdmin()
            || $this->isAdmin();
    }

    public function getRemainingCapacityAttribute(): string
    {
        if ($this->isAppraiser()) {
            $jobsInHandCount = $this->appraisalJobs()
                ->where('is_on_hold', false)
                ->where('status', \App\Enums\AppraisalJobStatus::InProgress)->count();
            $remainingCapacity = $this->capacity - $jobsInHandCount;
            return "Remaining Capacity: {$remainingCapacity}";
        }
        return "Remaining Capacity: N/A";
    }

    public function getRemainingCapacityAsInt(): int
    {
        $jobsInHandCount = $this->appraisalJobs()
            ->where('is_on_hold', false)
            ->where('status', \App\Enums\AppraisalJobStatus::InProgress)->count();
        return $this->capacity - $jobsInHandCount;
    }

    public function sendPasswordResetNotification($token)
    {
        $this->notify(new PasswordReset($token, $this));
    }
}
