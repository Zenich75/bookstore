<x-mail::message>
# {{ __('mail.import_failed.heading') }}

{{ __('mail.import_failed.intro', ['file' => $file]) }}

<x-mail::panel>
{{ $reason }}
</x-mail::panel>

<x-mail::button :url="$url" color="error">
{{ __('mail.import_failed.button') }}
</x-mail::button>

{{ __('mail.signature') }}<br>
{{ config('app.name') }}
</x-mail::message>
