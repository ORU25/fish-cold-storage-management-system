<?php

namespace App\Enums;

enum QrLabelStatus: string
{
    case Available = 'available';
    case Used = 'used';
    case Void = 'void';
}
