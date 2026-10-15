<dl class="grid grid-cols-2 gap-3 text-sm md:grid-cols-4">
    @foreach ([
        'Queries' => number_format($profile->queries),
        'DB time' => number_format($profile->dbMs, 1).' ms',
        'Wall time' => number_format($profile->wallMs, 1).' ms',
        'Peak memory' => Number::fileSize($profile->memoryBytes, 1),
    ] as $label => $value)
        <div class="rounded border border-stone-200 bg-white px-3 py-2">
            <dt class="text-xs text-stone-500">{{ $label }}</dt>
            <dd class="text-lg font-semibold">{{ $value }}</dd>
        </div>
    @endforeach
</dl>
