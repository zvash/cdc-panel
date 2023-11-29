<?php

namespace App\Nova;

use App\Nova\Actions\AssignAppraiserAction;
use App\Traits\NovaResource\LimitsIndexQuery;
use Digitalcloud\ZipCodeNova\ZipCode;
use Dniccum\PhoneNumber\PhoneNumber;
use Illuminate\Http\Request;
use Laravel\Nova\Fields\Badge;
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
    use LimitsIndexQuery;
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
                ->showCreateRelationButton()
                ->modalSize('3xl')
                ->displayUsing(function ($client) {
                    return $client->complete_name;
                }),

            Select::make('Appraisal Type', 'appraisal_type_id')
                ->options(\App\Models\AppraisalType::pluck('name', 'id'))
                ->searchable()
                ->required()
                ->displayUsingLabels(),

            BelongsTo::make('Office')
                ->searchable()
                ->exceptOnForms(),

            Select::make('Office', 'office_id')
                ->searchable()
                ->required()
                ->onlyOnForms()
                ->options(\App\Models\Office::pluck('city', 'id'))
                ->displayUsingLabels(),

            BelongsTo::make('Appraiser', 'appraiser', User::class)
                ->searchable()
                ->exceptOnForms()
                ->displayUsing(function ($user) {
                    return $user->name;
                }),

            Badge::make('Status')->map([
                \App\Enums\AppraisalJobStatus::Pending->value => 'warning',
                \App\Enums\AppraisalJobStatus::InProgress->value => 'info',
                \App\Enums\AppraisalJobStatus::Completed->value => 'success',
                \App\Enums\AppraisalJobStatus::Cancelled->value => 'danger',
            ])->exceptOnForms(),

            Badge::make('On Hold?', 'is_on_hold')
                ->label(function ($isOnHold) {
                    return $isOnHold ? 'Yes' : 'No';
                })->map([
                    true => 'warning',
                    false => 'success',
                ])->withIcons()
                ->exceptOnForms(),

            BelongsTo::make('Appraiser', 'appraiser', User::class)
                ->searchable()
                ->exceptOnForms()
                ->nullable()
                ->hideFromIndex()
                ->displayUsing(function ($user) {
                    return $user->name;
                }),

            BelongsTo::make('Reviewer', 'reviewer', User::class)
                ->searchable()
                ->exceptOnForms()
                ->nullable()
                ->hideFromIndex()
                ->displayUsing(function ($user) {
                    return $user->name;
                }),

            Select::make('Reviewer', 'reviewer_id')
                ->options(\App\Models\User::whereHas('roles', function ($roles) {
                    return $roles->whereIn('name', ['Appraiser']);
                })->pluck('name', 'id')->toArray())
                ->rules('nullable', 'exists:users,id')
                ->nullable()
                ->onlyOnForms()
                ->searchable()
                ->displayUsingLabels(),

            Text::make('Lender')
                ->hideFromIndex(),

            Text::make('Reference Number')
                ->hideFromIndex(),

            Text::make('Applicant')
                ->hideFromIndex(),

            Text::make('Email')
                ->hideFromIndex()
                ->nullable()
                ->creationRules('nullable', 'email'),

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
                ->nullable()
                ->creationRules('nullable', 'email'),
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

            ZipCode::make('Postal Code', 'property_postal_code')
                ->hideFromIndex()
                ->setCountry('CA'),

            Text::make('Address', 'property_address')
                ->hideFromIndex(),
        ]);
    }

    public function contactInformation(): Panel
    {
        return $this->panel('Contact Information', [
            Text::make('Contact Name')
                ->nullable()
                ->rules('nullable', 'max:255')
                ->hideFromIndex(),

            PhoneNumber::make('Contact Phone')
                ->hideFromIndex()
                ->nullable()
                ->rules('nullable')
                ->countries(['CA', 'US']),
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
        return [
            (new AssignAppraiserAction())
                ->onlyOnTableRow()
                ->setModel($this->resource)
                ->confirmText(__('nova.actions.invite_user.confirm_text'))
                ->confirmButtonText(__('nova.actions.invite_user.confirm_button'))
                ->cancelButtonText(__('nova.actions.invite_user.cancel_button'))
                ->showAsButton()
                ->canSee(function () use ($request) {
                    return $request->user()->isSupervisor()
                        || $request->user()->isSuperAdmin()
                        || $request->user()->isAdmin();
                })
                ->canRun(function () use ($request) {
                    return $request->user()->isSupervisor()
                        || $request->user()->isSuperAdmin()
                        || $request->user()->isAdmin();
                }),
        ];
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
