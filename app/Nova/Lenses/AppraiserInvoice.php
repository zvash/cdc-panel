<?php

namespace App\Nova\Lenses;

use App\Models\AppraisalJob;
use App\Models\AppraisalType;
use App\Nova\User;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Laravel\Nova\Fields\Badge;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\Currency;
use Laravel\Nova\Fields\Date;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\LensRequest;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Lenses\Lens;
use Lupennat\BetterLens\BetterLens;

class AppraiserInvoice extends Lens
{
    use BetterLens;

    /**
     * The columns that should be searched.
     *
     * @var array
     */
    public static $search = [
        'invoice_number',
        'reference_number',
        'property_address',
        'client.name',
        'office.city',
        'appraiser.name',
    ];

    public static function withRelated()
    {
        return [
            'client',
            'office',
            'appraiser',
        ];
    }

    public static function hideFromToolbar()
    {
        return true;
    }

    public function authorizedToDelete(\Illuminate\Http\Request $request): bool
    {
        return false;
    }

    public function authorizedToView(\Illuminate\Http\Request $request): bool
    {
        return false;
    }

    public function authorizedToUpdate(\Illuminate\Http\Request $request): bool
    {
        return false;
    }

    public function name()
    {
        return 'Transactions';
    }

    /**
     * Get the query builder / paginator for the lens.
     *
     * @param \Laravel\Nova\Http\Requests\LensRequest $request
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return mixed
     */
    public static function query(LensRequest $request, $query)
    {
        $rawQueryAsString = "
            SELECT
                appraisal_jobs.id,
                appraisal_jobs.client_id,
                appraisal_jobs.office_id,
                appraisal_jobs.appraiser_id,
                appraisal_jobs.reviewer_id,
                appraisal_jobs.property_address,
                appraisal_jobs.appraisal_type_id,
                appraisal_jobs.created_at,
                appraisal_jobs.reference_number,
                appraisal_jobs.fee_quoted,
                appraisal_jobs.payment_terms,
                appraisal_jobs.completed_at,
                YEAR(appraisal_jobs.completed_at) AS completed_at_year,
                MONTH(appraisal_jobs.completed_at) AS completed_at_month,
                CONCAT('INV-', YEAR(completed_at), '-', MONTH(completed_at)) AS invoice_number,
                users.commission,
                reviewers.reviewer_commission,
                province_taxes.total as province_tax
            FROM
                appraisal_jobs
            INNER JOIN
                users
            ON
                users.id = appraiser_id
            INNER JOIN users as reviewers
                    ON reviewers.id = reviewer_id
            INNER JOIN
                provinces
            ON
                provinces.name = appraisal_jobs.province
            INNER JOIN
                province_taxes
            ON
                province_taxes.province_id = provinces.id
            WHERE
                completed_at IS NOT NULL
            AND
                fee_quoted IS NOT NULL
            AND
                province IS NOT NULL
            AND
                appraiser_id IS NOT NULL";
        $user = auth()->user();
        $user = \App\Models\User::query()->find($user->id);
        if (!$user->hasManagementAccess()) {
            $rawQueryAsString .= " AND (appraiser_id = " . auth()->user()->id . " OR reviewer_id = " . auth()->user()->id . ")";
        }
        return $request->withOrdering($request->withFilters(
            $query
                ->fromSub($rawQueryAsString, 'appraisal_jobs')
                ->orderBy('completed_at', 'desc')
        ));
    }

