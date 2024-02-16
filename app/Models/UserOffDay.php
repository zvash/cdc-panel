<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserOffDay extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'off_date',
    ];

    protected $casts = [
        'off_date' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
