<x-layouts.app :title="__('ui.manage.edit_stock_title', ['title' => $stock->book->title])">
    <p class="-mt-4 mb-6 text-sm text-stone-500">
        <a href="{{ route('manage.warehouses.stocks.index', $warehouse) }}" class="hover:underline">{{ $warehouse->name }}</a>
    </p>

    <x-card class="max-w-xl">
        <form method="POST" action="{{ route('manage.warehouses.stocks.update', [$warehouse, $stock]) }}" class="space-y-4">
            @csrf
            @method('PUT')
            <div class="grid grid-cols-2 gap-4">
                <x-field :label="__('ui.warehouses.columns.quantity')" name="quantity">
                    <input type="number" name="quantity" min="0" value="{{ old('quantity', $stock->quantity) }}" required class="form-input">
                </x-field>
                <x-field :label="__('ui.warehouses.columns.reserved')" name="reserved">
                    <input type="number" name="reserved" min="0" value="{{ old('reserved', $stock->reserved) }}" required class="form-input">
                </x-field>
            </div>
            <x-field :label="__('ui.warehouses.columns.shelf')" name="shelf_code">
                <input type="text" name="shelf_code" maxlength="20" value="{{ old('shelf_code', $stock->shelf_code) }}" class="form-input">
            </x-field>
            {{-- Stored in Stock::$movementReason; StockObserver writes it to stock_movements. --}}
            <x-field :label="__('ui.manage.reason')" name="reason">
                <select name="reason" class="form-input">
                    @foreach ($reasons as $reason)
                        <option value="{{ $reason->value }}" @selected(old('reason', 'adjustment') === $reason->value)>{{ $reason->label() }}</option>
                    @endforeach
                </select>
            </x-field>
            <button type="submit" class="btn">{{ __('ui.manage.save') }}</button>
        </form>
    </x-card>
</x-layouts.app>
