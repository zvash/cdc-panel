<?php

namespace App\Nova;

use Illuminate\Http\Request;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;

class ProvinceTax extends Resource
{
    /**
     * The model the resource corresponds to.
     *
     * @var class-string<\App\Models\ProvinceTax>
     */
    public static $model = \App\Models\ProvinceTax::class;

    /**
     * The single value that should be used to represent the resource when being displayed.
     *
     * @var string
     */
    public static $title = 'id';

    /**
     * The columns that should be searched.
     *
     * @var array
     */
    public static $search = [
        'id',
        'province.name',
    ];

    public static $with = [
        'province',
    ];

    public function authorizedToDelete(Request $request)
    {
        return false;
    }

    public function authorizedToView(Request $request)
    {
        return true;
    }

    public function authorizedToUpdate(Request $request)
    {
        return auth()->user() && auth()->user()->hasManagementAccess();
    }

    public static function authorizedToCreate(Request $request)
    {
        return return auth()->user() && auth()->user()->hasManagementAccess();
    }

    public static function label()
    {
        return 'Province Taxes';
    }

    /**
     * Get the fields displayed by the resource.
     *
     * @param \Laravel\Nova\Http\Requests\NovaRequest $request
     * @return array
     */
    public function fields(NovaRequest $request)
    {
        return [
            ID::make()->sortable(),
            Select::make('Province', 'province_id')
                ->options(\App\Models\Province::all()->pluck('name', 'id'))
                ->displayUsingLabels(),

            Number::make('PST')
                ->displayUsing(function ($value) {
                    return $value . '%';
                })->step(0.001)
                ->min(0)
                ->max(100),

            Number::make('GST')
                ->displayUsing(function ($value) {
                    return $value . '%';
                })->step(0.001)
                ->min(0)
                ->max(100),

            Number::make('HST')
                ->displayUsing(function ($value) {
                    return $value . '%';
                })->step(0.001)
                ->min(0)
                ->max(100),

            Number::make('Total')
                ->displayUsing(function ($value) {
                    return $value . '%';
                })->step(0.001)
                ->min(0)
                ->max(100),

        ];
    }

    /**
     * Get the cards available for the request.
     *
     * @param \Laravel\Nova\Http\Requests\NovaRequest $request
     * @return array
     */
    public function cards(NovaRequest $request)
    {
        return [];
    }

    /**
     * Get the filters available for the resource.
     *
     * @param \Laravel\Nova\Http\Requests\NovaRequest $request
     * @return array
     */
    public function filters(NovaRequest $request)
    {
        return [];
    }

    /**
     * Get the lenses available for the resource.
     *
     * @param \Laravel\Nova\Http\Requests\NovaRequest $request
     * @return array
     */
    public function lenses(NovaRequest $request)
    {
        return [];
    }

    /**
     * Get the actions available for the resource.
     *
     * @param \Laravel\Nova\Http\Requests\NovaRequest $request
     * @return array
     */
    public function actions(NovaRequest $request)
    {
        return [];
    }
}
