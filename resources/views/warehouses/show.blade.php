<x-layouts.app :title="$warehouse->name">
    <p class="-mt-4 mb-6 text-sm text-stone-500">
        {{ $warehouse->address }}, {{ $warehouse->city }} · Manager: {{ $warehouse->manager?->name ?? '—' }}
    </p>

    <x-card>
        <table class="w-full text-sm">
            <thead class="text-left text-stone-500">
                <tr>
                    <th class="py-2">#</th>
                    <th class="py-2">Book</th>
                    <th class="py-2">Shelf</th>
                    <th class="py-2 text-right">Quantity</th>
                    <th class="py-2 text-right">Reserved</th>
                    <th class="py-2 text-right">Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($stocks as $stock)
                    <tr class="border-t border-stone-100">
                        {{-- $loop->iteration is 1-based within the current page; add the page offset. --}}
                        <td class="py-1.5 text-stone-400">{{ $stocks->firstItem() + $loop->iteration - 1 }}</td>
                        <td class="py-1.5"><a href="{{ route('books.show', $stock->book) }}" class="hover:text-amber-700">{{ $stock->book->title }}</a></td>
                        <td class="py-1.5">{{ $stock->shelf_code }}</td>
                        <td class="py-1.5 text-right">{{ $stock->quantity }}</td>
                        <td class="py-1.5 text-right">{{ $stock->reserved }}</td>
                        <td class="py-1.5 text-right"><x-stock-badge :quantity="$stock->quantity" :reserved="$stock->reserved" /></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-4 text-center text-stone-500">This warehouse is empty.</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-card>

    <div class="mt-6">{{ $stocks->links() }}</div>
</x-layouts.app>
