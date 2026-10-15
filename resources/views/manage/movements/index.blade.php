<x-layouts.app :title="__('ui.manage.movements_title', ['warehouse' => $warehouse->name])">
    <p class="-mt-4 mb-6 text-sm text-stone-500">
        <a href="{{ route('manage.warehouses.stocks.index', $warehouse) }}" class="hover:underline">{{ __('ui.manage.back_to_stock') }}</a>
    </p>

    <x-card>
        <table class="w-full text-sm">
            <thead class="text-left text-stone-500">
                <tr>
                    <th class="py-2">{{ __('ui.manage.columns.date') }}</th>
                    <th class="py-2">{{ __('ui.warehouses.columns.book') }}</th>
                    <th class="py-2 text-right">{{ __('ui.manage.columns.delta') }}</th>
                    <th class="py-2">{{ __('ui.manage.reason') }}</th>
                    <th class="py-2">{{ __('ui.manage.columns.user') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($movements as $movement)
                    <tr class="border-t border-stone-100">
                        <td class="py-1.5 text-stone-500">{{ $movement->created_at?->translatedFormat('j M Y, H:i') }}</td>
                        <td class="py-1.5">{{ $movement->book?->title ?? '—' }}</td>
                        <td @class(['py-1.5 text-right tabular-nums', 'text-emerald-700' => $movement->delta > 0, 'text-red-700' => $movement->delta < 0])>{{ $movement->delta > 0 ? '+' : '' }}{{ $movement->delta }}</td>
                        <td class="py-1.5">{{ $movement->reason->label() }}</td>
                        <td class="py-1.5 text-stone-500">{{ $movement->user?->name ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-4 text-center text-stone-500">{{ __('ui.manage.no_movements') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-card>

    <div class="mt-6">{{ $movements->links() }}</div>
</x-layouts.app>
