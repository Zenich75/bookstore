<x-layouts.app :title="__('reports.stock.title')">
    @include('reports._nav')

    <div class="mb-6 grid grid-cols-3 gap-4">
        <x-card class="text-center"><div class="text-2xl font-semibold">{{ Number::format($totals['quantity']) }}</div><div class="text-sm text-stone-500">{{ __('reports.stock.units') }}</div></x-card>
        <x-card class="text-center"><div class="text-2xl font-semibold">{{ Number::format($totals['reserved']) }}</div><div class="text-sm text-stone-500">{{ __('reports.stock.reserved') }}</div></x-card>
        <x-card class="text-center"><div class="text-2xl font-semibold">{{ Number::currency($totals['value_cents'] / 100, in: 'UAH', locale: 'uk') }}</div><div class="text-sm text-stone-500">{{ __('reports.stock.value') }}</div></x-card>
    </div>

    <div class="space-y-6">
        @forelse ($warehouses as $warehouse => $rows)
            <x-card>
                <x-slot:header>{{ $warehouse }}</x-slot:header>
                <table class="w-full text-sm">
                    <thead class="text-left text-xs uppercase text-stone-400">
                        <tr>
                            <th class="py-1">{{ __('reports.stock.columns.genre') }}</th>
                            <th class="py-1 text-right">{{ __('reports.stock.columns.titles') }}</th>
                            <th class="py-1 text-right">{{ __('reports.stock.columns.quantity') }}</th>
                            <th class="py-1 text-right">{{ __('reports.stock.columns.reserved') }}</th>
                            <th class="py-1 text-right">{{ __('reports.stock.columns.value') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            <tr class="border-t border-stone-100">
                                <td class="py-1">{{ $row->genre }}</td>
                                <td class="py-1 text-right tabular-nums">{{ Number::format($row->books) }}</td>
                                <td class="py-1 text-right tabular-nums">{{ Number::format($row->quantity) }}</td>
                                <td class="py-1 text-right tabular-nums">{{ Number::format($row->reserved) }}</td>
                                <td class="py-1 text-right tabular-nums">{{ Number::currency($row->value_cents / 100, in: 'UAH', locale: 'uk') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <x-slot:footer>
                    {{ trans_choice('ui.counts.units', $rows->sum('quantity'), ['count' => Number::format($rows->sum('quantity'))]) }} · {{ Number::currency($rows->sum('value_cents') / 100, in: 'UAH', locale: 'uk') }}
                </x-slot:footer>
            </x-card>
        @empty
            <p class="text-stone-500">{{ __('reports.stock.empty') }}</p>
        @endforelse
    </div>
</x-layouts.app>
