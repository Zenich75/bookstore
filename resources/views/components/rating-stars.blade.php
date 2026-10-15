{{-- Props with a default: <x-rating-stars :rating="4.3" /> (max defaults to 5). --}}
@props(['rating' => null, 'max' => 5])

@php
    $filled = $rating === null ? 0 : (int) round((float) $rating);
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1 text-sm']) }}
      title="{{ $rating === null ? __('ui.rating.none_title') : __('ui.rating.title', ['rating' => Number::format((float) $rating, 1), 'max' => $max]) }}">
    <span class="tracking-tight text-amber-500" aria-hidden="true">
        {{ str_repeat('★', $filled) }}<span class="text-stone-300">{{ str_repeat('★', $max - $filled) }}</span>
    </span>
    @if ($rating !== null)
        <span class="text-stone-500">{{ Number::format((float) $rating, 1) }}</span>
    @else
        <span class="text-stone-400">{{ __('ui.rating.none') }}</span>
    @endif
</span>
