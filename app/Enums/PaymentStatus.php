<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Unpaid = 'Unpaid';
    case RetainerPaid = 'Retainer Paid';
    case Paid = 'Paid';
    case Cancelled = 'Cancelled';

    public static function names(): array
    {
        return array_column(self::cases(), 'name');
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function array(): array
    {
        return array_combine(self::values(), self::names());
    }
}
