@extends('layouts.legacy')

@section('title', 'About')

@section('content')
    <div class="space-y-4">
        <p>
            Bookstore Warehouse is an educational Laravel 13 project. Each stage adds a group of
            framework features on top of a small bookstore warehouse domain.
        </p>

        <h2 class="text-lg font-semibold">How this page is built</h2>
        <p>
            This page uses <strong>template inheritance</strong>: <code>@@extends('layouts.legacy')</code>
            plus <code>@@section('content')</code> blocks that fill the layout's <code>@@yield</code>
            placeholders. All other pages use the <strong>component layout</strong>
            <code>&lt;x-layouts.app title="..."&gt;</code>, where the page body is the default
            <code>$slot</code> and values are passed as props.
        </p>
        <p>
            Compare <code>resources/views/layouts/legacy.blade.php</code> with
            <code>resources/views/components/layouts/app.blade.php</code>, and read
            <code>docs/03-routes-blade.md</code>.
        </p>
    </div>
@endsection
