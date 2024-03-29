<?php

namespace App\Nova\Lenses;

use App\Enums\AppraisalJobStatus;
use App\Models\AppraisalType;
use App\Nova\User;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\Currency;
use Laravel\Nova\Fields\Date;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\LensRequest;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Lenses\Lens;
use Laravel\Nova\Nova;
use Lupennat\BetterLens\BetterLens;

class ReviewerInvoice extends Lens
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
        'reviewer.name',
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
        return 'Reviewer Invoices';
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
                    'reviewer_id',
                    'appraiser_id',
                    'appraiser_type_id',
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
                    'reviewer_commission' => fn($query) => $query->select('reviewer_commission')
                        ->from('users')
                        ->whereColumn('users.id', 'appraisal_jobs.reviewer_id'),
                    'province_tax' => fn($query) => $query->select('total')
                        ->from('provinces')
                        ->join('province_taxes', 'province_taxes.province_id', '=', 'provinces.id')
                        ->whereColumn('provinces.name', 'appraisal_jobs.province'),
                ])
                ->whereNotNull('completed_at')
                ->whereNot('status', AppraisalJobStatus::Cancelled->value)
                ->whereNotNull('fee_quoted')
                ->whereNotNull('province')
                ->whereNotNull('reviewer_id')
                ->where(function ($query) {
                    if (auth()->user()->hasManagementAccess()) {
                        return $query;
                    } else {
                        return $query->where('reviewer_id', auth()->user()->id);
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
            BelongsTo::make('Reviewer', 'reviewer', User::class)
                ->filterable(function () {
                    return auth()->user()->hasManagementAccess();
                })
                ->searchable()
                ->sortable(),
            BelongsTo::make('Appraiser', 'appraiser', User::class)
                ->searchable()
                ->sortable(),
            BelongsTo::make('Office')
                ->searchable()
                ->sortable(),
            BelongsTo::make('Client')
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
            Select::make('Appraisal Type', 'appraisal_type_id')
                ->options(AppraisalType::pluck('name', 'id'))
                ->required()
                ->hideFromIndex()
                ->filterable()
                ->displayUsingLabels(),
            Select::make('Office', 'office_id')
                ->options(\App\Models\Office::pluck('title', 'id'))
                ->required()
                ->hideFromIndex()
                ->filterable()
                ->displayUsingLabels(),
            Select::make('Appraiser', 'appraiser_id')
                ->options(\App\Models\User::query()->whereHas('roles', function ($roles) {
                    return $roles->whereIn('name', ['Appraiser']);
                })->pluck('name', 'id')->toArray())
                ->required()
                ->hideFromIndex()
                ->filterable()
                ->displayUsingLabels(),
            Select::make('Client', 'client_id')
                ->options(\App\Models\Client::pluck('name', 'id'))
                ->required()
                ->hideFromIndex()
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
        return parent::actions($request);
    }

    /**
     * Get the URI key for the lens.
     *
     * @return string
     */
    public function uriKey()
    {
        return 'reviewer-invoice';
    }
}
