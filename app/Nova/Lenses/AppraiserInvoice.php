<?php

namespace App\Nova\Lenses;

use App\Models\AppraisalJob;
use App\Nova\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Laravel\Nova\Fields\Badge;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\Currency;
use Laravel\Nova\Fields\Date;
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
        return 'Appraisers Invoices';
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
                appraisal_jobs.property_address,
                appraisal_jobs.created_at,
                appraisal_jobs.reference_number,
                appraisal_jobs.fee_quoted,
                appraisal_jobs.payment_terms,
                appraisal_jobs.completed_at,
                appraiser_id,
                'Appraiser' AS user_type,
                CONCAT('INV-', YEAR(completed_at), '-', MONTH(completed_at)) AS invoice_number,
                users.commission,
                province_taxes.total as province_tax
            FROM
                appraisal_jobs
            INNER JOIN
                users
            ON
                users.id = appraiser_id
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
                appraiser_id IS NOT NULL
            AND
                (appraiser_id = " . auth()->user()->id . " OR " . (auth()->user()->hasManagementAccess() ? 'TRUE' : 'FALSE') . ")
            UNION
            SELECT
                appraisal_jobs.id,
                appraisal_jobs.client_id,
                appraisal_jobs.office_id,
                appraisal_jobs.property_address,
                appraisal_jobs.created_at,
                appraisal_jobs.reference_number,
                appraisal_jobs.fee_quoted,
                appraisal_jobs.payment_terms,
                appraisal_jobs.completed_at,
                reviewer_id,
                'Reviewer' AS user_type,
                CONCAT('INV-', YEAR(completed_at), '-', MONTH(completed_at)) AS invoice_number,
                users.reviewer_commission AS commission,
                province_taxes.total AS province_tax
            FROM
                appraisal_jobs
            INNER JOIN
                users
            ON
                users.id = reviewer_id
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
                reviewer_id IS NOT NULL
            AND
                (reviewer_id = " . auth()->user()->id . " OR " . (auth()->user()->hasManagementAccess() ? 'TRUE' : 'FALSE') . ")
        ";
        return $request->withOrdering($request->withFilters(
            $query->fromSub($rawQueryAsString, 'appraisal_jobs')->orderBy('completed_at', 'desc')
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
            Text::make('File Number', 'reference_number')->sortable(),
            BelongsTo::make('Appraiser', 'appraiser', User::class)
                ->filterable(function () {
                    return auth()->user()->hasManagementAccess();
                })
                ->searchable()
                ->sortable(),
            Badge::make('Appraiser Role', 'user_type')
                ->map([
                    'Appraiser' => 'info',
                    'Reviewer' => 'warning',
                ])
                ->filterable()
                ->sortable(),
            BelongsTo::make('Office')
                ->filterable()
                ->sortable(),
            BelongsTo::make('Client')
                ->filterable()
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


