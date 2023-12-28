<?php

namespace App\Nova\Lenses\Traits;

use App\Nova\Filters\OfficeFilter;
use App\Nova\User;
use Flatroy\FieldProgressbar\FieldProgressbar;
use Laravel\Nova\Fields\Badge;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\Date;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Line;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Stack;
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

            Stack::make('Address', [
                Line::make('Property Address')->asHeading(),
                Line::make('Property Postal Code')->asSmall(),
                Line::make('Property City')->asSmall(),
                Line::make('Property Province')->asSmall(),
            ])->onlyOnIndex(),

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
                \App\Enums\AppraisalJobStatus::Pending->value => 'danger',
                \App\Enums\AppraisalJobStatus::Assigned->value => 'warning',
                \App\Enums\AppraisalJobStatus::InProgress->value => 'info',
                \App\Enums\AppraisalJobStatus::InReview->value => 'warning',
                \App\Enums\AppraisalJobStatus::Completed->value => 'success',
                \App\Enums\AppraisalJobStatus::Cancelled->value => 'danger',
                'On Hold' => 'warning',
            ])
                ->resolveUsing(function ($status) {
                    if ($this->is_on_hold) {
                        return 'On Hold';
                    }
                    return $status;
                })
                ->withIcons()
                ->exceptOnForms(),

            FieldProgressbar::make('Progress')
                ->options([
                    'color' => '#40BF55',
                    'fromColor' => '#FFEA82',
                    'toColor' => '#40BF55',
                    'animationColor' => false,
                ])
                ->exceptOnForms(),

            Date::make('Due Date')
                ->sortable(),
        ];
    }

    /**
     * Get the filters available for the resource.
     *
     * @param \Laravel\Nova\Http\Requests\NovaRequest $request
     * @return array
     */
    public function filters(NovaRequest $request)
    {
        return [
            (new OfficeFilter())
                ->canSee(function () use ($request) {
                    return $request->user()->hasManagementAccess();
                }),
        ];
    }
}
