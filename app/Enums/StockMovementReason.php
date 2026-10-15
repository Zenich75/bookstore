<?php

namespace App\Enums;

enum StockMovementReason: string
{
    case Receipt = 'receipt';
    case Sale = 'sale';
    case Adjustment = 'adjustment';
    case Import = 'import';

    /**
     * Human-readable, translated name (lang/{en,uk}/enums.php).
     */
    public function label(): string
    {
        return __('enums.stock_movement_reason.'.$this->value);
    }
}