    /**
     * Get the fields available to the lens.
     *
     * @param \Laravel\Nova\Http\Requests\NovaRequest $request
     * @return array
     */
    public function fields(NovaRequest $request)
    {
        return [
            Text::make('Invoice Number', 'invoice_number')->sortable(),
            Select::make('Year', 'completed_at_year')
                ->options([
                    Carbon::now()->year => Carbon::now()->year,
                    Carbon::now()->subYear()->year => Carbon::now()->subYear()->year,
                ])
                ->displayUsingLabels()
                ->filterable()
                ->sortable(),
            Select::make('Month', 'completed_at_month')
                ->options([
                    1 => 'January',
                    2 => 'February',
                    3 => 'March',
                    4 => 'April',
                    5 => 'May',
                    6 => 'June',
                    7 => 'July',
                    8 => 'August',
                    9 => 'September',
                    10 => 'October',
                    11 => 'November',
                    12 => 'December',
                ])
                ->displayUsingLabels()
                ->filterable()
                ->sortable(),

            Text::make('File Number', 'reference_number')->sortable(),

            BelongsTo::make('Appraiser', 'appraiser', User::class)
                ->sortable(),
            BelongsTo::make('Office')
                ->sortable(),
            BelongsTo::make('Client')
                ->sortable(),
            BelongsTo::make('Appraisal Type')
                ->sortable(),
            Text::make('Property Address')->sortable(),
            Date::make('Completed At')
                ->filterable()
                ->sortable(),
            Currency::make('CDC Fee', 'fee_quoted')->sortable(),
            Text::make('CDC GST', 'province_tax')
                ->resolveUsing(fn($value) => '$' . round($value * $this->fee_quoted / 100, 2))
                ->sortable(),
            Text::make('CDC Total', 'fee_quoted')
                ->resolveUsing(fn($value) => '$' . round($value * $this->province_tax / 100 + $value, 2))
                ->sortable(),
            Text::make('Appraiser Commission', 'commission')
                ->displayUsing(fn($value) => ($value ?? 0) . '%')
                ->sortable(),
            Text::make('Appraiser Fee', 'fee_quoted')
                ->resolveUsing(fn($value) => '$' . round($value * ($this->commission ?? 0) / 100, 2))
                ->sortable(),
            Text::make('Appraiser GST', 'province_tax')
                ->resolveUsing(fn($value) => '$' . round(($value * ($this->commission ?? 0) * $this->fee_quoted / 100 / 100), 2))
                ->sortable(),
            Text::make('Appraiser Total', 'fee_quoted')
                ->resolveUsing(function ($value) {
                    return '$' . round(($value * $this->province_tax / 100 + $value) * (($this->commission ?? 0) / 100), 2);
                })
                ->sortable(),
            Text::make('Reviewer Commission', 'reviewer_commission')
                ->displayUsing(fn($value) => ($value ?? 0) . '%')
                ->sortable(),
            Text::make('Reviewer Fee', 'fee_quoted')
                ->resolveUsing(fn($value) => '$' . round($value * ($this->reviewer_commission ?? 0) / 100, 2))
                ->sortable(),
            Text::make('Reviewer GST', 'province_tax')
                ->resolveUsing(fn($value) => '$' . round(($value * ($this->reviewer_commission ?? 0) * $this->fee_quoted / 100 / 100), 2))
                ->sortable(),
            Text::make('Reviewer Total', 'fee_quoted')
                ->resolveUsing(function ($value) {
                    return '$' . round(($value * $this->province_tax / 100 + $value) * (($this->reviewer_commission ?? 0) / 100), 2);
                })
                ->sortable(),

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
     * Get the cards available on the lens.
     *
     * @param \Laravel\Nova\Http\Requests\NovaRequest $request
     * @return array
     */
    public function cards(NovaRequest $request)
    {
        return [];
    }

    /**
     * Get the filters available for the lens.
     *
     * @param \Laravel\Nova\Http\Requests\NovaRequest $request
     * @return array
     */
    public function filters(NovaRequest $request)
    {
        return [];
    }

    /**
     * Get the actions available on the lens.
     *
     * @param \Laravel\Nova\Http\Requests\NovaRequest $request
     * @return array
     */
    public function actions(NovaRequest $request)
    {
        return [];
    }

    /**
     * Get the URI key for the lens.
     *
     * @return string
     */
    public function uriKey()
    {
        return 'appraiser-invoice';
    }
}


