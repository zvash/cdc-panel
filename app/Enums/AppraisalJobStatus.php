<?php

namespace App\Enums;

enum AppraisalJobStatus: string
{
    use GenericMethods;
    case Pending = 'Pending';
    case InProgress = 'In Progress';
    case Completed = 'Completed';
    case Cancelled = 'Cancelled';

}
