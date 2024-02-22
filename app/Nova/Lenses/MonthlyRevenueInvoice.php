<?php

namespace App\Nova\Lenses;

use App\Enums\AppraisalJobStatus;
use App\Models\AppraisalType;
use App\Nova\Client;
use App\Nova\Filters\ProvinceFilter;
use Carbon\Carbon;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\Currency;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\LensRequest;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Lenses\Lens;
use Laravel\Nova\Nova;
use Lupennat\BetterLens\BetterLens;

class MonthlyRevenueInvoice extends Lens
{
    use BetterLens;

    public static $search = [
        'invoice_number',
    ];

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
        return 'Monthly Revenue';
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
        return $request->withOrdering($request->withFilters(
            $query->fromSub(fn($query) => /**
             * @var \Illuminate\Database\Query\Builder $query
             */
            $query
                ->from('appraisal_jobs')
                ->select([
                    'id',
                    'client_id',
                    'office_id',
                    'appraiser_id',
                    'appraisal_type_id',
                    'admin_fee',
                    'completed_at',
                ])
                ->addSelect([
                    'invoice_number' => fn($query) => $query->selectRaw('CONCAT("INV-", YEAR(completed_at), "-", MONTH(completed_at))'),
                    'completed_at_year' => fn($query) => $query->selectRaw('YEAR(completed_at)'),
                    'completed_at_month' => fn($query) => $query->selectRaw('MONTH(completed_at)'),
                    'province_tax' => fn($query) => $query->select('total')
                        ->from('provinces')
                        ->join('province_taxes', 'province_taxes.province_id', '=', 'provinces.id')
                        ->whereColumn('provinces.name', 'appraisal_jobs.province'),
                    'qst' => fn($query) => $query->select('qst')
                        ->from('provinces')
                        ->join('province_taxes', 'province_taxes.province_id', '=', 'provinces.id')
                        ->whereColumn('provinces.name', 'appraisal_jobs.province'),
                    'gst' => fn($query) => $query->select('gst')
                        ->from('provinces')
                        ->join('province_taxes', 'province_taxes.province_id', '=', 'provinces.id')
                        ->whereColumn('provinces.name', 'appraisal_jobs.province'),
                    'hst' => fn($query) => $query->select('hst')
                        ->from('provinces')
                        ->join('province_taxes', 'province_taxes.province_id', '=', 'provinces.id')
                        ->whereColumn('provinces.name', 'appraisal_jobs.province'),
                    'pst' => fn($query) => $query->select('pst')
                        ->from('provinces')
                        ->join('province_taxes', 'province_taxes.province_id', '=', 'provinces.id')
                        ->whereColumn('provinces.name', 'appraisal_jobs.province'),
                    'cdc_fee_with_tax' => fn($query) => $query->selectRaw('admin_fee * province_tax / 100 + admin_fee'),
                    'cdc_tax' => fn($query) => $query->selectRaw('admin_fee * province_tax / 100'),
                ])
                ->whereNotNull('completed_at')
                ->whereNot('status', AppraisalJobStatus::Cancelled->value)
                ->whereNotNull('admin_fee')
                ->whereNotNull('province')
                ->where(function ($query) {
                    if (auth()->user()->hasManagementAccess()) {
                        return $query;
                    } else {
                        return $query->where('appraiser_id', -1);
                    }
                })
                , 'appraisal_jobs'
            )->select('invoice_number',)
                ->selectRaw('CAST(SUM(admin_fee) AS DECIMAL(10,2)) AS admin_fee, CAST(SUM(cdc_fee_with_tax) AS DECIMAL(10,2)) AS cdc_fee_with_tax, CAST(SUM(cdc_tax) AS DECIMAL(10,2)) AS cdc_tax, completed_at_year, completed_at_month, qst, gst, hst, pst')
                ->groupBy('invoice_number', 'completed_at_year', 'completed_at_month', 'qst', 'gst', 'hst', 'pst')
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
            Text::make('Invoice Number', 'invoice_number')
                ->filterable()
                ->sortable(),
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

            Currency::make('CDC Fee', 'admin_fee')
                ->resolveUsing(fn($value) => round($value, 2))
                ->sortable(),
            Currency::make('CDC Tax', 'cdc_tax')
                ->resolveUsing(fn($value) => round($value, 2))
                ->sortable(),
            Currency::make('CDC Total', 'cdc_fee_with_tax')
                ->resolveUsing(fn($value) => round($value, 2))
                ->sortable(),

            Number::make('QST')
                ->displayUsing(function ($value) {
                    return '$' . round($this->resource->admin_fee * (1 + $value / 100.0), 2);
                }),

            Number::make('PST')
                ->displayUsing(function ($value) {
                    return '$' . round($this->resource->admin_fee * (1 + $value / 100.0), 2);
                }),

            Number::make('GST')
                ->displayUsing(function ($value) {
                    return '$' . round($this->resource->admin_fee * (1 + $value / 100.0), 2);
                }),

            Number::make('HST')
                ->displayUsing(function ($value) {
                    return '$' . round($this->resource->admin_fee * (1 + $value / 100.0), 2);
                }),

            //filters
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
        return [
            new ProvinceFilter(),
        ];
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
        return 'monthly-revenue-invoice';
    }
}
