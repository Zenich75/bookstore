@extends('layouts.legacy')

@section('title', __('ui.about.title'))

@section('content')
    <div class="space-y-4">
        <p>{{ __('ui.about.intro') }}</p>

        <h2 class="text-lg font-semibold">{{ __('ui.about.how_title') }}</h2>
        {{-- These strings contain markup from our own lang files (no user input), so they are printed raw. --}}
        <p>{!! __('ui.about.how') !!}</p>
        <p>{!! __('ui.about.compare') !!}</p>
    </div>
@endsection
