<?php

namespace App\Nova\Lenses;

use App\Nova\Client;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\Currency;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\LensRequest;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Lenses\Lens;
use Laravel\Nova\Nova;
use Lupennat\BetterLens\BetterLens;

class ClientMonthlyInvoice extends Lens
{
    use BetterLens;

    public static $search = [
        'invoice_number',
        'client.name',
    ];

    public static function withRelated()
    {
        return [
            'client',
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
        return 'Clients Monthly Invoices';
    }

    /**
     * Get the query builder / paginator for the lens.
     *
     * @param  \Laravel\Nova\Http\Requests\LensRequest  $request
     * @param  \Illuminate\Database\Eloquent\Builder  $query
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
                    'fee_quoted',
                    'completed_at',
                ])
                ->addSelect([
                    'invoice_number' => fn($query) => $query->selectRaw('CONCAT("INV-", YEAR(completed_at), "-", MONTH(completed_at))'),
                    'province_tax' => fn($query) => $query->select('total')
                        ->from('provinces')
                        ->join('province_taxes', 'province_taxes.province_id', '=', 'provinces.id')
                        ->whereColumn('provinces.name', 'appraisal_jobs.province'),
                    'cdc_fee_with_tax' => fn($query) => $query->selectRaw('fee_quoted * province_tax / 100 + fee_quoted'),
                    'cdc_tax' => fn($query) => $query->selectRaw('fee_quoted * province_tax / 100'),
                ])
                ->whereNotNull('completed_at')
                ->whereNotNull('fee_quoted')
                ->whereNotNull('province')
                ->where(function ($query) {
                    if (auth()->user()->hasManagementAccess()) {
                        return $query;
                    } else {
                        return $query->where('appraiser_id', -1);
                    }
                })
                , 'appraisal_jobs'
            )->select('invoice_number', 'client_id',)
            ->selectRaw('SUM(fee_quoted) AS fee_quoted, SUM(cdc_fee_with_tax) AS cdc_fee_with_tax, SUM(cdc_tax) AS cdc_tax')
            ->groupBy('invoice_number', 'client_id')
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
            Text::make('Invoice Number', 'invoice_number')
                ->filterable()
                ->sortable(),
            BelongsTo::make('Client', 'client', Client::class)
                ->filterable()
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
        return 'client-monthly-invoice';
    }
}
