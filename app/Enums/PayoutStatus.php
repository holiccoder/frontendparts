<?php

namespace App\Enums;

enum PayoutStatus: string
{
    case Processing = 'processing';
    case Paid = 'paid';
    case Failed = 'failed';
}
