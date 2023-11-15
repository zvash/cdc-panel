<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Laravel\Nova\Actions\Actionable;
use Spatie\Permission\Models\Permission as Model;

class Permission extends Model
{
    use HasFactory, Actionable;
}
