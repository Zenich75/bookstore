<?php

namespace App\Enums;

enum StockMovementReason: string
{
    case Receipt = 'receipt';
    case Sale = 'sale';
    case Adjustment = 'adjustment';
    case Import = 'import';
}
