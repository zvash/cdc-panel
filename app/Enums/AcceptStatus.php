<?php

namespace App\Enums;

enum AcceptStatus: string
{
    use GenericMethods;

    case Pending = 'Pending';
    case Accepted = 'Accepted';
    case Declined = 'Declined';
    case Missed = 'Missed';

}
