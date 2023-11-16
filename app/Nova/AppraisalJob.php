<?php

namespace App\Nova;

use Digitalcloud\ZipCodeNova\ZipCode;
use Dniccum\PhoneNumber\PhoneNumber;
use Illuminate\Http\Request;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\Currency;
use Laravel\Nova\Fields\Date;
use Laravel\Nova\Fields\FormData;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Textarea;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Panel;

class AppraisalJob extends Resource
{
    /**
     * The model the resource corresponds to.
     *
     * @var class-string<\App\Models\AppraisalJob>
     */
    public static $model = \App\Models\AppraisalJob::class;

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
    ];

    /**
     * Get the fields displayed by the resource.
     *
     * @param \Laravel\Nova\Http\Requests\NovaRequest $request
     * @return array
     */
    public function fields(NovaRequest $request): array
    {
        return [
            $this->orderInformation(),

            $this->paymentInformation(),

            $this->propertyAddress(),

            $this->contactInformation(),

            $this->additionalInformations(),
        ];
    }

    public function orderInformation(): Panel
    {
        return $this->panel('Order Information', [
            ID::make()->sortable(),

            BelongsTo::make('Created By', 'createdBy', User::class)
                ->searchable()
                ->exceptOnForms()
                ->displayUsing(function ($user) {
                    return $user->name;
                }),

            BelongsTo::make('Client')
                ->searchable()
                ->exceptOnForms()
                ->displayUsing(function ($client) {
                    return $client->complete_name;
                }),

            Select::make('Client', 'client_id')
                ->options(\App\Models\Client::all()->pluck('complete_name', 'id'))
                ->searchable()
                ->onlyOnForms()
                ->displayUsingLabels(),

            Select::make('Appraisal Type', 'appraisal_type_id')
                ->options(\App\Models\AppraisalType::pluck('name', 'id'))
                ->searchable()
                ->displayUsingLabels(),

            BelongsTo::make('Appraiser', 'appraiser', User::class)
                ->searchable()
                ->exceptOnForms()
                ->displayUsing(function ($user) {
                    return $user->name;
                }),

            Select::make('Appraiser', 'appraiser_id')
                ->options(\App\Models\User::whereHas('roles', function ($roles) {
                    return $roles->where('name', 'Appraiser');
                })->pluck('name', 'id'))
                ->onlyOnForms()
                ->searchable()
                ->displayUsingLabels(),

            BelongsTo::make('Reviewer', 'reviewer', User::class)
                ->searchable()
                ->exceptOnForms()
                ->hideFromIndex()
                ->displayUsing(function ($user) {
                    return $user->name;
                }),

            Select::make('Reviewer', 'reviewer_id')
                ->options(\App\Models\User::whereHas('roles', function ($roles) {
                    return $roles->whereIn('name', ['Appraiser', 'Admin', 'SuperAdmin']);
                })->pluck('name', 'id'))
                ->onlyOnForms()
                ->searchable()
                ->displayUsingLabels(),

            Text::make('Lender')->hideFromIndex(),

            Text::make('Reference Number')->hideFromIndex(),

            Text::make('Applicant')->hideFromIndex(),

            Text::make('Email')
                ->hideFromIndex()
                ->creationRules('email'),

            Date::make('Due Date')
                ->sortable(),
        ]);
    }

    public function paymentInformation(): Panel
    {
        return $this->panel('Payment Information', [
            Currency::make('Fee Quoted')
                ->min(0)
                ->max(999999.99)
                ->step(0.01)
                ->hideFromIndex()
                ->nullable(),

            Select::make('Payment Terms')
                ->options(\App\Enums\PaymentTerm::array())
                ->hideFromIndex()
                ->displayUsingLabels(),

            Select::make('Payment Status')
                ->options(\App\Enums\PaymentStatus::array())
                ->hideFromIndex()
                ->displayUsingLabels(),

            Text::make('Invoice Name')
                ->hideFromIndex(),

            Text::make('Invoice Email')
                ->hideFromIndex()
                ->creationRules('email'),
        ]);
    }

    public function propertyAddress(): Panel
    {
        return $this->panel('Property Address', [
            Select::make('Property Province')
                ->searchable()
                ->required()
                ->hideFromIndex()
                ->options(\App\Models\Province::pluck('name', 'name'))
                ->displayUsingLabels(),

            Select::make('Property City')
                ->searchable()
                ->required()
                ->hideFromIndex()
                ->dependsOn(['property_province'], function (Select $field, NovaRequest $request, FormData $formData) {
                    if ($formData->property_province) {
                        $field->options(
                            \App\Models\City::where('province_id', \App\Models\Province::where('name', $formData->property_province)
                                ->first()->id)->pluck('name', 'name')
                        );
                    } else {
                        $field->options([]);
                    }
                })
                ->displayUsingLabels(),

            ZipCode::make('Zip Code', 'property_zip')
                ->hideFromIndex()
                ->setCountry('CA'),

            Text::make('Address', 'property_address')
                ->hideFromIndex(),
        ]);
    }

    public function contactInformation(): Panel
    {
        return $this->panel('Contact Information', [
            Text::make('Contact Name')->hideFromIndex(),

            PhoneNumber::make('Contact Phone')
                ->hideFromIndex()
                ->country('CA'),
        ]);
    }

    public function additionalInformations()
    {
        return $this->panel('Additional Information', [
            Textarea::make('Additional Information')
                ->hideFromIndex(),
        ]);
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

    /**
     * Create a panel for collection of fields.
     *
     * @param string $key
     * @param array $fields
     * @param int|null $limit
     * @return \Laravel\Nova\Panel
     */
    protected function panel(string $key, array $fields, ?int $limit = null): Panel
    {
        $panel = new Panel(__($key), $fields);

        return $limit ? $panel->limit($limit) : $panel;
    }
}
