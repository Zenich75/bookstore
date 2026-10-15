<dl class="grid grid-cols-2 gap-3 text-sm md:grid-cols-4">
    @foreach ([
        __('lab.common.metrics.queries') => Number::format($profile->queries),
        __('lab.common.metrics.db_time') => __('lab.common.metrics.ms', ['value' => Number::format($profile->dbMs, 1)]),
        __('lab.common.metrics.wall_time') => __('lab.common.metrics.ms', ['value' => Number::format($profile->wallMs, 1)]),
        __('lab.common.metrics.memory') => Number::fileSize($profile->memoryBytes, 1),
    ] as $label => $value)
        <div class="rounded border border-stone-200 bg-white px-3 py-2">
            <dt class="text-xs text-stone-500">{{ $label }}</dt>
            <dd class="text-lg font-semibold">{{ $value }}</dd>
        </div>
    @endforeach
</dl>
