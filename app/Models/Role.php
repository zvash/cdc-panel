<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Nova\Actions\Actionable;

class Role extends Model
{
    use HasFactory, Actionable;

    public function __call($method, $parameters)
    {
        if (
            preg_match('/^is[A-Z][\w]*/', $method)
            && !method_exists($this, 'scope' . ucwords($method))
        ) {
            $roleName = preg_replace('/^is/', '', $method);
            return $this->name == $roleName;
        }
        return parent::__call($method, $parameters);
    }
}
