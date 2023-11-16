<?php

namespace App\Traits\NovaResource;

use App\Models\Invitation;
use App\Models\JobFile;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Laravel\Nova\Http\Requests\NovaRequest;
use ReflectionClass;
use ReflectionException;

trait LimitsIndexQuery
{
    /**
     * Build an "index" query for the given resource.
     *
     * @param NovaRequest $request
     * @param Builder $query
     * @return Builder
     * @throws ReflectionException
     */
    public static function indexQuery(NovaRequest $request, $query): Builder
    {
        if ($request->user()->isSupervisor()) {
            return $query;
        }
        if (self::$model == User::class) {
            return self::handleUserModel($request, $query);
        } else if (self::$model == Invitation::class) {
            return self::handleInvitationModel($request, $query);
        } else if ($request->user()->isAppraiser()) {
            return self::handleAppraiser($request, $query);
        }
        return $query;
    }

    protected static function handleInvitationModel(NovaRequest $request, Builder $query): Builder
    {
        if ($request->user()->isSuperAdmin()) {
            return self::handleSuperAdminInvitations($query);
        } else if ($request->user()->isAdmin()) {
            return self::handleAdminInvitations($request, $query);
        } else {
            return $query->where('id', -1);
        }
    }

    protected static function handleSuperAdminInvitations(Builder $query): Builder
    {
        return $query;
    }

    protected static function handleAdminInvitations(NovaRequest $request, Builder $query): Builder
    {
        return $query->where('invited_by', $request->user()->id);
    }

    protected static function handleUserModel(NovaRequest $request, Builder $query): Builder
    {
        if ($request->user()->isSuperAdmin()) {
            return self::handleSuperAdmin($request, $query);
        } else if ($request->user()->isAdmin()) {
            return self::handleAdmin($request, $query);
        } else {
            return $query->where('id', $request->user()->id);
        }
    }

    protected static function handleSuperAdmin(NovaRequest $request, Builder $query): Builder
    {
        return $query->where('id', $request->user()->id)
            ->orWhere(
                function ($query) {
                    $query->whereHas('roles', function ($query) {
                        $query->where('name', 'Admin')->orWhere('name', 'Appraiser');
                    })->orWhereDoesntHave('roles');
                }
            );
    }

    protected static function handleAdmin(NovaRequest $request, Builder $query): Builder
    {
        return $query->where('id', $request->user()->id)
            ->orWhere(
                function ($query) {
                    $query->whereHas('roles', function ($query) {
                        $query->where('name', 'Appraiser');
                    })->orWhereDoesntHave('roles');
                }
            );
    }

    protected static function handleAppraiserJobFile(NovaRequest $request, Builder $query): Builder
    {
        return $query->whereHas('job', function ($query) use ($request) {
            $query->where('appraiser_id', $request->user()->id);
        });
    }

    protected static function handleAppraiser($request, $query)
    {
        if (self::$model == JobFile::class) {
            return self::handleAppraiserJobFile($request, $query);
        }
        try {
            $reflectionClass = new ReflectionClass(self::$model);
            if ($reflectionClass->hasMethod('appraiser')) {
                return $query->where('appraiser_id', $request->user()->id);
            }
            return $query;
        } catch (ReflectionException) {
            return $query;
        }
    }

}
