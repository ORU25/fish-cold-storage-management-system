<?php

namespace App\Enums;

enum BoxStatus: string
{
    case InWarehouse = 'in_warehouse';
    case Outbound = 'outbound';
    case PendingAdjustment = 'pending_adjustment';
    case Lost = 'lost';
    case Damaged = 'damaged';
}
