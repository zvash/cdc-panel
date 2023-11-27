<?php

namespace App\Nova\Actions;

use App\Models\User;
use App\Models\Invitation;
use Dniccum\PhoneNumber\PhoneNumber;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Collection;
use Konsulting\NovaActionButtons\ShowAsButton;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;

class InviteUserAction extends Action
{
    use InteractsWithQueue, Queueable, ShowAsButton;

    /**
     * @var \App\Models\User
     */
    protected $inviter = null;

    /**
     * @param User $inviter
     * @return InviteUserAction
     */
    public function setInviter(\App\Models\User $inviter)
    {
        $this->inviter = $inviter;
        return $this;
    }

    /**
     * Perform the action on the given models.
     *
     * @param \Laravel\Nova\Fields\ActionFields $fields
     * @param \Illuminate\Support\Collection $models
     * @return mixed
     */
    public function handle(ActionFields $fields, Collection $models): mixed
    {
        $user = User::query()->where('email', $fields->email)->first();
        $invitation = Invitation::query()->where('email', $fields->email)->first();
        if ($user || $invitation) {
            return Action::danger('User with this email is already invited.');
        }
        $invitation = new Invitation();
        $invitation->email = $fields->email;
        $invitation->role = $fields->role;
        if ($fields->office != 0) {
            $invitation->office_id = $fields->office;
        }

        $invitation->capacity = $fields->capacity ?? 0;
        $invitation->phone = $fields->phone ?? null;
        $invitation->pin = $fields->pin ?? null;
        $invitation->title = $fields->title ?? null;
        $invitation->designation = $fields->designation ?? null;
        $invitation->commission = $fields->commission ?? null;
        $invitation->reviewer_commission = $fields->reviewer_commission ?? null;
        $invitation->gst_number = $fields->gst_number ?? null;

        $invitation->token = $this->createSixDigitRandomToken();
        $invitation->invited_by = $this->inviter->id;
        $invitation->save();
        return Action::message('Invitation was sent.');
    }

    /**
     * Get the fields available on the action.
     *
     * @param \Laravel\Nova\Http\Requests\NovaRequest $request
     * @return array
     */
    public function fields(NovaRequest $request): array
    {
        $invitableRoles = [];
        if ($request->user()->isSupervisor()) {
            $invitableRoles = [
                'SuperAdmin' => 'SuperAdmin',
                'Admin' => 'Admin',
                'Appraiser' => 'Appraiser',
            ];
        } else if ($request->user()->isSuperAdmin()) {
            $invitableRoles = [
                'Admin' => 'Admin',
                'Appraiser' => 'Appraiser',
            ];
        } else if ($request->user()->isAdmin()) {
            $invitableRoles = [
                'Appraiser' => 'Appraiser',
            ];
        }
        $offices = [
            0 => 'None',
        ];
        $offices = array_merge($offices, \App\Models\Office::query()->pluck('city', 'id')->toArray());
        return [
            Text::make('Email')
                ->rules('required', 'email', 'max:254')
                ->required(),

            Select::make('Role')
                ->options($invitableRoles)
                ->rules('required', 'in:' . implode(',', array_keys($invitableRoles)))
                ->required(),

            Select::make('Office')
                ->options($offices)
                ->rules('required', 'in:' . implode(',', array_keys($offices)))
                ->required(),

            Number::make('Capacity')
                ->min(0)
                ->max(50)
                ->default(10)
                ->nullable(),

            PhoneNumber::make('Phone')
                ->countries(['CA', 'US'])
                ->rules('nullable')
                ->nullable(),

            Text::make('Pin')
                ->rules('nullable', 'digits_between:3,6')
                ->nullable(),

            Text::make('Title(s)', 'title')
                ->rules('nullable', 'max:255')
                ->nullable(),

            Text::make('Designation(s)', 'designation')
                ->rules('nullable', 'max:255')
                ->nullable(),

            Number::make('Commission (%)', 'commission')
                ->rules('nullable', 'numeric', 'min:0', 'max:100')
                ->min(0)
                ->max(100)
                ->nullable(),

            Number::make('Reviewer Commission (%)', 'reviewer_commission')
                ->rules('nullable', 'numeric', 'min:0', 'max:100')
                ->min(0)
                ->max(100)
                ->nullable(),

            Text::make('GST Number', 'gst_number')
                ->rules('nullable', 'max:255')
                ->nullable(),
        ];
    }

    private function createSixDigitRandomToken(): string
    {
        return mt_rand(100000, 999999) . "";
    }
}
