<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProvinceTax extends Model
{
    use HasFactory;

    protected $fillable = [
        'province_id',
        'gst',
        'hst',
        'pst',
        'qst',
    ];

    public function province()
    {
        return $this->belongsTo(Province::class);
    }
}
