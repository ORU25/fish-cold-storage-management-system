<?php

namespace App\Enums;

enum AdjustmentType: string
{
    case Lost = 'lost';
    case Damaged = 'damaged';

    /**
     * The box status an approved adjustment of this type leads to.
     */
    public function boxStatus(): BoxStatus
    {
        return match ($this) {
            self::Lost => BoxStatus::Lost,
            self::Damaged => BoxStatus::Damaged,
        };
    }
}
