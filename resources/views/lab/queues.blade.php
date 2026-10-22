<x-layouts.app :title="__('lab.queues.title')">
    <p class="-mt-2 mb-2 max-w-3xl text-sm text-stone-600">{{ __('lab.queues.intro') }}</p>
    <p class="mb-6 text-xs text-stone-500">{{ __('lab.queues.connection', ['connection' => $connection]) }}</p>

    @session('lab_details')
        @if ($value)
            <x-card class="mb-6">
                <x-slot:header>{{ __('lab.queues.last_dispatch') }}</x-slot:header>
                <pre class="overflow-x-auto text-xs">{{ json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
            </x-card>
        @endif
    @endsession

    <div class="grid gap-8 xl:grid-cols-[1fr_24rem]">
        <div class="space-y-6">
            @foreach ($groups as $group => $variants)
                <section>
                    <h2 class="mb-3 text-lg font-semibold">{{ __('lab.queues.groups.'.$group) }}</h2>
                    <div class="grid gap-3 md:grid-cols-2">
                        @foreach ($variants as $variant)
                            <x-card>
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <code class="text-sm font-semibold">{{ __('lab.queues.variants.'.$variant.'.title') }}</code>
                                        <p class="mt-1 text-xs text-stone-600">{{ __('lab.queues.variants.'.$variant.'.text') }}</p>
                                    </div>
                                    <form method="POST" action="{{ route('lab.queues.dispatch', $variant) }}">
                                        @csrf
                                        <button type="submit" class="btn-secondary shrink-0" data-variant="{{ $variant }}">{{ __('lab.queues.dispatch') }}</button>
                                    </form>
                                </div>
                            </x-card>
                        @endforeach
                    </div>
                </section>
            @endforeach
        </div>

        <aside class="space-y-6">
            <x-card>
                <x-slot:header>{{ __('lab.queues.pending') }}</x-slot:header>
                <ul class="text-sm">
                    @forelse ($pending as $queue => $count)
                        <li class="flex justify-between"><code>{{ $queue }}</code><span>{{ $count }}</span></li>
                    @empty
                        <li class="text-stone-500">{{ __('lab.queues.no_pending') }}</li>
                    @endforelse
                </ul>
            </x-card>

            <x-card>
                <x-slot:header>{{ __('lab.queues.failed_title') }}</x-slot:header>
                <ul class="space-y-2 text-xs">
                    @forelse ($failed as $job)
                        <li>
                            <code>{{ json_decode($job->payload)->displayName ?? '?' }}</code> · {{ $job->queue }} · {{ $job->failed_at }}
                            <span class="block truncate text-stone-500" title="{{ Str::before($job->exception, "\n") }}">{{ Str::before($job->exception, "\n") }}</span>
                        </li>
                    @empty
                        <li class="text-stone-500">{{ __('lab.queues.no_failed') }}</li>
                    @endforelse
                </ul>
                <x-slot:footer class="space-y-2">
                    <div class="flex gap-2">
                        @foreach (['retry' => 'retry_all', 'flush' => 'flush'] as $action => $label)
                            <form method="POST" action="{{ route('lab.queues.failed', $action) }}">
                                @csrf
                                <button type="submit" class="btn-secondary"><code>{{ __('lab.queues.'.$label) }}</code></button>
                            </form>
                        @endforeach
                    </div>
                    <p class="text-xs text-stone-500">{{ __('lab.queues.cli_hint') }}</p>
                </x-slot:footer>
            </x-card>
        </aside>
    </div>

    <section class="mt-8">
        <h2 class="mb-3 text-lg font-semibold">{{ __('lab.queues.runs') }}</h2>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="uppercase text-stone-400">
                    <tr>
                        @foreach (['time', 'variant', 'job', 'queue', 'attempt', 'payload', 'restore_queries', 'details'] as $column)
                            <th class="py-1 pr-3">{{ __('lab.queues.columns.'.$column) }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse ($runs as $run)
                        <tr class="border-t border-stone-100 align-top" data-run="{{ $run->variant }}">
                            <td class="py-1 pr-3 whitespace-nowrap">{{ Illuminate\Support\Carbon::parse($run->created_at)->format('H:i:s') }}</td>
                            <td class="py-1 pr-3">{{ $run->variant }}</td>
                            <td class="py-1 pr-3"><code>{{ $run->job }}</code></td>
                            <td class="py-1 pr-3">{{ $run->queue }}</td>
                            <td class="py-1 pr-3">{{ $run->attempt }}</td>
                            <td class="py-1 pr-3">{{ $run->payload_bytes !== null ? Number::fileSize($run->payload_bytes, 1) : '—' }}</td>
                            <td class="py-1 pr-3">{{ $run->restore_queries ?? '—' }}</td>
                            <td class="py-1"><code class="break-all">{{ $run->details }}</code></td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="py-3 text-stone-500">{{ __('lab.queues.no_runs') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</x-layouts.app>
