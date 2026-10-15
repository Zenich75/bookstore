<x-layouts.app title="Warehouses">
    <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
        @forelse ($warehouses as $warehouse)
            <x-card>
                <x-slot:header>
                    <a href="{{ route('warehouses.show', $warehouse) }}" class="hover:text-amber-700">{{ $warehouse->name }}</a>
                </x-slot:header>
                <p class="text-sm text-stone-600">{{ $warehouse->address }}, {{ $warehouse->city }}</p>
                <p class="mt-2 text-sm">{{ number_format($warehouse->stocks_count) }} titles · {{ number_format((int) $warehouse->stocks_sum_quantity) }} units</p>
                <x-slot:footer>Manager: {{ $warehouse->manager?->name ?? '—' }}</x-slot:footer>
            </x-card>
        @empty
            <p class="text-stone-500">No warehouses.</p>
        @endforelse
    </div>
</x-layouts.app>
