<x-layouts.app :title="__('lab.collections.title')">
    <p class="-mt-2 mb-4 max-w-3xl text-sm text-stone-600">{{ __('lab.collections.intro') }}</p>

    <nav class="mb-6 flex flex-wrap gap-2 text-sm">
        @foreach ($sections as $key => $title)
            <a href="{{ route('lab.collections', ['section' => $key]) }}"
               @class(['rounded px-3 py-1', 'bg-amber-600 text-white' => $key === $section, 'bg-white hover:bg-stone-100' => $key !== $section])>{{ $title }}</a>
        @endforeach
    </nav>

    @foreach ($notes as $note)
        <p class="mb-4 rounded border border-amber-200 bg-amber-50 px-4 py-2 text-sm text-amber-900">{{ $note }}</p>
    @endforeach

    <div class="space-y-8">
        @foreach ($results as $result)
            @php
                $comparison = $result['comparison'];
                $runs = collect($result['runs']);
                $max = [
                    'queries' => max(1, $runs->max(fn ($r) => $r['profile']->queries)),
                    'db' => max(0.01, $runs->max(fn ($r) => $r['profile']->dbMs)),
                    'wall' => max(0.01, $runs->max(fn ($r) => $r['profile']->wallMs)),
                    'memory' => max(1, $runs->max(fn ($r) => $r['profile']->memoryBytes)),
                ];
            @endphp

            <x-card>
                <x-slot:header class="flex flex-wrap items-baseline justify-between gap-2">
                    <span>{{ $comparison->title }}</span>
                    <span class="flex gap-1 text-xs font-normal">
                        @if ($comparison->heavy)<span class="rounded bg-stone-200 px-1.5">{{ __('lab.collections.heavy') }}</span>@endif
                        @if ($comparison->writes)<span class="rounded bg-emerald-100 px-1.5 text-emerald-800">{{ __('lab.collections.writes') }}</span>@endif
                    </span>
                </x-slot:header>

                <p class="mb-4 text-sm text-stone-600">{{ $comparison->description }}</p>

                <table class="mb-4 w-full text-sm">
                    <thead class="text-left text-xs uppercase text-stone-400">
                        <tr>
                            <th class="py-1 pr-3">{{ __('lab.common.metrics.approach') }}</th>
                            <th class="w-1/6 py-1 pr-3">{{ __('lab.common.metrics.queries') }}</th>
                            <th class="w-1/6 py-1 pr-3">{{ __('lab.common.metrics.db_ms') }}</th>
                            <th class="w-1/6 py-1 pr-3">{{ __('lab.common.metrics.wall_ms') }}</th>
                            <th class="w-1/6 py-1">{{ __('lab.common.metrics.memory') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($runs as $run)
                            @php($p = $run['profile'])
                            <tr class="border-t border-stone-100">
                                <td class="py-1.5 pr-3 font-mono text-xs">{{ $run['approach']->label }}</td>
                                @foreach ([
                                    ['queries', $p->queries, Number::format($p->queries)],
                                    ['db', $p->dbMs, Number::format($p->dbMs, 1)],
                                    ['wall', $p->wallMs, Number::format($p->wallMs, 1)],
                                    ['memory', $p->memoryBytes, Number::fileSize($p->memoryBytes, 1)],
                                ] as [$metric, $value, $formatted])
                                    <td class="py-1.5 pr-3">
                                        <div class="text-xs tabular-nums">{{ $formatted }}</div>
                                        <div class="mt-0.5 h-1.5 rounded bg-stone-100">
                                            <div class="h-1.5 rounded bg-amber-500" style="width: {{ min(100, round($value / $max[$metric] * 100, 1)) }}%"></div>
                                        </div>
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                <div @class(['grid gap-4', 'lg:grid-cols-2' => $runs->count() === 2, 'lg:grid-cols-3' => $runs->count() === 3, 'lg:grid-cols-2 xl:grid-cols-3' => $runs->count() > 3])>
                    @foreach ($runs as $run)
                        @php($p = $run['profile'])
                        <div class="min-w-0 rounded border border-stone-200">
                            <div class="border-b border-stone-200 bg-stone-50 px-3 py-1.5 font-mono text-xs font-semibold">{{ $run['approach']->label }}</div>
                            <div class="space-y-2 p-3">
                                @if ($run['approach']->note)
                                    <p class="text-xs text-amber-800">{{ $run['approach']->note }}</p>
                                @endif
                                <pre class="overflow-x-auto rounded bg-stone-900 p-2 text-xs text-stone-100">{{ $run['approach']->code() }}</pre>
                                @if ($p->error)
                                    <p class="rounded bg-red-50 p-2 text-xs text-red-800"><strong>{{ __('lab.common.error') }}</strong> {{ $p->error }}</p>
                                @else
                                    <pre class="max-h-56 overflow-auto rounded bg-stone-100 p-2 text-xs">{{ json_encode($p->result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
                                @endif
                                <details class="text-xs">
                                    <summary class="cursor-pointer text-stone-500">{{ __('lab.common.sql_distinct', ['queries' => trans_choice('lab.common.queries', $p->queries), 'distinct' => count($p->groups)]) }}</summary>
                                    <ol class="mt-1 space-y-1">
                                        @foreach ($p->groups as $group)
                                            <li class="flex gap-2"><span class="w-10 shrink-0 text-right tabular-nums">×{{ $group['count'] }}</span><code class="break-all">{{ $group['sql'] }}</code></li>
                                        @endforeach
                                    </ol>
                                </details>
                            </div>
                        </div>
                    @endforeach
                </div>
            </x-card>
        @endforeach
    </div>
</x-layouts.app>
