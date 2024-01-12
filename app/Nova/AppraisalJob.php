<?php

namespace App\Nova;

use App\Models\AppraisalType;
use App\Nova\Actions\AddFile;
use App\Nova\Actions\AssignAppraiserAction;
use App\Nova\Actions\MarkAsCompleted;
use App\Nova\Actions\PutAppraisalJobOnHold;
use App\Nova\Actions\PutJobInReview;
use App\Nova\Actions\RejectAfterReview;
use App\Nova\Actions\RespondToAssignment;
use App\Nova\Actions\ResumeAppraisalJob;
use App\Nova\Lenses\AppraiserInvoice;
use App\Nova\Lenses\AppraiserMonthlyInvoice;
use App\Nova\Lenses\AssignedAppraisalJobs;
use App\Nova\Lenses\ClientInvoice;
use App\Nova\Lenses\ClientMonthlyInvoice;
use App\Nova\Lenses\CompletedAppraisalJobs;
use App\Nova\Lenses\InProgressAppraisalJobs;
use App\Nova\Lenses\InReviewAppraisalJobs;
use App\Nova\Lenses\NotAssignedAppraisalJobs;
use App\Nova\Lenses\OnHoldAppraisalJobs;
use App\Nova\Lenses\RejectedAppraisalJobs;
use App\Nova\Lenses\ReviewerInvoice;
use App\Nova\Metrics\AverageAppraisalProcessDuration;
use App\Nova\Metrics\AverageJobCreationToCompletionDuration;
use App\Nova\Metrics\AverageReviewerProcessDuration;
use App\Nova\Metrics\AverageWorkOnJobDuration;
use App\Nova\Metrics\CompletedJobsPerDay;
use App\Nova\Metrics\JobPerStatus;
use App\Traits\NovaResource\LimitsIndexQuery;
use BrandonJBegle\GoogleAutocomplete\GoogleAutocomplete;
use Dniccum\PhoneNumber\PhoneNumber;
use Ebess\AdvancedNovaMediaLibrary\Fields\Files;
use Flatroy\FieldProgressbar\FieldProgressbar;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Laravel\Nova\Fields\Badge;
use Laravel\Nova\Fields\BelongsTo;
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
            $this->orderInformation(),

            $this->propertyAddress(),

            $this->paymentInformation(),

            $this->contactInformation(),

            $this->additionalInformations(),

            $this->relations(),
        ];
    }

    public function orderInformation(): Panel
    {
        return $this->panel('Order Information', [
            ID::make()->sortable(),

            Select::make('Client', 'client_id')
                ->options(\App\Models\Client::pluck('name', 'id'))
                ->required()
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
                ->options(AppraisalType::pluck('name', 'id'))
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
                ->required()
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

            Select::make('Appraiser', 'appraiser_id')
                ->options(\App\Models\User::query()->whereHas('roles', function ($roles) {
                    return $roles->whereIn('name', ['Appraiser']);
                })->pluck('name', 'id')->toArray())
                ->required()
                ->hideFromIndex()
                ->filterable()
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
                        $reviewers = \App\Models\User::query()->find($value)->reviewers;
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
                ->rules('required')
                ->setFileName(function ($originalFilename, $extension, $model) {
                    return $originalFilename . '-' . time() . '.' . $extension;
                })
                ->required()
                ->singleImageRules('mimes:pdf,doc,docx,txt,jpg,jpeg,png,webp'),


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

//            BelongsTo::make('Reviewer', 'reviewer', User::class)
//                ->searchable()
//                ->exceptOnForms()
//                ->nullable()
//                ->hideFromIndex()
//                ->displayUsing(function ($user) {
//                    return $user->name;
//                }),

//            Select::make('Reviewer', 'reviewer_id')
//                ->options(\App\Models\User::whereHas('roles', function ($roles) {
//                    return $roles->whereIn('name', ['Appraiser']);
//                })->pluck('name', 'id')->toArray())
//                ->rules('nullable', 'exists:users,id')
//                ->nullable()
//                ->onlyOnForms()
//                ->searchable()
//                ->displayUsingLabels(),

            Text::make('File Number', 'reference_number')
                ->filterable()
                ->hideFromIndex(),

            Text::make('Lender')
                ->hideFromIndex(),

            Text::make('Applicant')
                ->hideFromIndex(),

            Text::make('Email')
                ->hideFromIndex()
                ->nullable()
                ->placeholder('N/A')
                ->creationRules('nullable', 'email'),

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

    public function paymentInformation(): Panel
    {
        return $this->panel('Payment Information', [

            Select::make('Province (for tax)', 'province')
                ->searchable()
                ->hideFromIndex()
                ->required()
                ->rules('required')
                ->options(\App\Models\Province::pluck('name', 'name'))
                ->displayUsingLabels(),

//            Number::make('Tax (%)', 'tax')
//                ->min(0)
//                ->max(100)
//                ->step(0.01)
//                ->rules('nullable', 'numeric', 'min:0', 'max:100')
//                ->hideFromIndex()
//                ->nullable(),

//            Currency::make('Fee Quoted')
//                ->min(0)
//                ->max(999999.99)
//                ->step(0.01)
//                ->hideFromIndex()
//                ->nullable(),

            Text::make('Fee Quoted')
                ->hideFromIndex()
                ->nullable()
                ->displayUsing(function ($value) {
                    return $value ? '$' . number_format($value, 2) : '-';
                })
                ->rules('nullable', 'numeric', 'min:0', 'max:999999.99'),

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
                ->placeholder('N/A')
                ->creationRules('nullable', 'email'),

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
            Select::make('Property Province')
                ->searchable()
                ->hideFromIndex()
                ->options(\App\Models\Province::pluck('name', 'name'))
                ->required()
                ->rules('required')
                ->displayUsingLabels(),

            Select::make('Property City')
                ->searchable()
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
                ->required()
                ->rules('required')
                ->displayUsingLabels(),

            GoogleAutocomplete::make('Address', 'property_address')
                ->countries('CA')
                ->required()
                ->rules('required')
                ->hideFromIndex(),

            Text::make('Postal Code', 'property_postal_code')
                ->rules('regex:/^[A-Za-z]{1}\d{1}[A-Za-z]{1}[ ]{0,1}\d{1}[A-Za-z]{1}\d{1}$/', 'nullable')
                ->nullable()
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
        return [
//            (new CompletedJobsPerDay())
//                ->width('2/3')
//                ->defaultRange('7')
//                ->canSee(function () use ($request) {
//                    return $request->user()->hasManagementAccess();
//                })->refreshWhenFiltersChange(),
//            (new JobPerStatus())
//                ->width('1/3')
//                ->canSee(function () use ($request) {
//                    return $request->user()->hasManagementAccess();
//                })->refreshWhenFiltersChange(),
//            (new AverageAppraisalProcessDuration())
//                ->width('1/3')
//                ->canSee(function () use ($request) {
//                    return $request->user()->hasManagementAccess();
//                }),
//            (new AverageReviewerProcessDuration())
//                ->width('1/3')
//                ->canSee(function () use ($request) {
//                    return $request->user()->hasManagementAccess();
//                }),
//            (new AverageWorkOnJobDuration())
//                ->width('1/3')
//                ->canSee(function () use ($request) {
//                    return $request->user()->hasManagementAccess();
//                }),
//            (new AverageJobCreationToCompletionDuration())
//                ->width('full')
//                ->canSee(function () use ($request) {
//                    return $request->user()->hasManagementAccess();
//                })->refreshWhenFiltersChange(),
        ];
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
            (new AppraiserInvoice($this->resource)),
            (new ReviewerInvoice($this->resource)),
            (new ClientInvoice($this->resource)),
            (new AppraiserMonthlyInvoice($this->resource)),
            (new ClientMonthlyInvoice($this->resource)),
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
                    return $this->userCanAssignJob($request);
                })
                ->canRun(function () use ($request) {
                    return $this->userCanAssignJob($request);
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
        $user = $request->user();
        return $user->isAppraiser()
            && !$this->resource->is_on_hold
            && $this->resource->status == \App\Enums\AppraisalJobStatus::InReview->value
            && (
                $this->resource->reviewer_id == $user->id
                || (
                    $this->resource->appraiser_id
                    && \App\Models\User::query()
                        ->where('id', $this->resource->appraiser_id)
                        ->whereJsonContains('reviewers', "{$user->id}")
                        ->exists()
                )
            );
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
    private function jobCanBeMarkedAsCompletedByCurrentUser(NovaRequest $request): bool
    {
        if ($request instanceof ActionRequest) {
            return true;
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

    private function userCanRejectJob(NovaRequest $request): bool
    {
        if ($request instanceof ActionRequest) {
            return true;
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
        $user = $request->user();
        return $user->isAppraiser()
            && $this->resource->assignments()
                ->where('appraiser_id', $user->id)
                ->where('status', \App\Enums\AppraisalJobAssignmentStatus::Pending)
                ->exists();
    }

    /**
     * @param NovaRequest $request
     * @return bool
     */
    private function userCanAssignJob(NovaRequest $request): bool
    {
        return $request->user()->hasManagementAccess()
            && $this->resource->appraiser_id === null;
    }
}
