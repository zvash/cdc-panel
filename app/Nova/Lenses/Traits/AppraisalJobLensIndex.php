<?php

namespace App\Nova\Lenses\Traits;

use App\Nova\User;
use Laravel\Nova\Fields\Badge;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\Date;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Nova;

trait AppraisalJobLensIndex
{
    /**
     * Get the fields available to the lens.
     *
     * @param \Laravel\Nova\Http\Requests\NovaRequest $request
     * @return array
     */
    public function fields(NovaRequest $request)
    {
        return [
            ID::make(Nova::__('ID'), 'id')->sortable(),

            BelongsTo::make('Created By', 'createdBy', User::class)
                ->searchable()
                ->exceptOnForms()
                ->displayUsing(function ($user) {
                    return $user->name;
                }),

            BelongsTo::make('Client')
                ->searchable()
                ->showCreateRelationButton()
                ->modalSize('3xl')
                ->displayUsing(function ($client) {
                    return $client->complete_name;
                }),

            Select::make('Appraisal Type', 'appraisal_type_id')
                ->options(\App\Models\AppraisalType::pluck('name', 'id'))
                ->searchable()
                ->required()
                ->displayUsingLabels(),

            BelongsTo::make('Office')
                ->searchable()
                ->exceptOnForms(),

            BelongsTo::make('Appraiser', 'appraiser', User::class)
                ->searchable()
                ->exceptOnForms()
                ->displayUsing(function ($user) {
                    return $user->name;
                }),

            Badge::make('Status')->map([
                \App\Enums\AppraisalJobStatus::Pending->value => 'warning',
                \App\Enums\AppraisalJobStatus::InProgress->value => 'info',
                \App\Enums\AppraisalJobStatus::Completed->value => 'success',
                \App\Enums\AppraisalJobStatus::Cancelled->value => 'danger',
            ])->exceptOnForms(),

            Badge::make('On Hold?', 'is_on_hold')
                ->label(function ($isOnHold) {
                    return $isOnHold ? 'Yes' : 'No';
                })->map([
                    true => 'warning',
                    false => 'success',
                ])->withIcons()
                ->exceptOnForms(),

            Date::make('Due Date')
                ->sortable(),
        ];
    }
}
