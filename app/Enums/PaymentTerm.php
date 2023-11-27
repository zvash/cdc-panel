<?php

namespace App\Enums;

enum PaymentTerm: string
{
    use GenericMethods;

    case Invoice = 'Invoice';
    case AdvancePayment = 'Advance Payment';
    case FiftyPercentRetainer = '50% Retainer';
}
