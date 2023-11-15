<?php

namespace App\Nova;

use App\Nova\Actions\InviteUser;
use App\Traits\NovaResource\LimitsIndexQuery;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules;
use Laravel\Nova\Fields\Avatar;
use Laravel\Nova\Fields\BelongsToMany;
use Laravel\Nova\Fields\Gravatar;
use Laravel\Nova\Fields\HasMany;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\MorphToMany;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Password;
use Laravel\Nova\Fields\PasswordConfirmation;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;
use Dniccum\PhoneNumber\PhoneNumber;
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
     * @return string|null
     */
    public function subtitle()
    {
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
     * @param \Illuminate\Http\Request $request
     * @return array
     */
    public function fields(Request $request)
    {
        return [
            $this->properties(),
            $this->panel('Password', $this->password($request)),
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
                ->creationRules('unique:users,email')
                ->updateRules('unique:users,email,{{resourceId}}'),

            Number::make('Capacity')
                ->min(0)
                ->max(50)
                ->default(10)
                ->required(),

            PhoneNumber::make('Phone')
                ->country('CA')
                ->nullable(),
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
            (new InviteUser())
                ->setInviter($request->user())
                ->confirmText(__('nova.actions.invite_user.confirm_text'))
                ->confirmButtonText(__('nova.actions.invite_user.confirm_button'))
                ->cancelButtonText(__('nova.actions.invite_user.cancel_button'))
                ->standalone()
                ->showAsButton()
                ->canSee(function () use ($request) {
                    return $request->user()->isSuperAdmin() || $request->user()->isAdmin();
                })
                ->canRun(function () use ($request) {
                    return $request->user()->isSuperAdmin() || $request->user()->isAdmin();
                }),
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

    /**
     * Resource relations.
     *
     * @return array
     */
    protected function relations(): array
    {
        return array_merge([
            MorphToMany::make('Roles')
                ->required()
                ->searchable()
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
    protected function panel(string $key, array $fields, ?int $limit = null): Panel
    {
        $panel = new Panel(__($key), $fields);

        return $limit ? $panel->limit($limit) : $panel;
    }
}
