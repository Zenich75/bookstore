{{-- Markdown mail: <x-mail::...> components render to HTML + a plain-text part. Keep the indentation flat: Markdown treats 4 spaces as code. --}}
<x-mail::message>
# {{ __('mail.low_stock.heading', ['warehouse' => $warehouse]) }}

{{ trans_choice('mail.low_stock.intro', $total, ['count' => $total, 'threshold' => $threshold]) }}

<x-mail::table>
| {{ __('mail.low_stock.columns.book') }} | {{ __('mail.low_stock.columns.isbn') }} | {{ __('mail.low_stock.columns.shelf') }} | {{ __('mail.low_stock.columns.available') }} |
|:--|:--|:--|--:|
@foreach ($items as $item)
| {{ $item['title'] }} | {{ $item['isbn'] }} | {{ $item['shelf'] ?? '—' }} | {{ $item['available'] }} |
@endforeach
</x-mail::table>

@if ($total > count($items))
{{ __('mail.low_stock.more', ['count' => $total - count($items)]) }}
@endif

<x-mail::button :url="$url">
{{ __('mail.low_stock.button') }}
</x-mail::button>

{{ __('mail.signature') }}<br>
{{ config('app.name') }}
</x-mail::message>
