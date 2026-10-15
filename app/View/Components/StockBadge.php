<?php

namespace App\View\Components;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * Class-based component: the state logic lives in PHP, the Blade view only renders.
 *
 * <x-stock-badge :quantity="$stock->quantity" :reserved="$stock->reserved" />
 */
class StockBadge extends Component
{
    public const LOW_STOCK_THRESHOLD = 5;

    public function __construct(
        public int $quantity,
        public int $reserved = 0,
    ) {}

    public function available(): int
    {
        return max(0, $this->quantity - $this->reserved);
    }

    /**
     * @return 'out'|'low'|'in'
     */
    public function state(): string
    {
        return match (true) {
            $this->available() === 0 => 'out',
            $this->available() < self::LOW_STOCK_THRESHOLD => 'low',
            default => 'in',
        };
    }

    public function label(): string
    {
        return __('ui.stock_badge.'.$this->state());
    }

    public function render(): View|Closure|string
    {
        return view('components.stock-badge');
    }
}
