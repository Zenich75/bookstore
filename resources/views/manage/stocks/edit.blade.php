<x-layouts.app :title="__('ui.manage.edit_stock_title', ['title' => $stock->book->title])">
    <p class="-mt-4 mb-6 text-sm text-stone-500">
        <a href="{{ route('manage.warehouses.stocks.index', $warehouse) }}" class="hover:underline">{{ $warehouse->name }}</a>
    </p>

    <x-card class="max-w-xl">
        <form method="POST" action="{{ route('manage.warehouses.stocks.update', [$warehouse, $stock]) }}" class="space-y-4">
            @csrf
            @method('PUT')
            <div class="grid grid-cols-2 gap-4">
                <x-form.input type="number" name="quantity" :label="__('ui.warehouses.columns.quantity')" :value="$stock->quantity" min="0" required />
                <x-form.input type="number" name="reserved" :label="__('ui.warehouses.columns.reserved')" :value="$stock->reserved" min="0" required />
            </div>
            <x-form.input name="shelf_code" :label="__('ui.warehouses.columns.shelf')" :value="$stock->shelf_code" maxlength="20" :hint="__('ui.manage.shelf_hint')" />
            {{-- Stored in Stock::$movementReason; StockObserver writes it to stock_movements. --}}
            <x-form.select name="reason" :label="__('ui.manage.reason')" :options="$reasons" value="adjustment" :hint="__('ui.manage.reason_hint')" />
            <button type="submit" class="btn">{{ __('ui.manage.save') }}</button>
        </form>
    </x-card>
</x-layouts.app>
