<x-mail::message>
# {{ __('mail.pending_review.heading') }}

{{ __('mail.pending_review.intro', ['reviewer' => $reviewer, 'title' => $title, 'rating' => $rating]) }}

<x-mail::panel>
{{ $excerpt }}
</x-mail::panel>

<x-mail::button :url="$url">
{{ __('mail.pending_review.button') }}
</x-mail::button>

{{ __('mail.signature') }}<br>
{{ config('app.name') }}
</x-mail::message>
