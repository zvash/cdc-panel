<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use \Illuminate\Database\Eloquent\Relations\BelongsTo;

class Client extends Model
{
    use HasFactory;

    protected $appends = [
        'complete_name',
    ];

    public function clientType(): BelongsTo
    {
        return $this->belongsTo(ClientType::class);
    }

    public function getCompleteNameAttribute(): string
    {
        return "{$this->company_name} ({$this->name})";
    }
}
