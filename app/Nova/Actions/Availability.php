<?php

namespace App\Nova\Actions;

use Ghanem\MultipleDatePicker\MultipleDatePicker;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Konsulting\NovaActionButtons\ShowAsButton;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Http\Requests\NovaRequest;

class Availability extends Action
{
    use InteractsWithQueue, Queueable, ShowAsButton;

    public function name()
    {
        return 'Off Days';
    }

    /**
     * The name of the action.
     *
     * @var \App\Models\User $user
     */
    protected $user;

    public function setUser(\App\Models\User $user)
    {
        $this->user = $user;
        return $this;
    }

    /**
     * Perform the action on the given models.
     *
     * @param \Laravel\Nova\Fields\ActionFields $fields
     * @param \Illuminate\Support\Collection $models
     * @return mixed
     */
    public function handle(ActionFields $fields, Collection $models)
    {
        $user = \App\Models\User::query()->find($models->first()->id);
        if ($user) {
            DB::beginTransaction();
            try {
                \App\Models\UserOffDay::query()
                    ->where('user_id', $user->id)
                    ->whereDate('off_date', '>=', now()->startOfMonth())
                    ->delete();
                $dates = $fields->availability;
                foreach ($dates as $date) {
                    $date = date('Y-m-d', strtotime($date));
                    \App\Models\UserOffDay::query()->create([
                        'user_id' => $user->id,
                        'off_date' => $date
                    ]);
                }
                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();
                Log::error('Availability', [$e->getMessage()]);
                return Action::danger('Failed to update availability');
            }
            return Action::message('Availability updated successfully');
        }
        return Action::danger('User not found');
    }

    /**
     * Get the fields available on the action.
     *
     * @param \Laravel\Nova\Http\Requests\NovaRequest $request
     * @return array
     */
    public function fields(NovaRequest $request)
    {
        $dates = $this->user->offDays()
            ->where('off_date', '>=', now()->startOfMonth())
            ->get()
            ->pluck('off_date')
            ->toArray();
        return [
            MultipleDatePicker::make('Off Days', 'availability')
                ->default(function ($request) use ($dates) {
                    $asString = implode(',', array_map(function ($date) {
                        return date('d/m/Y', strtotime($date));
                    }, $dates));
                    return $asString;
                })
        ];
    }
}
