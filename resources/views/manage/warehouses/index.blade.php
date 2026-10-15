<x-layouts.app :title="__('ui.warehouses.title')">
    <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
        @forelse ($warehouses as $warehouse)
            <x-card>
                <x-slot:header>
                    <a href="{{ route('manage.warehouses.stocks.index', $warehouse) }}" class="hover:text-amber-700">{{ $warehouse->name }}</a>
                </x-slot:header>
                <p class="text-sm text-stone-600">{{ $warehouse->address }}, {{ $warehouse->city }}</p>
                <p class="mt-2 text-sm">
                    {{ trans_choice('ui.counts.titles', $warehouse->stocks_count, ['count' => Number::format($warehouse->stocks_count)]) }} ·
                    {{ trans_choice('ui.counts.units', (int) $warehouse->stocks_sum_quantity, ['count' => Number::format((int) $warehouse->stocks_sum_quantity)]) }}
                </p>
                <x-slot:footer class="flex justify-between">
                    <span>{{ __('ui.warehouses.manager', ['name' => $warehouse->manager?->name ?? '—']) }}</span>
                    <a href="{{ route('manage.warehouses.movements.index', $warehouse) }}" class="text-amber-700 hover:underline">{{ __('ui.manage.movements') }}</a>
                </x-slot:footer>
            </x-card>
        @empty
            <p class="text-stone-500">{{ __('ui.warehouses.empty') }}</p>
        @endforelse
    </div>
</x-layouts.app>
