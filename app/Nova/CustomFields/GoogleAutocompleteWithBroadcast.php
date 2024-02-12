<?php

namespace App\Nova\CustomFields;

use Laravel\Nova\Fields\SupportsDependentFields;

class GoogleAutocompleteWithBroadcast extends \BrandonJBegle\GoogleAutocomplete\GoogleAutocomplete
{
    use SupportsDependentFields;

}
