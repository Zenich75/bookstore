{{-- Action buttons, each behind the policy ability that its route checks. --}}
<div class="inline-flex flex-wrap justify-end gap-2">
    @if ($book->trashed())
        @can('restore', $book)
            <form method="POST" action="{{ route('admin.books.restore', $book) }}">
                @csrf @method('PATCH')
                <button type="submit" class="btn-secondary">{{ __('ui.admin.restore') }}</button>
            </form>
        @endcan
        @can('forceDelete', $book)
            <form method="POST" action="{{ route('admin.books.force-delete', $book) }}" onsubmit="return confirm(@js(__('ui.admin.confirm_force_delete')))">
                @csrf @method('DELETE')
                <button type="submit" class="btn-danger">{{ __('ui.admin.force_delete') }}</button>
            </form>
        @endcan
    @else
        @can('manage', $book)
            <a href="{{ route('admin.books.edit', $book) }}" class="btn-secondary">{{ __('ui.admin.edit') }}</a>
            <form method="POST" action="{{ route('admin.books.destroy', $book) }}">
                @csrf @method('DELETE')
                <button type="submit" class="btn-danger">{{ __('ui.admin.delete') }}</button>
            </form>
        @endcan
    @endif
    @cannot('manage', $book)
        <span class="text-xs text-stone-400">{{ __('ui.admin.read_only') }}</span>
    @endcannot
</div>
