<?php

namespace App\Nova;

use Dniccum\PhoneNumber\PhoneNumber;
use Illuminate\Http\Request;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;

class Client extends Resource
{
    /**
     * The model the resource corresponds to.
     *
     * @var class-string<\App\Models\Client>
     */
    public static $model = \App\Models\Client::class;

    /**
     * The single value that should be used to represent the resource when being displayed.
     *
     * @var string
     */
    public static $title = 'company_name';

    /**
     * The columns that should be searched.
     *
     * @var array
     */
    public static $search = [
        'id',
        'name',
        'company_name',
    ];

    public static function redirectAfterCreate(NovaRequest $request, $resource)
    {
        return '/resources/'.static::uriKey();
    }


    /**
     * Get the fields displayed by the resource.
     *
     * @param \Laravel\Nova\Http\Requests\NovaRequest $request
     * @return array
     */
    public function fields(NovaRequest $request)
    {
        $clientTypes = \App\Models\ClientType::query()
            ->orderBy('id')
            ->get()
            ->pluck('name', 'id')
            ->toArray();
        return [
            ID::make()->sortable(),

            Select::make('Client Type', 'client_type_id')
                ->options($clientTypes)
                ->displayUsingLabels()
                ->searchable()
                ->required()
                ->sortable(),

            Text::make('Company Name')
                ->sortable()
                ->required()
                ->rules('required', 'max:255'),

            Text::make('Name')
                ->sortable()
                ->required()
                ->rules('required', 'max:255'),

            Text::make('Email')
                ->sortable()
                ->nullable()
                ->rules('nullable', 'email', 'max:255'),

            PhoneNumber::make('Phone')
                ->disableValidation()
                ->sortable()
                ->nullable()
                ->rules('nullable', 'max:255'),

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
