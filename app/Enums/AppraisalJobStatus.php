<?php

namespace App\Enums;

enum AppraisalJobStatus: string
{
    use GenericMethods;

    case Pending = 'Pending';

    case Assigned = 'Assigned';

    case InProgress = 'In Progress';
    case InReview = 'In Review';
    case Completed = 'Completed';
    case Cancelled = 'Cancelled';

}
