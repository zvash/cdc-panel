<?php

namespace App\Nova\Lenses;

use App\Enums\AppraisalJobStatus;
use App\Models\AppraisalType;
use App\Nova\User;
use Carbon\Carbon;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\Currency;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\ActionRequest;
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
                appraisal_jobs.appraisal_type_id,
                appraisal_jobs.client_id,
                appraisal_jobs.office_id,
                appraisal_jobs.fee_quoted,
                appraisal_jobs.fee_quoted * province_taxes.total / 100 + appraisal_jobs.fee_quoted AS cdc_fee_with_tax,
                appraisal_jobs.fee_quoted * province_taxes.total / 100 AS cdc_tax,
                appraisal_jobs.fee_quoted * IFNULL(appraisal_jobs.commission, users.commission) / 100 AS appraiser_fee,
                (appraisal_jobs.fee_quoted * province_taxes.total / 100 + appraisal_jobs.fee_quoted) * IFNULL(appraisal_jobs.commission, users.commission) / 100 AS appraiser_fee_with_tax,
                appraisal_jobs.fee_quoted * province_taxes.total / 100 * IFNULL(appraisal_jobs.commission, users.commission) / 100 AS appraiser_tax,
                appraisal_jobs.completed_at,
                appraisal_jobs.appraiser_id AS appraiser_id,
                'Appraiser' AS user_type,
                CONCAT('INV-', YEAR(completed_at), '-', MONTH(completed_at)) AS invoice_number,
                users.commission,
                province_taxes.total as province_tax,
                YEAR(appraisal_jobs.completed_at) AS completed_at_year,
                MONTH(appraisal_jobs.completed_at) AS completed_at_month
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
                status = '" . AppraisalJobStatus::Completed->value . "'
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
                appraisal_jobs.appraisal_type_id,
                appraisal_jobs.client_id,
                appraisal_jobs.office_id,
                appraisal_jobs.fee_quoted,
                appraisal_jobs.fee_quoted * province_taxes.total / 100 + appraisal_jobs.fee_quoted AS cdc_fee_with_tax,
                appraisal_jobs.fee_quoted * province_taxes.total / 100 AS cdc_tax,
                appraisal_jobs.fee_quoted * IFNULL(appraisal_jobs.reviewer_commission, users.reviewer_commission) / 100 AS appraiser_fee,
                (appraisal_jobs.fee_quoted * province_taxes.total / 100 + appraisal_jobs.fee_quoted) * IFNULL(appraisal_jobs.reviewer_commission, users.reviewer_commission) / 100 AS appraiser_fee_with_tax,
                appraisal_jobs.fee_quoted * province_taxes.total / 100 * IFNULL(appraisal_jobs.reviewer_commission, users.reviewer_commission) / 100 AS appraiser_tax,
                appraisal_jobs.completed_at,
                appraisal_jobs.reviewer_id AS appraiser_id,
                'Reviewer' AS user_type,
                CONCAT('INV-', YEAR(completed_at), '-', MONTH(completed_at)) AS invoice_number,
                users.reviewer_commission as commission,
                province_taxes.total as province_tax,
                YEAR(appraisal_jobs.completed_at) AS completed_at_year,
                MONTH(appraisal_jobs.completed_at) AS completed_at_month
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
                ->select('invoice_number', 'appraiser_id', 'completed_at_year', 'completed_at_month')
                ->selectRaw('id, CAST(SUM(fee_quoted) AS DECIMAL(10,2)) as fee_quoted, CAST(SUM(cdc_fee_with_tax) AS DECIMAL(10,2)) as cdc_fee_with_tax, CAST(SUM(cdc_tax) AS DECIMAL(10,2)) as cdc_tax, CAST(SUM(appraiser_fee) AS DECIMAL(10,2)) as appraiser_fee, CAST(SUM(appraiser_fee_with_tax) AS DECIMAL(10,2)) as appraiser_fee_with_tax, CAST(SUM(appraiser_tax) AS DECIMAL(10,2)) as appraiser_tax')
                ->groupBy('invoice_number', 'appraiser_id', 'completed_at_year', 'completed_at_month')
                ->orderBy('invoice_number', 'desc')
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
            ID::hidden(),
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
            BelongsTo::make('Appraiser', 'appraiser', User::class)
                ->searchable()
                ->sortable(),
            Currency::make('CDC Fee', 'fee_quoted')
                ->resolveUsing(fn($value) => round($value, 2))
                ->sortable(),
            Currency::make('CDC Tax', 'cdc_tax')
                ->resolveUsing(fn($value) => round($value, 2))
                ->sortable(),
            Currency::make('CDC Total', 'cdc_fee_with_tax')
                ->resolveUsing(fn($value) => round($value, 2))
                ->sortable(),
            Currency::make('Appraiser Fee', 'appraiser_fee')
                ->resolveUsing(fn($value) => round($value, 2))
                ->sortable(),
            Currency::make('Appraiser Tax', 'appraiser_tax')
                ->resolveUsing(fn($value) => round($value, 2))
                ->sortable(),
            Currency::make('Appraiser Total', 'appraiser_fee_with_tax')
                ->resolveUsing(fn($value) => round($value, 2))
                ->sortable(),

            Text::make('', 'appraiser_id')
                ->displayUsing(function ($value) {
                    return '<div class="shrink-0"><a size="md" class="shrink-0 h-9 px-4 focus:outline-none ring-primary-200 dark:ring-gray-600 focus:ring text-white dark:text-gray-800 inline-flex items-center font-bold shadow rounded focus:outline-none ring-primary-200 dark:ring-gray-600 focus:ring bg-primary-500 hover:bg-primary-400 active:bg-primary-600 text-white dark:text-gray-800 inline-flex items-center font-bold px-4 h-9 text-sm shrink-0 h-9 px-4 focus:outline-none ring-primary-200 dark:ring-gray-600 focus:ring text-white dark:text-gray-800 inline-flex items-center font-bold" href="/pdf/appraiser-invoice/' . $value . '/year/' . $this->completed_at_year . '/month/' . $this->completed_at_month . '"><span class="hidden md:inline-block">PDF</span><span class="inline-block md:hidden">PDF</span></a></div>';
                })->asHtml(),

//            Text::make('', 'appraiser_id')
//                ->displayUsing(function ($value) {
//                    return '<div class="shrink-0"><a size="md" class="shrink-0 h-9 px-4 focus:outline-none ring-primary-200 dark:ring-gray-600 focus:ring text-white dark:text-gray-800 inline-flex items-center font-bold shadow rounded focus:outline-none ring-primary-200 dark:ring-gray-600 focus:ring bg-primary-500 hover:bg-primary-400 active:bg-primary-600 text-white dark:text-gray-800 inline-flex items-center font-bold px-4 h-9 text-sm shrink-0 h-9 px-4 focus:outline-none ring-primary-200 dark:ring-gray-600 focus:ring text-white dark:text-gray-800 inline-flex items-center font-bold" href="/paid/appraiser-invoice/' . $value . '/year/' . $this->completed_at_year . '/month/' . $this->completed_at_month . '"><span class="hidden md:inline-block">Paid</span><span class="inline-block md:hidden">Paid</span></a></div>';
//                })->asHtml(),

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
        return [
            (new \App\Nova\Actions\Paid($this->resource))
                ->showInline()
                ->showAsButton()
                ->canSee(function () use ($request) {
                    $q = $request->findModelQuery($request->resources);
                    return optional($q->first());
                }),
        ];
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
