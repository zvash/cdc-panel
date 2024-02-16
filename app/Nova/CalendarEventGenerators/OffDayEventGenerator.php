<?php

namespace App\Nova\CalendarEventGenerators;

use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Laravel\Nova\Resource as NovaResource;
use Wdelfuego\NovaCalendar\EventGenerator\Custom as CustomEventGenerator;
use Wdelfuego\NovaCalendar\Event;

class OffDayEventGenerator extends CustomEventGenerator
{

    protected function modelQuery(EloquentBuilder $queryBuilder, Carbon $startOfCalendar, Carbon $endOfCalendar): EloquentBuilder
    {
        $user = auth()?->user();
        if (!$user) {
            return $queryBuilder->where('id', 0);
        }
        $queryBuilder = $queryBuilder->where('off_date', '>=', $startOfCalendar)
            ->where('off_date', '<=', $endOfCalendar);
        if ($user->hasManagementAccess()) {
            return $queryBuilder;
        }
        return $queryBuilder->where('user_id', $user->id);
    }

    protected function resourceToEvents(NovaResource $resource, Carbon $startOfCalendar, Carbon $endOfCalendar): array
    {
        $events = [];
        /** @var \App\Models\UserOffDay $userOffDay */
        $userOffDay = $resource->model();
        $user = $userOffDay->user;
        $events[] = (new Event(
            "$user->name is off",
            $userOffDay->off_date
        ))
            ->withUrl('/resources/users/' . $user->id)
            ->addBadge('📅');

        return $events;
    }
}