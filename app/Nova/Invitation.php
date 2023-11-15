<?php

namespace App\Nova;

use App\Traits\NovaResource\LimitsIndexQuery;
use Illuminate\Http\Request;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\Date;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;

class Invitation extends Resource
{
    use LimitsIndexQuery;

    /**
     * The model the resource corresponds to.
     *
     * @var class-string<\App\Models\Invitation>
     */
    public static $model = \App\Models\Invitation::class;

    /**
     * Get the value that should be displayed to represent the resource.
     *
     * @return string
     */
    public function title()
    {
        return ucwords($this->email);
    }

    /**
     * Get the search result subtitle for the resource.
     *
     * @return string|null
     */
    public function subtitle()
    {
        return strtolower($this->role);
    }

    /**
     * The columns that should be searched.
     *
     * @var array
     */
    public static $search = [
        'id',
        'email',
        'role',
        'invited_by',
    ];

    /**
     * Get the logical group associated with the resource.
     *
     * @return string
     */
    public static function group()
    {
        return 'Accounts';
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

            BelongsTo::make('Invited By', 'invitedBy', User::class)
                ->required()
                ->sortable(),

            Text::make('Email')
                ->sortable()
                ->rules('required', 'email', 'max:254')
                ->creationRules('unique:invitation,email')
                ->updateRules('unique:invitation,email,{{resourceId}}'),

            Text::make('Role')
                ->sortable()
                ->rules('required', 'in:Admin,Appraiser')
                ->creationRules('unique:invitations,email')
                ->updateRules('unique:invitations,email,{{resourceId}}'),

            Text::make('Token')
                ->readonly()
                ->hideWhenUpdating()
                ->hideWhenCreating(),

            Date::make('Sent At')
                ->nullable()
                ->readonly(),

            Date::make('Accepted At')
                ->nullable()
                ->readonly(),
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
