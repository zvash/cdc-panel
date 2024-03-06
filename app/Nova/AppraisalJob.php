<?php

namespace App\Nova;

use App\Enums\AppraisalJobStatus;
use App\Models\AppraisalType;
use App\Nova\Actions\AddFile;
use App\Nova\Actions\AssignAppraiserAction;
use App\Nova\Actions\CancelAppraisalJob;
use App\Nova\Actions\DropAppraisalJob;
use App\Nova\Actions\MailCompletedJob;
use App\Nova\Actions\MarkAsCompleted;
use App\Nova\Actions\PutAppraisalJobOnHold;
use App\Nova\Actions\PutJobInReview;
use App\Nova\Actions\Reinstate;
use App\Nova\Actions\RejectAfterReview;
use App\Nova\Actions\RespondToAssignment;
use App\Nova\Actions\ResumeAppraisalJob;
use App\Nova\CustomFields\GoogleAutocompleteWithBroadcast;
use App\Nova\Lenses\AppraiserInvoice;
use App\Nova\Lenses\AppraiserMonthlyInvoice;
use App\Nova\Lenses\AssignedAppraisalJobs;
use App\Nova\Lenses\CanceledJobs;
use App\Nova\Lenses\ClientInvoice;
use App\Nova\Lenses\ClientMonthlyInvoice;
use App\Nova\Lenses\CompletedAppraisalJobs;
use App\Nova\Lenses\InProgressAppraisalJobs;
use App\Nova\Lenses\InReviewAppraisalJobs;
use App\Nova\Lenses\MonthlyRevenueInvoice;
use App\Nova\Lenses\NotAssignedAppraisalJobs;
use App\Nova\Lenses\OnHoldAppraisalJobs;
use App\Nova\Lenses\OverdueJobs;
use App\Nova\Lenses\RejectedAppraisalJobs;
use App\Nova\Lenses\ReviewerInvoice;
use App\Nova\Metrics\AverageAppraisalProcessDuration;
use App\Nova\Metrics\AverageJobCreationToCompletionDuration;
use App\Nova\Metrics\AverageReviewerProcessDuration;
use App\Nova\Metrics\AverageWorkOnJobDuration;
use App\Nova\Metrics\CompletedJobsPerDay;
use App\Nova\Metrics\JobPerStatus;
use App\Rules\EmailOrNotAvailable;
use App\Rules\PostalCodeOrNotAvailable;
use App\Traits\NovaResource\LimitsIndexQuery;
use BrandonJBegle\GoogleAutocomplete\GoogleAutocomplete;
use Dniccum\PhoneNumber\PhoneNumber;
use Ebess\AdvancedNovaMediaLibrary\Fields\Files;
use Flatroy\FieldProgressbar\FieldProgressbar;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Laravel\Nova\Fields\Badge;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\Boolean;
use Laravel\Nova\Fields\Currency;
use Laravel\Nova\Fields\Date;
use Laravel\Nova\Fields\File;
use Laravel\Nova\Fields\FormData;
use Laravel\Nova\Fields\HasMany;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Line;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Repeater;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Stack;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Textarea;
use Laravel\Nova\Http\Requests\ActionRequest;
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
        'appraisalType.name',
        'office.city',
        'property_address',
        'appraiser.name',
        'reference_number',
    ];

    public static $with = [
        'office',
        'appraiser',
        'appraisalType',
    ];

    public static function label()
    {
        return 'All Jobs';
    }

    public static function afterValidation(NovaRequest $request, $validator)
    {
        $ensureUniqueAddress = $request->get('ensure_unique_address', false);
        $address = $request->get('property_address', null);
        if ($ensureUniqueAddress && $address) {
            $jobs = \App\Models\AppraisalJob::query()->where('property_address', $address);
            if ($request->resourceId) {
                $jobs->where('id', '!=', $request->resourceId);
            }
            Log::info('count', [$jobs->count()]);
            if ($jobs->count() > 0) {
                $validator->errors()->add('property_address', 'The property address must be unique.');
            }
        }
    }

    public static function createButtonLabel()
    {
        return 'Create Job';
    }

    public static function updateButtonLabel()
    {
        return 'Update Job';
    }

    public function authorizedToUpdate(Request $request)
    {
        return auth()->user()->hasManagementAccess();
    }

    /**
     * Get the fields displayed by the resource.
     *
     * @param \Laravel\Nova\Http\Requests\NovaRequest $request
     * @return array
     */
    public function fields(NovaRequest $request): array
    {
        return [
            $this->orderInformation($request),

            $this->adminSection($request),

            $this->appraiserSection($request),

            $this->reviewerSection($request),

            $this->propertyAddress(),

            $this->paymentInformation(),

            $this->contactInformation(),

            $this->additionalInformations(),

            $this->relations(),
        ];
    }

    public function orderInformation(NovaRequest $request): Panel
    {
        return $this->panel('Order Information', [
            ID::make()->sortable()
            ->displayUsing(function ($value) {
                if (
                    $this->resource->status != AppraisalJobStatus::Completed->value
                    && $this->resource->status != AppraisalJobStatus::Cancelled->value
                    && $this->resource->due_date
                    && $this->resource->due_date->isPast()
                ) {
                    return $value . ' ❗️';
                }
                return $value;
            }),

            Select::make('Client', 'client_id')
                ->options(\App\Models\Client::pluck('name', 'id'))
                ->exceptOnForms()
                ->hideFromDetail()
                ->hideFromIndex()
                ->filterable()
                ->displayUsingLabels(),

            BelongsTo::make('Client')
                ->searchable()
                ->default(function ($request) {
                    return \App\Models\Client::query()->pluck('name', 'id')->toArray();
                })
                ->withoutTrashed()
                ->showCreateRelationButton()
                ->modalSize('3xl')
                ->displayUsing(function ($client) {
                    return $client->complete_name;
                }),

            Stack::make('Details', [
                Line::make('Property Address')
                    ->displayUsing(function ($value) {
                        return Str::limit(str_ireplace(', Canada', '', $value), 35, '...');
                    })->asHeading(),
                Line::make('Appraisal Type', 'appraisalType.name')
                    ->displayUsing(function ($value) {
                        return 'Type: ' . ($value ?? '-');
                    })->asSmall(),
                Line::make('File Number', 'reference_number')
                    ->displayUsing(function ($value) {
                        return 'File Number: ' . ($value ?? '-');
                    })->filterable()
                    ->asSmall(),
                Line::make('Due Date')
                    ->displayUsing(function ($value) {
                        return 'Due Date: ' . ($value ?? '-');
                    })->asSmall(),
            ])->onlyOnIndex(),

            Select::make('Appraisal Type', 'appraisal_type_id')
                ->options(AppraisalType::query()->orderByRaw('name != "Other" desc')->orderBy('id')->pluck('name', 'id'))
                ->required()
                ->hideFromIndex()
                ->filterable()
                ->displayUsingLabels(),

            BelongsTo::make('Appraisal Type', 'appraisalType', \App\Nova\AppraisalType::class)
                ->searchable()
                ->exceptOnForms()
                ->hideFromIndex()
                ->hideFromDetail()
                ->displayUsing(function ($appraisalType) {
                    return $appraisalType->name;
                }),

            Select::make('Office', 'office_id')
                ->options(\App\Models\Office::pluck('title', 'id'))
                ->exceptOnForms()
                ->hideFromDetail()
                ->hideFromIndex()
                ->filterable()
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

            Select::make('Province (for tax)', 'province')
                ->searchable()
                ->hideFromIndex()
                ->required()
                ->rules('required')
                ->options(\App\Models\Province::pluck('name', 'name'))
                ->displayUsingLabels(),

            Text::make('Fee Quoted')
                ->hideFromIndex()
                ->nullable()
                ->displayUsing(function ($value) {
                    return $value ? '$' . number_format($value, 2) : '-';
                })
                ->rules('nullable', 'numeric', 'min:0', 'max:999999.99'),

            //for filtering
            Select::make('Appraisers', 'appraiser_id')
                ->options(\App\Models\User::query()->whereHas('roles', function ($roles) {
                    return $roles->whereIn('name', ['Appraiser']);
                })->pluck('name', 'id')->toArray())
                ->filterable()
                ->exceptOnForms()
                ->hideFromDetail()
                ->hideFromIndex()
                ->displayUsingLabels(),

            BelongsTo::make('Appraiser', 'appraiser', User::class)
                ->searchable()
                ->exceptOnForms()
                ->displayUsing(function ($user) {
                    return $user->name;
                }),

            Text::make('Reviewer', 'appraiser_id')
                ->exceptOnForms()
                ->displayUsing(function ($value) {
                    if (!$value) {
                        return '-';
                    }
                    $reviewer = null;
                    if ($this->reviewer_id) {
                        $reviewer = \App\Models\User::query()->find($this->reviewer_id);

                    } else {
                        $reviewers = \App\Models\User::query()->find($value)?->reviewers;
                        if ($reviewers && count($reviewers) > 0) {
                            $reviewer = \App\Models\User::query()->find($reviewers[0]);
                        }
                    }
                    if ($reviewer) {
                        return "<a href='/resources/users/{$reviewer->id}' class='link-default'>{$reviewer->name}</a>";
                    }
                    return '-';
                })->asHtml(),

            Files::make('Documents', 'job_files')
                ->hideFromIndex()
                ->setFileName(function ($originalFilename, $extension, $model) {
                    return $originalFilename . '-' . time() . '.' . $extension;
                }),


            Badge::make('Status')->map([
                \App\Enums\AppraisalJobStatus::Pending->value => 'danger',
                \App\Enums\AppraisalJobStatus::Assigned->value => 'warning',
                \App\Enums\AppraisalJobStatus::InProgress->value => 'info',
                \App\Enums\AppraisalJobStatus::InReview->value => 'warning',
                \App\Enums\AppraisalJobStatus::Completed->value => 'success',
                \App\Enums\AppraisalJobStatus::Cancelled->value => 'danger',
                'On Hold' => 'warning',
            ])
                ->resolveUsing(function ($status) {
                    if ($this->is_on_hold) {
                        return 'On Hold';
                    }
                    return $status;
                })
                ->withIcons()
                ->exceptOnForms(),

            BelongsTo::make('Appraiser', 'appraiser', User::class)
                ->searchable()
                ->exceptOnForms()
                ->nullable()
                ->hideFromIndex()
                ->displayUsing(function ($user) {
                    return $user->name;
                }),

            FieldProgressbar::make('Progress')
                ->options([
                    'color' => '#40BF55',
                    'fromColor' => '#FFEA82',
                    'toColor' => '#40BF55',
                    'animateColor' => false,
                ])
                ->exceptOnForms()
                ->hideFromIndex(),

            Text::make('File Number', 'reference_number')
                ->filterable()
                ->creationRules('unique:appraisal_jobs,reference_number')
                ->updateRules('unique:appraisal_jobs,reference_number,{{resourceId}}')
                ->hideFromIndex(),

            Text::make('Lender')
                ->hideFromIndex(),

            Text::make('Applicant')
                ->hideFromIndex(),

            Text::make('Email')
                ->hideFromIndex()
                ->nullable()
                ->placeholder('N/A')
                ->creationRules('nullable', new EmailOrNotAvailable()),

            Date::make('Due Date')
                ->hideFromIndex()
                ->sortable(),

            Date::make('Created At')
                ->sortable()
                ->filterable()
                ->exceptOnForms()
                ->nullable()
                ->hideFromIndex(),
        ]);
    }

    public function adminSection(NovaRequest $request): Panel
    {
        if (!$request->user()->hasManagementAccess()) {
            return $this->panel('Admin', []);
        }
        return $this->panel('Admin', [
            Text::make('Admin Fee', 'admin_fee')
                ->hideFromIndex()
                ->nullable()
                ->displayUsing(function ($value) {
                    return $value ? '$' . number_format($value, 2) : '-';
                })
                ->rules('nullable', 'numeric', 'min:0', 'max:999999.99'),
            Text::make('Admin Fee Tax', 'admin_fee_tax')
                ->hideFromIndex()
                ->nullable()
                ->displayUsing(function ($value) {
                    return $value ? '$' . number_format($value, 2) : '-';
                })
                ->readonly()
                ->dependsOn(['admin_fee', 'province'], function ($field, NovaRequest $request, FormData $formData) {
                    if (!$formData->admin_fee || !$formData->province) {
                        $field->value = null;
                        return;
                    }
                    $provinceTax = \App\Models\ProvinceTax::query()->whereHas('province', function ($query) use ($formData) {
                        $query->where('name', $formData->province);
                    })->first();
                    $field->value = $formData->admin_fee * ($provinceTax?->total / 100);
                }),

            Text::make('Admin Fee Total', 'admin_fee_total')
                ->hideFromIndex()
                ->nullable()
                ->displayUsing(function ($value) {
                    return $value ? '$' . number_format($value, 2) : '-';
                })
                ->readonly()
                ->dependsOn(['admin_fee', 'province'], function ($field, NovaRequest $request, FormData $formData) {
                    if (!$formData->admin_fee || !$formData->province) {
                        $field->value = null;
                        return;
                    }
                    $provinceTax = \App\Models\ProvinceTax::query()->whereHas('province', function ($query) use ($formData) {
                        $query->where('name', $formData->province);
                    })->first();
                    $field->value = $formData->admin_fee * (1 + ($provinceTax?->total / 100));
                }),

            Date::make('Admin Paid At', 'admin_paid_at')
                ->hideFromIndex()
                ->nullable(),

        ]);
    }

    public function appraiserSection(): Panel
    {
        return $this->panel('Appraiser', [
            Select::make('Appraiser', 'appraiser_id')
                ->options([null => '-'] + \App\Models\User::getAllAppraisersWithRemainingCapacity()->pluck('name', 'id')->toArray())
                ->searchable()
                ->onlyOnForms()
                ->displayUsingLabels(),

            Text::make('Commission (%)', 'commission')
                ->dependsOnCreating(['appraiser_id'], function (Text $field, NovaRequest $request, FormData $formData) {
                    $appraiser = \App\Models\User::query()->find($formData->appraiser_id);
                    $resource = $formData->resource(AppraisalJob::uriKey());
                    if ($appraiser && $appraiser->commission) {
                        $field->value = $appraiser->commission;
                    } else if ($resource && $resource->commission) {
                        $field->value = $formData->commission;
                    } else {
                        $field->value = null;
                    }
                })
                ->hideFromIndex()
                ->nullable()
                ->displayUsing(function ($value) {
                    return $value ? "$value%" : '-';
                })
                ->rules('nullable', 'numeric', 'min:0', 'max:100'),

            Text::make('Appraiser Fee', 'appraiser_fee')
                ->hideFromIndex()
                ->nullable()
                ->readonly()
                ->displayUsing(function ($value) {
                    return $value ? '$' . number_format($value, 2) : '-';
                })
                ->dependsOn(['fee_quoted', 'commission'], function ($field, NovaRequest $request, FormData $formData) {
                    if (!$formData->fee_quoted || !$formData->commission) {
                        $field->value = null;
                        return;
                    }
                    $field->value = $formData->fee_quoted * ($formData->commission / 100);
                }),

            Text::make('Appraiser Fee Tax', 'appraiser_fee_tax')
                ->hideFromIndex()
                ->nullable()
                ->readonly()
                ->displayUsing(function ($value) {
                    return $value ? '$' . number_format($value, 2) : '-';
                })
                ->dependsOn(['fee_quoted', 'commission', 'province'], function ($field, NovaRequest $request, FormData $formData) {
                    if (!$formData->fee_quoted || !$formData->commission || !$formData->province) {
                        $field->value = null;
                        return;
                    }
                    $provinceTax = \App\Models\ProvinceTax::query()->whereHas('province', function ($query) use ($formData) {
                        $query->where('name', $formData->province);
                    })->first();
                    $field->value = $formData->fee_quoted * ($formData->commission / 100) * ($provinceTax?->total / 100);
                }),

            Text::make('Appraiser Fee Total', 'appraiser_fee_total')
                ->hideFromIndex()
                ->nullable()
                ->readonly()
                ->displayUsing(function ($value) {
                    return $value ? '$' . number_format($value, 2) : '-';
                })
                ->dependsOn(['fee_quoted', 'commission', 'province'], function ($field, NovaRequest $request, FormData $formData) {
                    if (!$formData->fee_quoted || !$formData->commission || !$formData->province) {
                        $field->value = null;
                        return;
                    }
                    $provinceTax = \App\Models\ProvinceTax::query()->whereHas('province', function ($query) use ($formData) {
                        $query->where('name', $formData->province);
                    })->first();
                    $field->value = $formData->fee_quoted * ($formData->commission / 100) * (1 + ($provinceTax?->total / 100));
                }),

            Date::make('Appraiser Paid At', 'appraiser_paid_at')
                ->hideFromIndex()
                ->readonly()
                ->nullable(),
        ]);
    }

    public function reviewerSection(): Panel
    {
        return $this->panel('Reviewer', [
            Select::make('Reviewer', 'reviewer_id')
                ->options([null => '-'] + \App\Models\User::query()->whereHas('roles', function ($roles) {
                        return $roles->whereIn('name', ['Appraiser']);
                    })->pluck('name', 'id')->toArray())
                ->searchable()
                ->onlyOnForms()
                ->displayUsingLabels(),

            Text::make('Reviewer Commission (%)', 'reviewer_commission')
                ->dependsOnCreating(['reviewer_id'], function (Text $field, NovaRequest $request, FormData $formData) {
                    $appraiser = \App\Models\User::query()->find($formData->reviewer_id);
                    $resource = $formData->resource(AppraisalJob::uriKey());
                    if ($appraiser && $appraiser->reviewer_commission) {
                        $field->value = $appraiser->reviewer_commission;
                    } else if ($resource && $resource->reviewer_commission) {
                        $field->value = $resource->reviewer_commission;
                    } else {
                        $field->value = 10;
                    }
                })
                ->hideFromIndex()
                ->nullable()
                ->displayUsing(function ($value) {
                    return $value ? "$value%" : '-';
                })
                ->rules('nullable', 'numeric', 'min:0', 'max:100.00'),

            Text::make('Reviewer Fee', 'reviewer_fee')
                ->hideFromIndex()
                ->nullable()
                ->readonly()
                ->displayUsing(function ($value) {
                    return $value ? '$' . number_format($value, 2) : '-';
                })
                ->dependsOn(['fee_quoted', 'reviewer_commission'], function ($field, NovaRequest $request, FormData $formData) {
                    if (!$formData->fee_quoted || !$formData->reviewer_commission) {
                        $field->value = null;
                        return;
                    }
                    $field->value = $formData->fee_quoted * ($formData->reviewer_commission / 100);
                }),

            Text::make('Reviewer Fee Tax', 'reviewer_fee_tax')
                ->hideFromIndex()
                ->nullable()
                ->readonly()
                ->displayUsing(function ($value) {
                    return $value ? '$' . number_format($value, 2) : '-';
                })
                ->dependsOn(['fee_quoted', 'reviewer_commission', 'province'], function ($field, NovaRequest $request, FormData $formData) {
                    if (!$formData->fee_quoted || !$formData->reviewer_commission || !$formData->province) {
                        $field->value = null;
                        return;
                    }
                    $provinceTax = \App\Models\ProvinceTax::query()->whereHas('province', function ($query) use ($formData) {
                        $query->where('name', $formData->province);
                    })->first();
                    $field->value = $formData->fee_quoted * ($formData->reviewer_commission / 100) * ($provinceTax?->total / 100);
                }),

            Text::make('Reviewer Fee Total', 'reviewer_fee_total')
                ->hideFromIndex()
                ->nullable()
                ->readonly()
                ->displayUsing(function ($value) {
                    return $value ? '$' . number_format($value, 2) : '-';
                })
                ->dependsOn(['fee_quoted', 'reviewer_commission', 'province'], function ($field, NovaRequest $request, FormData $formData) {
                    if (!$formData->fee_quoted || !$formData->reviewer_commission || !$formData->province) {
                        $field->value = null;
                        return;
                    }
                    $provinceTax = \App\Models\ProvinceTax::query()->whereHas('province', function ($query) use ($formData) {
                        $query->where('name', $formData->province);
                    })->first();
                    $field->value = $formData->fee_quoted * ($formData->reviewer_commission / 100) * (1 + ($provinceTax?->total / 100));
                }),

            Date::make('Reviewer Paid At', 'reviewer_paid_at')
                ->hideFromIndex()
                ->readonly()
                ->nullable(),
        ]);
    }

    public function paymentInformation(): Panel
    {
        return $this->panel('Payment Information', [


            Select::make('Payment Terms')
                ->options(\App\Enums\PaymentTerm::array())
                ->hideFromIndex()
                ->displayUsingLabels(),

            Select::make('Payment', 'payment_status')
                ->options(array_flip(\App\Enums\PaymentStatus::array()))
                ->default(\App\Enums\PaymentStatus::Unpaid->value)
                ->displayUsingLabels(),

            Text::make('Invoice Name')
                ->hideFromIndex(),

            Text::make('Invoice Email')
                ->hideFromIndex()
                ->nullable()
                ->placeholder('N/A')
                ->creationRules('nullable', new EmailOrNotAvailable()),

            Text::make('Payment Link')
                ->hideFromIndex()
                ->nullable()
                ->rules('nullable', 'url')
                ->displayUsing(function ($value) {
                    return "<a href='$value' target='_blank'>$value</a>";
                })->asHtml(),
        ]);
    }

    public function propertyAddress(): Panel
    {
        return $this->panel('Property Address', [
//            Select::make('Property Province')
//                ->searchable()
//                ->hideFromIndex()
//                ->options(\App\Models\Province::pluck('name', 'name'))
//                ->required()
//                ->rules('required')
//                ->displayUsingLabels(),
//
//            Select::make('Property City')
//                ->searchable()
//                ->hideFromIndex()
//                ->dependsOn(['property_province'], function (Select $field, NovaRequest $request, FormData $formData) {
//                    if ($formData->property_province) {
//                        $field->options(
//                            \App\Models\City::where('province_id', \App\Models\Province::where('name', $formData->property_province)
//                                ->first()->id)->pluck('name', 'name')
//                        );
//                    } else {
//                        $field->options([]);
//                    }
//                })
//                ->required()
//                ->rules('required')
//                ->displayUsingLabels(),

            GoogleAutocomplete::make('Property Address', 'property_address')
                ->countries('CA')
                ->required()
                ->rules('required')
                ->hideFromIndex(),

            Boolean::make('Ensure Unique Address', 'ensure_unique_address')
                ->hideFromIndex()
                ->hideFromDetail()
                ->nullable()
                ->default(true),

//            Text::make('Postal Code', 'property_postal_code')
//                ->rules('nullable', new PostalCodeOrNotAvailable())
//                ->nullable()
//                ->hideFromIndex(),
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
                ->disableValidation()
                ->hideFromIndex()
                ->nullable()
                ->rules('nullable'),
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
        return [

        ];
    }

    /**
     * Get the lenses available for the resource.
     *
     * @param \Laravel\Nova\Http\Requests\NovaRequest $request
     * @return array
     */
    public function lenses(NovaRequest $request)
    {
        return [
            (new AssignedAppraisalJobs($this->resource)),
            (new NotAssignedAppraisalJobs($this->resource))
                ->canSee(function () use ($request) {
                    return $request->user()->hasManagementAccess();
                }),
            (new RejectedAppraisalJobs($this->resource))
                ->canSee(function () use ($request) {
                    return $request->user()->hasManagementAccess();
                }),
            (new InProgressAppraisalJobs($this->resource)),
            (new InReviewAppraisalJobs($this->resource)),
            (new OnHoldAppraisalJobs($this->resource)),
            (new CompletedAppraisalJobs($this->resource)),
            (new CanceledJobs($this->resource)),
            (new OverdueJobs($this->resource)),
            (new AppraiserInvoice($this->resource)),
            (new ReviewerInvoice($this->resource)),
            (new ClientInvoice($this->resource)),
            (new AppraiserMonthlyInvoice($this->resource)),
            (new ClientMonthlyInvoice($this->resource))->canSee(function () use ($request) {
                return $request->user()->hasManagementAccess();
            }),
            (new MonthlyRevenueInvoice($this->resource))->canSee(function () use ($request) {
                return $request->user()->isSuperAdmin() || $request->user()->isSupervisor();
            }),
        ];
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
                ->exceptOnIndex()
                ->setModel($this->resource)
                ->confirmText(__('nova.actions.assign_appraiser.confirm_text'))
                ->confirmButtonText(__('nova.actions.assign_appraiser.confirm_button'))
                ->cancelButtonText(__('nova.actions.assign_appraiser.cancel_button'))
                ->showAsButton()
                ->canSee(function () use ($request) {
                    return false && $this->userCanAssignJob($request);
                })
                ->canRun(function () use ($request) {
                    return false && $this->userCanAssignJob($request);
                }),

            (new RespondToAssignment())
                ->exceptOnIndex()
                ->setModel($this->resource)
                ->confirmText(__('nova.actions.respond_to_assignment.confirm_text'))
                ->confirmButtonText(__('nova.actions.respond_to_assignment.confirm_button'))
                ->cancelButtonText(__('nova.actions.respond_to_assignment.cancel_button'))
                ->showAsButton()
                ->canSee(function () use ($request) {
                    return $this->appraiserCanRespondToCurrentAssignment($request);
                })
                ->canRun(function () use ($request) {
                    return $this->appraiserCanRespondToCurrentAssignment($request);
                }),
            (new PutAppraisalJobOnHold())
                ->exceptOnIndex()
                ->setModel($this->resource)
                ->showAsButton()
                ->confirmText(__('nova.actions.put_on_hold.confirm_text'))
                ->confirmButtonText(__('nova.actions.put_on_hold.confirm_button'))
                ->cancelButtonText(__('nova.actions.put_on_hold.cancel_button'))
                ->canSee(function () use ($request) {
                    return $this->userCanPutTheJobOnHold($request);
                })
                ->canRun(function () use ($request) {
                    return $this->userCanPutTheJobOnHold($request);
                }),
            (new ResumeAppraisalJob())
                ->exceptOnIndex()
                ->showAsButton()
                ->confirmText(__('nova.actions.resume_job.confirm_text'))
                ->confirmButtonText(__('nova.actions.resume_job.confirm_button'))
                ->cancelButtonText(__('nova.actions.resume_job.cancel_button'))
                ->canSee(function () use ($request) {
                    if ($request instanceof ActionRequest) {
                        return true;
                    }
                    return $request->user()->hasManagementAccess()
                        && in_array($this->resource->status, [
                            \App\Enums\AppraisalJobStatus::Pending->value,
                            \App\Enums\AppraisalJobStatus::Assigned->value,
                            \App\Enums\AppraisalJobStatus::InProgress->value,
                            \App\Enums\AppraisalJobStatus::InReview->value,
                        ])
                        && $this->resource->is_on_hold == true;
                })
                ->canRun(function () use ($request) {
                    if ($request instanceof ActionRequest) {
                        return true;
                    }
                    return $request->user()->hasManagementAccess()
                        && in_array($this->resource->status, [
                            \App\Enums\AppraisalJobStatus::Pending->value,
                            \App\Enums\AppraisalJobStatus::Assigned->value,
                            \App\Enums\AppraisalJobStatus::InProgress->value,
                            \App\Enums\AppraisalJobStatus::InReview->value,
                        ])
                        && $this->resource->is_on_hold == true;
                }),
            (new AddFile())
                ->exceptOnIndex()
                ->confirmText(__('nova.actions.add_file.confirm_text'))
                ->confirmButtonText(__('nova.actions.add_file.confirm_button'))
                ->cancelButtonText(__('nova.actions.add_file.cancel_button'))
                ->showAsButton()
                ->canSee(function () use ($request) {
                    return $this->userCanAddFileToJob($request)
                        || $this->userIsTheJobsReviewerAndJobIsInReview($request);
                })
                ->canRun(function () use ($request) {
                    return $this->userCanAddFileToJob($request)
                        || $this->userIsTheJobsReviewerAndJobIsInReview($request);
                }),
            (new MarkAsCompleted())
                ->exceptOnIndex()
                ->confirmText(__('nova.actions.mark_job_as_completed.confirm_text'))
                ->confirmButtonText(__('nova.actions.mark_job_as_completed.confirm_button'))
                ->cancelButtonText(__('nova.actions.mark_job_as_completed.cancel_button'))
                ->showAsButton()
                ->canSee(function () use ($request) {
                    return $this->jobCanBeMarkedAsCompletedByCurrentUser($request);
                })
                ->canRun(function () use ($request) {
                    return $this->jobCanBeMarkedAsCompletedByCurrentUser($request);
                }),

            (new RejectAfterReview())
                ->exceptOnIndex()
                ->confirmText(__('nova.actions.reject_job.confirm_text'))
                ->confirmButtonText(__('nova.actions.reject_job.confirm_button'))
                ->cancelButtonText(__('nova.actions.reject_job.cancel_button'))
                ->showAsButton()
                ->canSee(function () use ($request) {
                    return $this->userCanRejectJob($request);
                })
                ->canRun(function () use ($request) {
                    return $this->userCanRejectJob($request);
                }),

            (new PutJobInReview())
                ->exceptOnIndex()
                ->confirmText(__('nova.actions.put_job_in_review.confirm_text'))
                ->confirmButtonText(__('nova.actions.put_job_in_review.confirm_button'))
                ->cancelButtonText(__('nova.actions.put_job_in_review.cancel_button'))
                ->showAsButton()
                ->canSee(function () use ($request) {
                    return $this->userIsTheJobsAppraiserAndNextStepIsInReview($request);
                })
                ->canRun(function () use ($request) {
                    return $this->userIsTheJobsAppraiserAndNextStepIsInReview($request);
                }),
            (new DropAppraisalJob())
                ->exceptOnIndex()
                ->confirmText(__('nova.actions.drop_job.confirm_text'))
                ->confirmButtonText(__('nova.actions.drop_job.confirm_button'))
                ->cancelButtonText(__('nova.actions.drop_job.cancel_button'))
                ->showAsButton()
                ->canSee(function () use ($request) {
                    return $this->userIsTheJobsAppraiserAndJobIsNotCompleted($request);
                })
                ->canRun(function () use ($request) {
                    return $this->userIsTheJobsAppraiserAndJobIsNotCompleted($request);
                }),
            (new CancelAppraisalJob())
                ->exceptOnIndex()
                ->confirmText(__('nova.actions.cancel_job.confirm_text'))
                ->confirmButtonText(__('nova.actions.cancel_job.confirm_button'))
                ->cancelButtonText(__('nova.actions.cancel_job.cancel_button'))
                ->showAsButton()
                ->canSee(function () use ($request) {
                    return $this->jobCanBeMarkedAsCancelledByCurrentUser($request);
                })
                ->canRun(function () use ($request) {
                    return $this->jobCanBeMarkedAsCancelledByCurrentUser($request);
                }),

            (new Reinstate())
                ->exceptOnIndex()
                ->confirmText(__('nova.actions.reinstate.confirm_text'))
                ->confirmButtonText(__('nova.actions.reinstate.confirm_button'))
                ->cancelButtonText(__('nova.actions.reinstate.cancel_button'))
                ->showAsButton()
                ->canSee(function () use ($request) {
                    return $this->canReinstateJobByCurrentUser($request);
                })
                ->canRun(function () use ($request) {
                    return $this->canReinstateJobByCurrentUser($request);
                }),

            (new MailCompletedJob($this->resource))
                ->onlyOnDetail()
                ->confirmText(__('nova.actions.send_job_with_mail.confirm_text'))
                ->confirmButtonText(__('nova.actions.send_job_with_mail.confirm_button'))
                ->cancelButtonText(__('nova.actions.send_job_with_mail.cancel_button'))
                ->showAsButton()
                ->canSee(function () use ($request) {
                    return $this->jobIsCompleted($request);
                })
                ->canRun(function () use ($request) {
                    return $this->jobIsCompleted($request);
                }),
        ];
    }

    protected function relations(): Panel
    {
        return $this->panel('Relations', [
            HasMany::make('Files', 'files', AppraisalJobFile::class),

            HasMany::make('Rejections', 'rejections', AppraisalJobRejection::class),

            HasMany::make('Change Logs', 'changeLogs', AppraisalJobChangeLog::class),
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

    /**
     * @param NovaRequest $request
     * @return bool
     */
    private function userCanAddFileToJob(NovaRequest $request): bool
    {
        if ($request instanceof ActionRequest) {
            return true;
        }
        $user = $request->user();

        return $user->isAppraiser()
            && !$this->resource->is_on_hold
            && $this->resource->status == \App\Enums\AppraisalJobStatus::InProgress->value
            && $this->resource->appraiser_id == $user->id;

//        return ($user->hasManagementAccess()
//                && in_array($this->resource->status, [
//                    \App\Enums\AppraisalJobStatus::Pending->value,
//                    \App\Enums\AppraisalJobStatus::Assigned->value,
//                ]))
//            || ($user->isAppraiser()
//                && !$this->resource->is_on_hold
//                && $this->resource->status == \App\Enums\AppraisalJobStatus::InProgress->value
//                && $this->resource->appraiser_id == $user->id);
    }

    private function userIsTheJobsReviewerAndJobIsInReview(NovaRequest $request): bool
    {
        if ($request instanceof ActionRequest) {
            return true;
        }
        if ($this->resource->status == \App\Enums\AppraisalJobStatus::Cancelled->value) {
            return false;
        }
        $user = $request->user();
        return $user->isAppraiser()
            && !$this->resource->is_on_hold
            && $this->resource->status == \App\Enums\AppraisalJobStatus::InReview->value
            && $user->id == $this->resource->inferReviewer();
    }

    /**
     * @param NovaRequest $request
     * @return bool
     */
    private function userIsTheJobsAppraiserAndNextStepIsInReview(NovaRequest $request): bool
    {
        if ($request instanceof ActionRequest) {
            return true;
        }
        if ($this->resource->status == \App\Enums\AppraisalJobStatus::Cancelled->value) {
            return false;
        }
        $user = $request->user();
        return $user->isAppraiser()
            && !$this->resource->is_on_hold
            && $this->resource->nextValidStatus() == \App\Enums\AppraisalJobStatus::InReview
            && $this->resource->appraiser_id == $user->id;
    }

    /**
     * @param NovaRequest $request
     * @return bool
     */
    private function userIsTheJobsAppraiserAndJobIsNotCompleted(NovaRequest $request): bool
    {
        if ($request instanceof ActionRequest) {
            return true;
        }
        $user = $request->user();
        return $user->isAppraiser()
            && $this->resource->status != \App\Enums\AppraisalJobStatus::Completed->value
            && $this->resource->status != \App\Enums\AppraisalJobStatus::Cancelled->value
            && $this->resource->appraiser_id == $user->id;
    }

    /**
     * @param NovaRequest $request
     * @return bool
     */
    private function jobCanBeMarkedAsCompletedByCurrentUser(NovaRequest $request): bool
    {
        if ($request instanceof ActionRequest) {
            return true;
        }
        if ($this->resource->status == \App\Enums\AppraisalJobStatus::Cancelled->value) {
            return false;
        }
        $user = $request->user();
        $appraiser = \App\Models\User::query()->find($this->resource->appraiser_id);
        return $user->isAppraiser()
            && !$this->resource->is_on_hold
            && $this->resource->nextValidStatus() == \App\Enums\AppraisalJobStatus::Completed
            && (
                ($this->resource->appraiser_id == $user->id && !$user->reviewers)
                || ($appraiser && $appraiser->reviewers && in_array($user->id, $appraiser->reviewers))
            );
    }

    /**
     * @param NovaRequest $request
     * @return bool
     */
    private function jobCanBeMarkedAsCancelledByCurrentUser(NovaRequest $request): bool
    {
        if ($request instanceof ActionRequest) {
            return true;
        }
        $user = $request->user();
        $user = \App\Models\User::query()->find($user->id);
        return $this->resource->status != AppraisalJobStatus::Cancelled->value && $user->hasManagementAccess();
    }

    /**
     * @param NovaRequest $request
     * @return bool
     */
    private function canReinstateJobByCurrentUser(NovaRequest $request): bool
    {
        if ($request instanceof ActionRequest) {
            return true;
        }
        $user = $request->user();
        $user = \App\Models\User::query()->find($user->id);
        return $this->resource->status == AppraisalJobStatus::Cancelled->value && $user->hasManagementAccess();
    }

    private function userCanRejectJob(NovaRequest $request): bool
    {
        if ($request instanceof ActionRequest) {
            return true;
        }
        if ($this->resource->status == \App\Enums\AppraisalJobStatus::Cancelled->value) {
            return false;
        }
        $user = $request->user();
        $appraiser = \App\Models\User::query()->find($this->resource->appraiser_id);
        return $appraiser
            && $appraiser->reviewers
            && in_array($user->id, $appraiser->reviewers)
            && !$this->resource->is_on_hold
            && $this->resource->status == \App\Enums\AppraisalJobStatus::InReview->value;
    }

    /**
     * @param NovaRequest $request
     * @return bool
     */
    private function userCanPutTheJobOnHold(NovaRequest $request): bool
    {
        if ($request instanceof ActionRequest) {
            return true;
        }
        if ($this->resource->status == \App\Enums\AppraisalJobStatus::Cancelled->value) {
            return false;
        }
        return $request->user()->hasManagementAccess()
            && in_array($this->resource->status, [
                \App\Enums\AppraisalJobStatus::Pending->value,
                \App\Enums\AppraisalJobStatus::Assigned->value,
                \App\Enums\AppraisalJobStatus::InProgress->value,
                \App\Enums\AppraisalJobStatus::InReview->value,
            ])
            && $this->resource->is_on_hold == false;
    }

    /**
     * @param NovaRequest $request
     * @return bool
     */
    private function appraiserCanRespondToCurrentAssignment(NovaRequest $request): bool
    {
        if ($request instanceof ActionRequest) {
            return true;
        }
        if ($this->resource->status == \App\Enums\AppraisalJobStatus::Cancelled->value) {
            return false;
        }
        $user = $request->user();
        return $user->isAppraiser()
            && $this->resource->assignments()
                ->where('appraiser_id', $user->id)
                ->where('status', \App\Enums\AppraisalJobAssignmentStatus::Pending)
                ->exists();
    }

    private function jobIsCompleted(NovaRequest $request): bool
    {
        if ($request instanceof ActionRequest) {
            return true;
        }
        if ($this->resource->status != \App\Enums\AppraisalJobStatus::Completed->value) {
            return false;
        }
        return true;
    }

    /**
     * @param NovaRequest $request
     * @return bool
     */
    private function userCanAssignJob(NovaRequest $request): bool
    {
        if ($this->resource->status == \App\Enums\AppraisalJobStatus::Cancelled->value) {
            return false;
        }
        return $request->user()->hasManagementAccess()
            && $this->resource->appraiser_id === null;
    }
}
