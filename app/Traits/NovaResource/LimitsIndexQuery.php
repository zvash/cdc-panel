<?php
namespace App\Traits\NovaResource;

use App\Models\User;
use Laravel\Nova\Http\Requests\NovaRequest;

trait LimitsIndexQuery
{
    /**
     * Build an "index" query for the given resource.
     *
     * @param \Laravel\Nova\Http\Requests\NovaRequest $request
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     * @throws \ReflectionException
     */
    public static function indexQuery(NovaRequest $request, $query)
    {
        if ($request->user()->isNurse()) {
            if (self::$model == User::class) {
                return $query->where('id', $request->user()->id);
            }
            $reflectionClass = new \ReflectionClass(self::$model);
            if ($reflectionClass->hasMethod('nurse'))
                return $query->where('nurse_id', $request->user()->id);
            else {
                return $query;
            }
        }
        return $query;
    }
}
