<nav class="-mt-4 mb-6 flex gap-4 text-sm">
    <a href="{{ route('reports.stock') }}" @class(['hover:text-amber-700', 'font-semibold text-amber-700' => request()->routeIs('reports.stock')])>Stock by warehouse &amp; genre</a>
    <a href="{{ route('reports.top-authors') }}" @class(['hover:text-amber-700', 'font-semibold text-amber-700' => request()->routeIs('reports.top-authors')])>Top authors</a>
    <a href="{{ route('reports.stock-csv') }}" class="ml-auto text-amber-700 hover:underline">Download stock CSV ↓</a>
</nav>
