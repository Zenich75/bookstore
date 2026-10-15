<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Lab: Eloquent relationships</title>
</head>
<body>
    <h1>Lab: Eloquent relationships</h1>
    <p>
        Each example shows the code that ran, its result and the SQL it executed.
        Strict mode is on, so every relation is loaded explicitly.
        Write examples run inside a transaction that is rolled back.
    </p>

    <nav>
        <ul>
            @foreach ($sections as $section => $examples)
                <li><a href="#{{ Str::slug($section) }}">{{ $section }}</a> ({{ $examples->count() }})</li>
            @endforeach
        </ul>
    </nav>

    @foreach ($sections as $section => $examples)
        <h2 id="{{ Str::slug($section) }}">{{ $section }}</h2>

        @foreach ($examples as $example)
            <section>
                <h3>{{ $example['title'] }} <code>[{{ $example['type'] }}]</code></h3>

                <h4>Code</h4>
                <pre>{{ $example['code'] }}</pre>

                <h4>Result</h4>
                @if ($example['skipped'])
                    <p><em>Skipped: {{ $example['skipped'] }}</em></p>
                @elseif ($example['error'])
                    <p><strong>Error:</strong> {{ $example['error'] }}</p>
                @else
                    <pre>{{ json_encode($example['result'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
                @endif

                <h4>SQL ({{ count($example['queries']) }} {{ Str::plural('query', count($example['queries'])) }})</h4>
                <ol>
                    @foreach ($example['queries'] as $sql)
                        <li><code>{{ $sql }}</code></li>
                    @endforeach
                </ol>
            </section>
            <hr>
        @endforeach
    @endforeach
</body>
</html>
