<?php

namespace App\Nova\Lenses\Traits;

use App\Enums\AppraisalJobStatus;
use App\Models\AppraisalType;
use App\Nova\Filters\OfficeFilter;
use App\Nova\User;
use Flatroy\FieldProgressbar\FieldProgressbar;
use Illuminate\Support\Str;
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
            ID::make(Nova::__('ID'), 'id')->sortable()
                ->displayUsing(function ($value) {
                    if (
                        $this->resource->status != AppraisalJobStatus::Completed->value
                        && $this->resource->status != AppraisalJobStatus::Cancelled->value
                        && $this->resource->due_date
                        && $this->resource->due_date->isPast()
                    ) {
                        return $value . ' ❗️';
                    }
                    return $value;
                }),

            Stack::make('Details', [
                Line::make('Property Address')
                    ->displayUsing(function ($value) {
                        return Str::limit(str_ireplace(', Canada', '', $value), 35, '...');
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

            BelongsTo::make('Office')
                ->searchable()
                ->exceptOnForms(),

            BelongsTo::make('Appraiser', 'appraiser', User::class)
                ->searchable()
                ->exceptOnForms()
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
                        $reviewers = \App\Models\User::query()->find($value)?->reviewers;
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

            Select::make('Payment', 'payment_status')
                ->options(array_flip(\App\Enums\PaymentStatus::array()))
                ->displayUsingLabels(),

            //filters
            Select::make('Appraiser', 'appraiser_id')
                ->options([null => '-'] + \App\Models\User::query()->whereHas('roles', function ($roles) {
                        return $roles->whereIn('name', ['Appraiser']);
                    })->pluck('name', 'id')->toArray())
                ->filterable()
                ->searchable()
                ->exceptOnForms()
                ->hideFromDetail()
                ->hideFromIndex()
                ->displayUsingLabels(),
            Select::make('Appraisal Type', 'appraisal_type_id')
                ->options([null => '-'] + AppraisalType::pluck('name', 'id')->toArray())
                ->searchable()
                ->hideFromIndex()
                ->filterable()
                ->displayUsingLabels(),
            Select::make('Office', 'office_id')
                ->options([null => '-'] + \App\Models\Office::pluck('title', 'id')->toArray())
                ->hideFromIndex()
                ->searchable()
                ->filterable()
                ->displayUsingLabels(),
            Select::make('Client', 'client_id')
                ->options([null => '-'] + \App\Models\Client::pluck('name', 'id')->toArray())
                ->hideFromIndex()
                ->searchable()
                ->filterable()
                ->displayUsingLabels(),
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
//            (new OfficeFilter())
//                ->canSee(function () use ($request) {
//                    return $request->user()->hasManagementAccess();
//                }),
        ];
    }
}
