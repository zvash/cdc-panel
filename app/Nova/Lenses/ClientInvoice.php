<?php

namespace App\Nova\Lenses;

use App\Nova\User;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\Currency;
use Laravel\Nova\Fields\Date;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\LensRequest;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Lenses\Lens;
use Lupennat\BetterLens\BetterLens;

class ClientInvoice extends Lens
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
        return 'Client Invoices';
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
                    'appraiser_id',
                    'client_id',
                    'office_id',
                    'property_address',
                    'created_at',
                    'reference_number',
                    'fee_quoted',
                    'payment_terms',
                    'completed_at',
                ])
                ->addSelect([
                    'invoice_number' => fn($query) => $query->selectRaw('CONCAT("INV-", YEAR(completed_at), "-", MONTH(completed_at))'),
                    'province_tax' => fn($query) => $query->select('total')
                        ->from('provinces')
                        ->join('province_taxes', 'province_taxes.province_id', '=', 'provinces.id')
                        ->whereColumn('provinces.name', 'appraisal_jobs.province'),
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
            )
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
            BelongsTo::make('Office')
                ->filterable()
                ->searchable()
                ->sortable(),
            BelongsTo::make('Client')
                ->filterable()
                ->searchable()
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
        return parent::actions($request);
    }

    /**
     * Get the URI key for the lens.
     *
     * @return string
     */
    public function uriKey()
    {
        return 'client-invoice';
    }
}
