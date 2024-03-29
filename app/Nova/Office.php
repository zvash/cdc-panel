<?php

namespace App\Nova;

use App\Nova\Metrics\CompletedJobsPerDay;
use Dniccum\PhoneNumber\PhoneNumber;
use Illuminate\Support\Facades\Log;
use Laravel\Nova\Fields\FormData;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;

class Office extends Resource
{
    /**
     * The model the resource corresponds to.
     *
     * @var class-string<\App\Models\Office>
     */
    public static $model = \App\Models\Office::class;

    /**
     * The single value that should be used to represent the resource when being displayed.
     *
     * @var string
     */
    public static $title = 'title';

    /**
     * The columns that should be searched.
     *
     * @var array
     */
    public static $search = [
        'address',
        'province',
        'city',
        'title',
    ];

    public static function redirectAfterCreate(NovaRequest $request, $resource)
    {
        return '/resources/' . static::uriKey();
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

            Select::make('Province')
                ->searchable()
                ->required()
                ->options(\App\Models\Province::pluck('name', 'name'))
                ->displayUsingLabels(),

            Select::make('City')
                ->searchable()
                ->required()
                ->dependsOn(['province'], function (Select $field, NovaRequest $request, FormData $formData) {
                    if ($formData->province) {
                        $field->options(
                            \App\Models\City::where('province_id', \App\Models\Province::where('name', $formData->province)
                                ->first()->id)->pluck('name', 'name')
                        );
                    } else {
                        $field->options([]);
                    }
                })
                ->displayUsingLabels(),

            Text::make('Address')
                ->required(),

            PhoneNumber::make('Phone')
                ->disableValidation()
                ->rules('nullable')
                ->nullable(),

            Text::make('Email')
                ->rules('nullable', 'email')
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
//        $provinces = \App\Models\Province::query()
//            ->whereRaw('name in (select province from offices)')
//            ->pluck('name', 'id');
//        $completedPerProvinces = [];
//        foreach ($provinces as $provinceId => $provinceName) {
//            $completedPerProvinces[] = (new CompletedJobsPerDay())
//                ->width('1/3')
//                ->setProvince($provinceId, $provinceName)
//                ->canSee(function ($request) {
//                    return $request->user()->hasManagementAccess();
//                })
//                ->defaultRange('7');
//        }
        return array_merge([
            (new CompletedJobsPerDay())
                ->width('2/3')
                ->setSource('office_id')
                ->defaultRange('7')
                ->onlyOnDetail(),
        ], []);
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
