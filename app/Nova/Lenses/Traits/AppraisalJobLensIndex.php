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
use Laravel\Nova\Fields\Text;
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

//            BelongsTo::make('Created By', 'createdBy', User::class)
//                ->searchable()
//                ->exceptOnForms()
//                ->displayUsing(function ($user) {
//                    return $user->name;
//                }),


            BelongsTo::make('Client')
                ->searchable()
                ->showCreateRelationButton()
                ->filterable()
                ->modalSize('3xl')
                ->displayUsing(function ($client) {
                    return $client->complete_name;
                }),

            Stack::make('Details', [
                Line::make('Property Address')
                    ->displayUsing(function ($value) {
                        return str_ireplace(', Canada', '', $value);
                    })->asHeading(),
                Line::make('Appraisal Type', 'appraisalType.name')
                    ->displayUsing(function ($value) {
                        return 'Type: ' . ($value ?? '-');
                    })->asSmall(),
                Line::make('File Number', 'reference_number')
                    ->displayUsing(function ($value) {
                        return 'File Number: ' . ($value ?? '-');
                    })->filterable()
                    ->asSmall(),
                Line::make('Due Date')
                    ->displayUsing(function ($value) {
                        return 'Due Date: ' . ($value ?? '-');
                    })->asSmall(),
            ])->onlyOnIndex(),

            Select::make('Appraisal Type', 'appraisal_type_id')
                ->options(\App\Models\AppraisalType::pluck('name', 'id'))
                ->searchable()
                ->hideFromIndex()
                ->required()
                ->displayUsingLabels(),

            BelongsTo::make('Appraisal Type', 'appraisalType', \App\Nova\AppraisalType::class)
                ->searchable()
                ->exceptOnForms()
                ->hideFromIndex()
                ->hideFromDetail()
                ->filterable()
                ->displayUsing(function ($appraisalType) {
                    return $appraisalType->name;
                }),

            BelongsTo::make('Office')
                ->searchable()
                ->filterable()
                ->exceptOnForms(),

            BelongsTo::make('Appraiser', 'appraiser', User::class)
                ->searchable()
                ->exceptOnForms()
                ->filterable()
                ->displayUsing(function ($user) {
                    return $user->name;
                }),

            Text::make('Reviewer', 'appraiser_id')
                ->exceptOnForms()
                ->displayUsing(function ($value) {
                    if (!$value) {
                        return '-';
                    }
                    $reviewer = null;
                    if ($this->reviewer_id) {
                        $reviewer = \App\Models\User::query()->find($this->reviewer_id);

                    } else {
                        $reviewers = \App\Models\User::query()->find($value)->reviewers;
                        if ($reviewers && count($reviewers) > 0) {
                            $reviewer = \App\Models\User::query()->find($reviewers[0]);
                        }
                    }
                    if ($reviewer) {
                        return "<a href='/resources/users/{$reviewer->id}' class='link-default'>{$reviewer->name}</a>";
                    }
                    return '-';
                })->asHtml(),

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
//                    'color' => '#40BF55',
                    'fromColor' => '#FFEA82',
                    'toColor' => '#40BF55',
                    'animateColor' => false,
                ])
                ->exceptOnForms()
                ->hideFromIndex(),

            Date::make('Due Date')
                ->hideFromIndex()
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
