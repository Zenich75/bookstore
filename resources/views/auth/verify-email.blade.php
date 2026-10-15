<x-layouts.app :title="__('ui.auth.verify_title')">
    <x-card class="max-w-xl">
        <p class="text-sm text-stone-700">{{ __('ui.auth.verify_text', ['email' => auth()->user()->email]) }}</p>
        @env('local')
            <p class="mt-2 text-sm text-stone-500">{{ __('ui.auth.mailpit_hint') }} <a href="http://localhost:8025" class="text-amber-700 underline">Mailpit</a></p>
        @endenv
        <x-slot:footer>
            <form method="POST" action="{{ route('verification.send') }}">
                @csrf
                <button type="submit" class="btn-secondary">{{ __('ui.auth.resend') }}</button>
            </form>
        </x-slot:footer>
    </x-card>
</x-layouts.app>
