<x-layouts.app :title="'N+1 lab — '.$variant->label.'. '.$variant->title">
    <nav class="-mt-4 mb-6 flex flex-wrap gap-2 text-xs">
        <a href="{{ route('lab.n-plus-one') }}" class="rounded bg-stone-200 px-2 py-1 hover:bg-stone-300">← All variants</a>
        @foreach ($variants as $other)
            <a href="{{ route('lab.n-plus-one', ['variant' => $other->key]) }}"
               @class(['rounded px-2 py-1', 'bg-amber-600 text-white' => $other->key === $variant->key, 'bg-white hover:bg-stone-100' => $other->key !== $variant->key])>{{ $other->label }}</a>
        @endforeach
    </nav>

    <p class="mb-4 max-w-3xl text-stone-700">{{ $variant->description }}</p>
    @if ($variant->lazy)
        <p class="mb-4 text-sm text-amber-800">Runs inside <code>LazyLoading::allowDuring()</code> — without it, strict mode would throw.</p>
    @endif

    <div class="space-y-6">
        @include('lab.n-plus-one._metrics')

        <x-card>
            <x-slot:header>Code</x-slot:header>
            <pre class="overflow-x-auto rounded bg-stone-900 p-3 text-xs text-stone-100">{{ $variant->code() }}</pre>
        </x-card>

        @if ($profile->error)
            <p class="rounded bg-red-50 p-3 text-sm text-red-800"><strong>Error:</strong> {{ $profile->error }}</p>
        @endif

        <x-card>
            <x-slot:header>Queries grouped by normalized SQL ({{ $profile->queries }} total, {{ count($profile->groups) }} distinct)</x-slot:header>
            <ol class="space-y-1 text-xs">
                @foreach ($profile->groups as $group)
                    <li @class(['flex gap-3', 'font-semibold text-red-700' => $group['count'] > 1])>
                        <span class="w-14 shrink-0 text-right tabular-nums">× {{ $group['count'] }}</span>
                        <code class="break-all">{{ $group['sql'] }}</code>
                    </li>
                @endforeach
            </ol>
        </x-card>

        <details class="rounded-lg border border-stone-200 bg-white">
            <summary class="cursor-pointer px-4 py-3 font-semibold">Rendered output</summary>
            <div class="border-t border-stone-200 p-4">
                {{-- Our own rendered partial (already escaped inside), so output it raw. --}}
                {!! $profile->result !!}
            </div>
        </details>
    </div>
</x-layouts.app>
