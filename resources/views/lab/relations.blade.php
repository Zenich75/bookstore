<x-layouts.app title="Lab: Eloquent relationships">
    <p class="-mt-2 mb-6 max-w-3xl text-sm text-stone-600">
        Each example shows the code that ran, its result and the SQL it executed.
        Strict mode is on, so every relation is loaded explicitly.
        Write examples run inside a transaction that is rolled back.
    </p>

    <div class="grid gap-8 lg:grid-cols-[14rem_1fr]">
        <nav class="text-sm lg:sticky lg:top-4 lg:self-start">
            @foreach ($sections as $section => $examples)
                <a href="#{{ Str::slug($section) }}" class="block py-1 font-semibold hover:text-amber-700">{{ $section }} <span class="font-normal text-stone-400">({{ $examples->count() }})</span></a>
                <ul class="mb-3 ml-3 space-y-0.5 text-stone-500">
                    @foreach ($examples as $example)
                        <li><a href="#{{ Str::slug($section.'-'.$loop->iteration) }}" class="hover:text-amber-700">{{ $example['type'] }}</a></li>
                    @endforeach
                </ul>
            @endforeach
        </nav>

        <div class="min-w-0 space-y-10">
            @foreach ($sections as $section => $examples)
                <section>
                    <h2 id="{{ Str::slug($section) }}" class="mb-4 text-xl font-semibold">{{ $section }}</h2>

                    <div class="space-y-6">
                        @foreach ($examples as $example)
                            <x-card id="{{ Str::slug($section.'-'.$loop->iteration) }}">
                                <x-slot:header class="flex flex-wrap items-baseline justify-between gap-2">
                                    <span>{{ $example['title'] }}</span>
                                    <code class="rounded bg-stone-100 px-2 py-0.5 text-xs font-normal text-stone-600">{{ $example['type'] }}</code>
                                </x-slot:header>

                                <div class="grid gap-4 xl:grid-cols-2">
                                    <div class="min-w-0">
                                        <h3 class="mb-1 text-xs font-semibold uppercase text-stone-400">Code</h3>
                                        <pre class="overflow-x-auto rounded bg-stone-900 p-3 text-xs text-stone-100">{{ $example['code'] }}</pre>
                                    </div>
                                    <div class="min-w-0">
                                        <h3 class="mb-1 text-xs font-semibold uppercase text-stone-400">Result</h3>
                                        @if ($example['skipped'])
                                            <p class="text-sm italic text-stone-500">Skipped: {{ $example['skipped'] }}</p>
                                        @elseif ($example['error'])
                                            <p class="rounded bg-red-50 p-3 text-sm text-red-800"><strong>Error:</strong> {{ $example['error'] }}</p>
                                        @else
                                            <pre class="max-h-72 overflow-auto rounded bg-stone-100 p-3 text-xs">{{ json_encode($example['result'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
                                        @endif
                                    </div>
                                </div>

                                <x-slot:footer>
                                    <details>
                                        <summary class="cursor-pointer">SQL — {{ count($example['queries']) }} {{ Str::plural('query', count($example['queries'])) }}</summary>
                                        <ol class="mt-2 list-decimal space-y-1 pl-5">
                                            @foreach ($example['queries'] as $sql)
                                                <li><code class="break-all text-xs">{{ $sql }}</code></li>
                                            @endforeach
                                        </ol>
                                    </details>
                                </x-slot:footer>
                            </x-card>
                        @endforeach
                    </div>
                </section>
            @endforeach
        </div>
    </div>
</x-layouts.app>
