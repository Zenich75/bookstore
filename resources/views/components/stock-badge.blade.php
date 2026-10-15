{{-- View of the StockBadge class component: public properties and methods are available. --}}
<span {{ $attributes->class([
    'inline-block rounded-full px-2 py-0.5 text-xs font-medium',
    'bg-red-100 text-red-800' => $state() === 'out',
    'bg-amber-100 text-amber-800' => $state() === 'low',
    'bg-emerald-100 text-emerald-800' => $state() === 'in',
]) }} data-state="{{ $state() }}">
    {{ $label() }}@if ($state() !== 'out') ({{ $available() }})@endif
</span>
