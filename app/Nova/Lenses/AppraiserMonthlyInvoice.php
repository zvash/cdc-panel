<?php

namespace App\Nova\Lenses;

use App\Nova\User;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\Currency;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\LensRequest;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Lenses\Lens;
use Laravel\Nova\Nova;
use Lupennat\BetterLens\BetterLens;

class AppraiserMonthlyInvoice extends Lens
{
    use BetterLens;

    /**
     * The columns that should be searched.
     *
     * @var array
     */
    public static $search = [
        'invoice_number',
        'appraiser.name',
    ];

    public static function withRelated()
    {
        return [
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
        return 'Appraisers Monthly Invoices';
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
                appraisal_jobs.reference_number,
                appraisal_jobs.fee_quoted,
                appraisal_jobs.fee_quoted * province_taxes.total / 100 + appraisal_jobs.fee_quoted AS cdc_fee_with_tax,
                appraisal_jobs.fee_quoted * province_taxes.total / 100 AS cdc_tax,
                appraisal_jobs.fee_quoted * users.commission / 100 AS appraiser_fee,
                (appraisal_jobs.fee_quoted * province_taxes.total / 100 + appraisal_jobs.fee_quoted) * users.commission / 100 AS appraiser_fee_with_tax,
                appraisal_jobs.fee_quoted * province_taxes.total / 100 * users.commission / 100 AS appraiser_tax,
                appraisal_jobs.completed_at,
                appraisal_jobs.appraiser_id AS appraiser_id,
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
                appraisal_jobs.reference_number,
                appraisal_jobs.fee_quoted,
                appraisal_jobs.fee_quoted * province_taxes.total / 100 + appraisal_jobs.fee_quoted AS cdc_fee_with_tax,
                appraisal_jobs.fee_quoted * province_taxes.total / 100 AS cdc_tax,
                appraisal_jobs.fee_quoted * users.reviewer_commission / 100 AS appraiser_fee,
                (appraisal_jobs.fee_quoted * province_taxes.total / 100 + appraisal_jobs.fee_quoted) * users.reviewer_commission / 100 AS appraiser_fee_with_tax,
                appraisal_jobs.fee_quoted * province_taxes.total / 100 * users.reviewer_commission / 100 AS appraiser_tax,
                appraisal_jobs.completed_at,
                appraisal_jobs.reviewer_id AS appraiser_id,
                'Reviewer' AS user_type,
                CONCAT('INV-', YEAR(completed_at), '-', MONTH(completed_at)) AS invoice_number,
                users.reviewer_commission as commission,
                province_taxes.total as province_tax
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
            $query->fromSub($rawQueryAsString, 'appraisal_jobs')
                ->select('invoice_number', 'appraiser_id',)
                ->selectRaw('CAST(SUM(fee_quoted) AS DECIMAL(10,2)) as fee_quoted, CAST(SUM(cdc_fee_with_tax) AS DECIMAL(10,2)) as cdc_fee_with_tax, CAST(SUM(cdc_tax) AS DECIMAL(10,2)) as cdc_tax, CAST(SUM(appraiser_fee) AS DECIMAL(10,2)) as appraiser_fee, CAST(SUM(appraiser_fee_with_tax) AS DECIMAL(10,2)) as appraiser_fee_with_tax, CAST(SUM(appraiser_tax) AS DECIMAL(10,2)) as appraiser_tax')
                ->groupBy('invoice_number', 'appraiser_id')
                ->orderBy('invoice_number', 'desc')
        ));
    }

    /**
     * Get the fields available to the lens.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return array
     */
    public function fields(NovaRequest $request)
    {
        return [
            Text::make('Invoice Number', 'invoice_number')->sortable(),
            BelongsTo::make('Appraiser', 'appraiser', User::class)
                ->filterable(function () {
                    return auth()->user()->hasManagementAccess();
                })
                ->searchable()
                ->sortable(),
            Currency::make('CDC Fee', 'fee_quoted')->sortable(),
            Currency::make('CDC Tax', 'cdc_tax')->sortable(),
            Currency::make('CDC Total', 'cdc_fee_with_tax')->sortable(),
            Currency::make('Appraiser Fee', 'appraiser_fee')->sortable(),
            Currency::make('Appraiser Tax', 'appraiser_tax')->sortable(),
            Currency::make('Appraiser Total', 'appraiser_fee_with_tax')->sortable(),
        ];
    }

    /**
     * Get the cards available on the lens.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return array
     */
    public function cards(NovaRequest $request)
    {
        return [];
    }

    /**
     * Get the filters available for the lens.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return array
     */
    public function filters(NovaRequest $request)
    {
        return [];
    }

    /**
     * Get the actions available on the lens.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return array
     */
    public function actions(NovaRequest $request)
    {
        return parent::actions($request);
    }

    /**
     * Get the URI key for the lens.
     *
     * @return string
     */
    public function uriKey()
    {
        return 'appraiser-monthly-invoice';
    }
}
