<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Draft = 'draft';
    case Open = 'open';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}
