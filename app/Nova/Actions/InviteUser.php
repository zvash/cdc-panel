<?php

namespace App\Nova\Actions;

use App\Models\User;
use App\Models\Invitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Collection;
use Konsulting\NovaActionButtons\ShowAsButton;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;

class InviteUser extends Action
{
    use InteractsWithQueue, Queueable, ShowAsButton;

    /**
     * @var \App\Models\User
     */
    protected $inviter = null;

    /**
     * @param User $inviter
     * @return InviteUser
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
        if ($request->user()->isSuperAdmin()) {
            $invitableRoles = [
                'Admin' => 'Admin',
                'Appraiser' => 'Appraiser',
            ];
        } else if ($request->user()->isAdmin()) {
            $invitableRoles = [
                'Appraiser' => 'Appraiser',
            ];
        }
        return [
            Text::make('Email')
                ->rules('required', 'email', 'max:254')
                ->required(),

            Select::make('Role')
                ->options($invitableRoles)
                ->rules('required', 'in:' . implode(',', array_keys($invitableRoles)))
                ->required(),
        ];
    }

    private function createSixDigitRandomToken(): string
    {
        return mt_rand(100000, 999999) . "";
    }
}
