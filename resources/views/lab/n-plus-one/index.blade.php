<x-layouts.app title="Lab: N+1 queries">
    <p class="-mt-2 mb-6 max-w-3xl text-sm text-stone-600">
        Each variant renders the same card for the same {{ $sampleSize }} books, so the only difference is
        how data is loaded. Metrics are from the second of two runs (warm-up). Debugbar is disabled on this
        page so it doesn't skew time and memory.
    </p>

    @php
        $ok = $rows->filter(fn ($row) => $row['variant']->ranked && $row['profile']->error === null);
        $max = [
            'queries' => max(1, $ok->max(fn ($row) => $row['profile']->queries)),
            'db' => max(0.01, $ok->max(fn ($row) => $row['profile']->dbMs)),
            'wall' => max(0.01, $ok->max(fn ($row) => $row['profile']->wallMs)),
            'memory' => max(1, $ok->max(fn ($row) => $row['profile']->memoryBytes)),
        ];
    @endphp

    <x-card>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="text-left text-xs uppercase text-stone-400">
                    <tr>
                        <th class="py-2 pr-3">Variant</th>
                        <th class="w-1/6 py-2 pr-3">Queries</th>
                        <th class="w-1/6 py-2 pr-3">DB ms</th>
                        <th class="w-1/6 py-2 pr-3">Wall ms</th>
                        <th class="w-1/6 py-2">Memory</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        @php($p = $row['profile'])
                        <tr @class([
                            'border-t border-stone-100',
                            'bg-red-50' => $row['variant']->key === $worst,
                            'bg-emerald-50' => $row['variant']->key === $best,
                        ])>
                            <td class="py-2 pr-3">
                                <a href="{{ route('lab.n-plus-one', ['variant' => $row['variant']->key]) }}" class="hover:text-amber-700">
                                    <span class="font-mono text-xs text-stone-400">{{ $row['variant']->label }}</span>
                                    {{ $row['variant']->title }}
                                </a>
                                @if ($row['variant']->key === $worst)
                                    <span class="ml-1 rounded bg-red-200 px-1.5 text-xs text-red-900">worst</span>
                                @elseif ($row['variant']->key === $best)
                                    <span class="ml-1 rounded bg-emerald-200 px-1.5 text-xs text-emerald-900">best</span>
                                @endif
                                @if ($p->error)
                                    <div class="text-xs text-red-700">{{ Str::limit($p->error, 120) }}</div>
                                @elseif (! $row['variant']->ranked)
                                    <div class="text-xs text-stone-500">failure demo — not ranked</div>
                                @endif
                            </td>
                            @foreach ([
                                ['queries', $p->queries, number_format($p->queries)],
                                ['db', $p->dbMs, number_format($p->dbMs, 1)],
                                ['wall', $p->wallMs, number_format($p->wallMs, 1)],
                                ['memory', $p->memoryBytes, Number::fileSize($p->memoryBytes, 1)],
                            ] as [$metric, $value, $formatted])
                                <td class="py-2 pr-3">
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
        </div>
        <x-slot:footer>Best/worst = fewest/most queries (ties: wall time) excluding the failure demos.</x-slot:footer>
    </x-card>
</x-layouts.app>
