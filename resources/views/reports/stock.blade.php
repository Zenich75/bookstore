<x-layouts.app title="Stock report">
    @include('reports._nav')

    <div class="mb-6 grid grid-cols-3 gap-4">
        <x-card class="text-center"><div class="text-2xl font-semibold">{{ number_format($totals['quantity']) }}</div><div class="text-sm text-stone-500">units</div></x-card>
        <x-card class="text-center"><div class="text-2xl font-semibold">{{ number_format($totals['reserved']) }}</div><div class="text-sm text-stone-500">reserved</div></x-card>
        <x-card class="text-center"><div class="text-2xl font-semibold">{{ Number::currency($totals['value_cents'] / 100, 'USD') }}</div><div class="text-sm text-stone-500">stock value</div></x-card>
    </div>

    <div class="space-y-6">
        @forelse ($warehouses as $warehouse => $rows)
            <x-card>
                <x-slot:header>{{ $warehouse }}</x-slot:header>
                <table class="w-full text-sm">
                    <thead class="text-left text-xs uppercase text-stone-400">
                        <tr>
                            <th class="py-1">Root genre</th>
                            <th class="py-1 text-right">Titles</th>
                            <th class="py-1 text-right">Quantity</th>
                            <th class="py-1 text-right">Reserved</th>
                            <th class="py-1 text-right">Value</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            <tr class="border-t border-stone-100">
                                <td class="py-1">{{ $row->genre }}</td>
                                <td class="py-1 text-right tabular-nums">{{ number_format($row->books) }}</td>
                                <td class="py-1 text-right tabular-nums">{{ number_format($row->quantity) }}</td>
                                <td class="py-1 text-right tabular-nums">{{ number_format($row->reserved) }}</td>
                                <td class="py-1 text-right tabular-nums">{{ Number::currency($row->value_cents / 100, 'USD') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <x-slot:footer>
                    {{ number_format($rows->sum('quantity')) }} units · {{ Number::currency($rows->sum('value_cents') / 100, 'USD') }}
                </x-slot:footer>
            </x-card>
        @empty
            <p class="text-stone-500">No stock.</p>
        @endforelse
    </div>
</x-layouts.app>
