<?php

namespace App\Nova;

use App\Nova\Actions\InviteUserAction;
use App\Traits\NovaResource\LimitsIndexQuery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Nova\Fields\Avatar;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\BelongsToMany;
use Laravel\Nova\Fields\FormData;
use Laravel\Nova\Fields\HasMany;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\MorphToMany;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Password;
use Laravel\Nova\Fields\PasswordConfirmation;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;
use Dniccum\PhoneNumber\PhoneNumber;
use Outl1ne\MultiselectField\Multiselect;
use Laravel\Nova\Panel;

class User extends Resource
{
    use LimitsIndexQuery;

    /**
     * The model the resource corresponds to.
     *
     * @var class-string<\App\Models\User>
     */
    public static $model = \App\Models\User::class;

    /**
     * The columns that should be searched.
     *
     * @var array
     */
    public static $search = [
        'id',
        'name',
        'email',
        'phone',
    ];

    /**
     * Get the value that should be displayed to represent the resource.
     *
     * @return string
     */
    public function title()
    {
        return ucwords($this->name);
    }

    /**
     * Get the search result subtitle for the resource.
     *
     * @return string
     */
    public function subtitle()
    {
        if ($this->isAppraiser()) {
            return $this->remaining_capacity;
        }
        return strtolower($this->email);
    }

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
     * @param NovaRequest $request
     * @return array
     */
    public function fields(NovaRequest $request)
    {
        return [
            $this->properties(),
            $this->panel('Info', $this->userInformation($request)),
            //$this->panel('Password', $this->password($request)),
            $this->panel('Reviewers', $this->reviewers($request)),
            $this->panel('Preferred Appraisal Types', $this->preferredAppraisalJobTypes()),
            $this->panel('Relations', $this->relations()),
        ];
    }

    protected function properties()
    {
        return $this->merge([
            ID::make()->sortable(),

            Avatar::make('Avatar')
                ->disk('public')
                ->path('users')
                ->prunable()
                ->deletable()
                ->squared()
                ->help(__('nova.fields.common.image', [
                    'mimes' => 'jpeg, jpg, png',
                    'dimension' => 'For better UX, Image ratio should be 1:1',
                ]))
                ->rules('nullable', 'mimes:jpeg,jpg,png'),

            Text::make('Name')
                ->sortable()
                ->rules('required', 'max:255'),

            Text::make('Email')
                ->sortable()
                ->rules('required', 'email', 'max:254')
                ->readonly(function (NovaRequest $request) {
                    return !$request->user()->isSuperAdmin() && !$request->user()->isSupervisor();
                })
                ->displayUsing(function ($value) {
                    return Str::limit($value, 50, '...');
                })
                ->creationRules('unique:users,email')
                ->updateRules('unique:users,email,{{resourceId}}'),

            Text::make('Capacity')
                ->onlyOnIndex()
                ->displayUsing(function ($value) {
                    return $value . '';
                }),

            Number::make('Capacity')
                ->min(0)
                ->max(50)
                ->default(10)
                ->hideFromIndex()
                ->required(),
        ]);
    }

