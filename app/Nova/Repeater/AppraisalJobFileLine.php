<?php

namespace App\Nova\Repeater;

use Illuminate\Support\Str;
use Laravel\Nova\Fields\File;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Repeater\Repeatable;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;

class AppraisalJobFileLine extends Repeatable
{

    public static $model = \App\Models\AppraisalJobFile::class;

    public static $icon = 'file';

    public static function key()
    {
        return 'appraisal-job-file';
    }

    public static function label()
    {
        return 'Appraisal Job File';
    }

    /**
     * Get the fields displayed by the repeatable.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @return array
     */
    public function fields(NovaRequest $request)
    {
        return [
            ID::hidden('id'),
            File::make('File')
                ->disk('local')
                ->path('appraisal-job-files')
                ->required()
                ->creationRules('required', 'file', 'mimes:pdf,doc,docx,xls,xlsx,txt,jpg,jpeg,png,webp')
                ->updateRules('nullable', 'file', 'mimes:pdf,doc,docx,xls,xlsx,txt,jpg,jpeg,png,webp'),

            Text::make('Comment')
                ->nullable()
                ->rules('nullable', 'string', 'max:255'),
        ];
    }
}
