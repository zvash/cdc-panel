<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Office extends Model
{
    use HasFactory, SoftDeletes;

    public function getAllAppraisersWithRemainingCapacity()
    {
        return $this->users()
            ->withCount('appraisalJobs')
            ->whereHas('roles', function ($query) {
                $query->where('name', 'Appraiser');
            })
            ->get()
            ->filter(function ($user) {
                return $user->getRemainingCapacityAsInt() > 0;
            });
    }

    public function users(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(User::class);
    }
}
