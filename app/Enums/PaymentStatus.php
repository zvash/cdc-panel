<?php

namespace App\Enums;

enum PaymentStatus: string
{
    use GenericMethods;

    case Unpaid = 'Unpaid';
    case RetainerPaid = 'Retainer Paid';
    case Paid = 'Paid';
    case Cancelled = 'Cancelled';

}