    protected function userInformation(Request $request): array
    {
        $reviewerOptions = \App\Models\User::query()
            ->where('id', '!=', $this->id)
            ->whereHas('roles', function ($query) {
                $query->where('name', 'appraiser');
            })
            ->get()
            ->groupBy('office_id')
            ->mapWithKeys(function ($users, $officeId) {
                return [$officeId => $users->pluck('name', 'id')->toArray()];
            })
            ->toArray();

        return [
            PhoneNumber::make('Phone')
                ->countries(['CA', 'US'])
                ->rules('nullable')
                ->nullable(),

            BelongsTo::make('Office')
                ->searchable()
                ->exceptOnForms()
                ->nullable(),

            Select::make('Office', 'office_id')
                ->searchable()
                ->onlyOnForms()
                ->options(\App\Models\Office::pluck('city', 'id'))
                ->nullable(),

            MultiSelect::make('Reviewers', 'reviewers')
                ->placeholder(' ')
                ->saveAsJSON()
                ->options(\App\Models\User::query()
                    ->whereHas('roles', function ($query) {
                        $query->where('name', 'appraiser');
                    })->pluck('name', 'id'))
                ->dependsOn(['office_id'], function (MultiSelect $field, NovaRequest $request, FormData $formData) use ($reviewerOptions) {
                    if (array_key_exists($formData->office_id, $reviewerOptions)) {
                        Log::info('Found', $reviewerOptions[$formData->office_id]);
                        $field->options($reviewerOptions[$formData->office_id]);
                    } else {
                        $field->options([]);
                    }
                })
//                ->hideWhenUpdating(function (NovaRequest $request) {
//                    return !$request->user()->isSupervisor()
//                        && !$request->user()->isSuperAdmin()
//                        && !$request->user()->isAdmin();
//                })
                ->max(1)
                ->hideFromIndex(),

            Text::make('Pin')
                ->rules('nullable', 'digits_between:3,6')
                ->hideFromIndex()
                ->nullable(),

            Text::make('Title(s)', 'title')
                ->rules('nullable', 'max:255')
                ->hideFromIndex()
                ->nullable(),

            Text::make('Designation(s)', 'designation')
                ->rules('nullable', 'max:255')
                ->hideFromIndex()
                ->nullable(),

            Number::make('Commission (%)', 'commission')
                ->rules('nullable', 'numeric', 'min:0', 'max:100')
                ->min(0)
                ->max(100)
                ->hideFromIndex()
                ->nullable(),

            Number::make('Reviewer Commission (%)', 'reviewer_commission')
                ->rules('nullable', 'numeric', 'min:0', 'max:100')
                ->min(0)
                ->max(100)
                ->hideFromIndex()
                ->nullable(),

            Text::make('GST Number', 'gst_number')
                ->rules('nullable', 'max:255')
                ->hideFromIndex()
                ->nullable(),
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
        return [
//            (new InviteUserAction())
//                ->setInviter($request->user())
//                ->confirmText(__('nova.actions.invite_user.confirm_text'))
//                ->confirmButtonText(__('nova.actions.invite_user.confirm_button'))
//                ->cancelButtonText(__('nova.actions.invite_user.cancel_button'))
//                ->standalone()
//                ->showAsButton()
//                ->canSee(function () use ($request) {
//                    return $request->user()->isSupervisor()
//                        || $request->user()->isSuperAdmin()
//                        || $request->user()->isAdmin();
//                })
//                ->canRun(function () use ($request) {
//                    return $request->user()->isSupervisor()
//                        || $request->user()->isSuperAdmin()
//                        || $request->user()->isAdmin();
//                }),
        ];
    }

    /**
     * Resource password.
     *
     * @param \Illuminate\Http\Request $request
     * @return array
     */
    protected function password(Request $request)
    {
        return [
            Password::make('Password')
                ->onlyOnForms()
                ->required($request->isCreateOrAttachRequest())
                ->creationRules('required')
                ->updateRules('nullable')
                ->rules('string', 'min:8', 'confirmed'),

            PasswordConfirmation::make('Password Confirmation')
                ->required($request->isCreateOrAttachRequest()),
        ];
    }

    protected function preferredAppraisalJobTypes()
    {
        $appraisalTypes = \App\Models\AppraisalType::pluck('name', 'name');
        return array_merge([
            MultiSelect::make('Preferred Appraisal Types', 'preferred_appraisal_types')
                ->options($appraisalTypes)
                ->reorderable()
                ->hideFromIndex(),
        ]);
    }

    protected function reviewers(NovaRequest $request)
    {

        //->pluck('name', 'id');
        return array_merge([

        ]);
    }

    /**
     * Resource relations.
     *
     * @return array
     */
    protected
    function relations(): array
    {
        return array_merge([

            BelongsToMany::make('Roles')
                ->required()
                ->withSubtitles(),
        ]);
    }

    /**
     * Create a panel for collection of fields.
     *
     * @param string $key
     * @param array $fields
     * @param int|null $limit
     * @return \Laravel\Nova\Panel
     */
    protected
    function panel(string $key, array $fields, ?int $limit = null): Panel
    {
        $panel = new Panel(__($key), $fields);

        return $limit ? $panel->limit($limit) : $panel;
    }
}
