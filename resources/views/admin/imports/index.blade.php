<x-layouts.app :title="__('ui.imports.title')">
    <div class="grid gap-6 lg:grid-cols-[22rem_1fr]">
        <x-card>
            <x-slot:header>{{ __('ui.imports.upload') }}</x-slot:header>
            {{-- enctype: without multipart/form-data the browser sends only the file name. --}}
            <form method="POST" action="{{ route('admin.imports.store') }}" enctype="multipart/form-data" class="space-y-4">
                @csrf
                <x-form.select name="warehouse_id" :label="__('ui.imports.warehouse')" :options="$warehouses" placeholder="—" required />
                <x-form.input type="file" name="file" :label="__('ui.imports.file')" accept=".csv,text/csv" required
                              :hint="__('ui.imports.file_hint', ['max' => Number::fileSize(config('warehouse.import.max_kb') * 1024), 'rows' => Number::format(config('warehouse.import.max_rows'))])" />
                <button type="submit" class="btn">{{ __('ui.imports.submit') }}</button>
            </form>
            <x-slot:footer>
                <p class="text-xs text-stone-500">{{ __('ui.imports.columns_hint') }}</p>
                <code class="mt-1 block break-all text-xs">{{ implode(',', $columns) }}</code>
            </x-slot:footer>
        </x-card>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="text-xs uppercase text-stone-400">
                    <tr>
                        <th class="py-2 pr-3">#</th>
                        <th class="py-2 pr-3">{{ __('ui.imports.file') }}</th>
                        <th class="py-2 pr-3">{{ __('ui.imports.warehouse') }}</th>
                        <th class="py-2 pr-3">{{ __('ui.imports.status') }}</th>
                        <th class="py-2 pr-3 text-right">{{ __('ui.imports.imported') }}</th>
                        <th class="py-2 pr-3 text-right">{{ __('ui.imports.failed') }}</th>
                        <th class="py-2">{{ __('ui.imports.uploaded') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($imports as $import)
                        <tr class="border-t border-stone-100">
                            <td class="py-2 pr-3"><a href="{{ route('admin.imports.show', $import) }}" class="text-amber-700 hover:underline">{{ $import->id }}</a></td>
                            <td class="py-2 pr-3">{{ $import->original_name }}</td>
                            <td class="py-2 pr-3">{{ $import->warehouse->name }}</td>
                            <td class="py-2 pr-3">{{ $import->status->label() }}</td>
                            <td class="py-2 pr-3 text-right">{{ Number::format($import->imported) }} / {{ Number::format($import->total_rows) }}</td>
                            <td class="py-2 pr-3 text-right">{{ Number::format($import->failed) }}</td>
                            <td class="py-2 text-stone-500">{{ $import->user?->name }} · {{ $import->created_at->diffForHumans() }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="py-4 text-stone-500">{{ __('ui.imports.empty') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
            <div class="mt-4">{{ $imports->links() }}</div>
        </div>
    </div>
</x-layouts.app>
