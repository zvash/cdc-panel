<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Nova Actions Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines are used in nova admin panel actions.
    | You are free to modify these language lines according to your application's requirements.
    |
    */

    'actions' => [
        'invite_user' => [
            'confirm_text' => 'Are you sure you want to invite this user?',
            'confirm_button' => 'Invite',
            'cancel_button' => 'Cancel',
        ],
        'assign_appraiser' => [
            'confirm_text' => 'Are you sure you want to assign/remove these users to/from the appraisal?',
            'confirm_button' => 'Yes',
            'cancel_button' => 'Cancel',
        ],
        'respond_to_assignment' => [
            'confirm_text' => 'Are you sure you want to respond to this job assignment?',
            'confirm_button' => 'Yes',
            'cancel_button' => 'Cancel',
        ],
        'put_on_hold' => [
            'confirm_text' => 'Are you sure you want to put this job on hold?',
            'confirm_button' => 'Yes',
            'cancel_button' => 'Cancel',
        ],
        'resume_job' => [
            'confirm_text' => 'Are you sure you want to reactivate this job?',
            'confirm_button' => 'Yes',
            'cancel_button' => 'Cancel',
        ],
        'mark_job_as_completed' => [
            'confirm_text' => 'Are you sure you want to mark this job as completed?',
            'confirm_button' => 'Yes',
            'cancel_button' => 'Cancel',
        ],
        'put_job_in_review' => [
            'confirm_text' => 'Are you sure you want to put this job in review?',
            'confirm_button' => 'Yes',
            'cancel_button' => 'Cancel',
        ],
        'add_file' => [
            'confirm_text' => 'Are you sure you want to add this file to the job?',
            'confirm_button' => 'Yes',
            'cancel_button' => 'Cancel',
        ],
        'reject_job' => [
            'confirm_text' => 'Are you sure you want to reject this job?',
            'confirm_button' => 'Yes',
            'cancel_button' => 'Cancel',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Nova Lens Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines are used in nova admin panel actions.
    | You are free to modify these language lines according to your application's requirements.
    |
    */

    'lenses' => [
        'assigned_appraisal_jobs' => [
            'admin_name' => 'Assigned Jobs',
            'appraiser_name' => 'Available Jobs',
        ],
        'rejected_appraisal_jobs' => [
            'name' => 'Rejected Jobs',
        ],
        'not_assigned_appraisal_jobs' => [
            'name' => 'Unassigned Jobs',
        ],
        'in_progress_appraisal_jobs' => [
            'admin_name' => 'In Progress Jobs',
            'appraiser_name' => 'Active Jobs',
        ],
        'in_review_appraisal_jobs' => [
            'name' => 'To Review Jobs',
        ],
        'completed_appraisal_jobs' => [
            'name' => 'Completed Jobs',
        ],
        'on_hold_appraisal_jobs' => [
            'name' => 'On Hold Jobs',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Nova Field Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines are used in nova admin panel fields.
    | You are free to modify these language lines according to your application's requirements.
    |
    */

    'fields' => [
        'common' => [
            'image' => 'MIME types: <b class="text-info-dark">:mimes</b> | Dimensions: <b class="text-info-dark">:dimension</b>',
            'key_value' => 'Use <b class="text-info-dark">Numbers</b> to fill the sort key. You can skip :section section if you don\'t have any.',
            'markdown' => 'For more information about markdown, check this <a class="font-bold" href="https://www.markdownguide.org/basic-syntax/">documentation</a>',
        ],
        'locations' => [
            'default' => 'Indicate that this location is head quarter or not.',
            'phone' => 'Phone ex: (123) 456-7890',
        ],
        'modules' => [
            'is_global' => 'Global modules will be <b class="text-info-dark">available</b> in all pages.',
            'content' => 'Content should be in <b class="text-info-dark">JSON</b> format. to validate it use <a href="https://jsonlint.com/" class="font-bold">JSON linter</a>.',
        ],
        'news' => [
            'tags' => 'Server use <b class="text-info-dark">Tag Slug</b> to find the news, A tag slug must write in <a href="https://en.wikipedia.org/wiki/Letter_case#Special_case_styles" class="font-bold">Kebab case</a> format.',
        ],
        'options' => [
            'key' => 'Application use this <b class="text-info-dark">Key</b> to find the option.',
        ],
        'orders' => [
            'error' => '<b class="text-danger">Invalid Card Payload</b>',
        ],
        'pages' => [
            'has_faq' => 'Show <b class="text-info-dark">FAQs</b> in the page. You can\'t use it on static pages so in this case system will automatically uncheck it.',
            'is_static' => 'Indicate the page is <b class="text-info-dark">static</b> page or dynamic, once you create a page, you <b class="text-danger-dark">CAN NOT</b> modify this value anymore.',
        ],
        'patients' => [
            'weight' => 'The weight should use <b class="text-info-dark">Kilograms</b>.',
            'height' => 'The height should use <b class="text-info-dark">Feet</b>.',
        ],
        'roles' => [
            'is_superadmin' => 'Superadmin roles <b class="text-danger-dark">DO NOT</b> need any permissions.',
        ],
        'services' => [
            'navigation' => 'The service appears in <b class="text-info-dark">footer</b> or other <b class="text-info-dark">menus</b>.',
            'feature' => 'The service appears in homepage or other pages as <b class="text-info-dark">feature</b> resource.',
            'color' => 'The color should be in <a href="https://en.wikipedia.org/wiki/Web_colors">hexadecimal</a> format follow by a #.',
        ],
        'users' => [
            'is_author' => 'Can select this user as a <b class="text-info-dark">Author</b> on creating or updating a news.',
            'is_team_member' => 'Show this user in <b class="text-info-dark">About us</b> page as a team member.',
            'team_role' => '<b class="text-info-dark">ex:</b> CEO, Manager and etc.',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Language Line for Nova
    |--------------------------------------------------------------------------
    |
    | Here you may specify custom validation messages for attributes using the
    | convention "attribute.rule" to name the lines. This makes it quick to
    | specify a specific custom language line for a given attribute rule in Nova.
    |
    */

    'validation' => [
        'seoable' => [
            'accepted' => 'The :title resource already has a seo object.',
        ],
        'email' => [
            'accepted' => 'The Email, Date of birth or Last name aren\'t unique, System find a patient with exact information.',
        ],
    ],

];
