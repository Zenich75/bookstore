<x-layouts.app :title="__('ui.notifications.title')">
    <div class="mb-4 flex justify-end">
        <form method="POST" action="{{ route('notifications.read-all') }}">
            @csrf
            <button type="submit" class="btn-secondary">{{ __('ui.notifications.read_all') }}</button>
        </form>
    </div>

    <div class="space-y-2">
        @forelse ($notifications as $notification)
            <x-card @class(['border-l-4 border-l-amber-500' => $notification->unread()]) data-notification>
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <span @class(['text-sm', 'font-semibold' => $notification->unread()])>
                        {{-- Text built at display time from the stored kind + params (current locale). --}}
                        {{ __('ui.notifications.kinds.'.($notification->data['kind'] ?? 'unknown'), $notification->data['params'] ?? []) }}
                    </span>
                    <span class="flex items-center gap-3 text-xs text-stone-500">
                        {{ $notification->created_at->diffForHumans() }}
                        <form method="POST" action="{{ route('notifications.read', $notification->id) }}">
                            @csrf
                            <button type="submit" class="text-amber-700 hover:underline">{{ $notification->unread() ? __('ui.notifications.open_and_read') : __('ui.notifications.open') }}</button>
                        </form>
                    </span>
                </div>
            </x-card>
        @empty
            <p class="text-sm text-stone-500">{{ __('ui.notifications.empty') }}</p>
        @endforelse
    </div>

    <div class="mt-6">{{ $notifications->links() }}</div>
</x-layouts.app>
