<x-mail::message>
# {{ __('mail.import_finished.heading') }}

{{ __('mail.import_finished.intro', ['file' => $file, 'warehouse' => $warehouse]) }}

<x-mail::table>
| | |
|:--|--:|
| {{ __('mail.import_finished.status') }} | {{ $status }} |
| {{ __('mail.import_finished.total') }} | {{ $total }} |
| {{ __('mail.import_finished.imported') }} | {{ $imported }} |
| {{ __('mail.import_finished.failed') }} | {{ $failed }} |
</x-mail::table>

<x-mail::button :url="$url">
{{ __('mail.import_finished.button') }}
</x-mail::button>

@if ($errorsUrl)
{{ __('mail.import_finished.errors') }} [{{ __('mail.import_finished.errors_link') }}]({{ $errorsUrl }})
@endif

{{ __('mail.signature') }}<br>
{{ config('app.name') }}
</x-mail::message>
