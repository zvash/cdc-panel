<?php

namespace App\Nova\Actions;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Konsulting\NovaActionButtons\ShowAsButton;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Http\Requests\NovaRequest;

class Paid extends Action
{
    use InteractsWithQueue, Queueable, ShowAsButton;

    public function name()
    {
        return 'Paid';
    }

    /**
     * Perform the action on the given models.
     *
     * @param  \Laravel\Nova\Fields\ActionFields  $fields
     * @param  \Illuminate\Support\Collection  $models
     * @return mixed
     */
    public function handle(ActionFields $fields, Collection $models)
    {
        Log::info('req', [request()->resources]);
        $resources = explode(',', request()->resources);
        if (!$resources) {
            return Action::danger('Could not find any linked job for the selected appraiser.');
        }
        $appraisers = [];
        $reviewers = [];
        foreach ($resources as $resource) {
            $this->fillWithNumericPart($resource, $appraisers, $reviewers);
        }
        $count = 0;
        if ($appraisers) {
            $count += \App\Models\AppraisalJob::query()
                ->whereNull('appraiser_paid_at')
                ->whereIn('id', $appraisers)
                ->update([
                    'appraiser_paid_at' => \Carbon\Carbon::now(),
                ]);
        }
        if ($reviewers) {
            $count += \App\Models\AppraisalJob::query()
                ->whereNull('reviewer_paid_at')
                ->whereIn('id', $reviewers)
                ->update([
                    'reviewer_paid_at' => \Carbon\Carbon::now(),
                ]);
        }
        return Action::message($count . ' appraisal job(s) are marked as paid for selected item(s).');
    }

    /**
     * Get the fields available on the action.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return array
     */
    public function fields(NovaRequest $request)
    {
        return [];
    }

    private function fillWithNumericPart(string $combination, array &$appraisers, &$reviewers)
    {
        if (Str::endsWith($combination, 'A')) {
            $appraisers[] = intval($combination);
        } else if (Str::endsWith($combination, 'R')) {
            $reviewers[] = intval($combination);
        }
    }
}
