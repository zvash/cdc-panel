<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles;

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
    ];

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

    private function appraisalJobs(): HasMany
    {
        return $this->hasMany(AppraisalJob::class, 'appraiser_id');
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

    public function getRemainingCapacityAttribute(): string
    {
        if ($this->isAppraiser()) {
            $remainingCapacity = $this->capacity - $this->appraisalJobs()->count();
            return "Remaining Capacity: {$remainingCapacity}";
        }
        return "Remaining Capacity: N/A";
    }
}
