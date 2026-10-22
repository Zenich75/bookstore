<x-layouts.app :title="__('ui.auth.register_title')">
    <x-card class="max-w-md">
        <form method="POST" action="{{ route('register') }}" class="space-y-4">
            @csrf
            <x-form.input name="name" :label="__('ui.auth.name')" required autofocus autocomplete="name" />
            <x-form.input type="email" name="email" :label="__('ui.auth.email')" required autocomplete="username" />
            <x-form.input type="password" name="password" :label="__('ui.auth.password')" required autocomplete="new-password" />
            <x-form.input type="password" name="password_confirmation" :label="__('ui.auth.password_confirmation')" required autocomplete="new-password" />
            <div class="flex items-center justify-between">
                <button type="submit" class="btn">{{ __('ui.auth.register') }}</button>
                <a href="{{ route('login') }}" class="text-sm text-amber-700 hover:underline">{{ __('ui.auth.have_account') }}</a>
            </div>
        </form>
    </x-card>
</x-layouts.app>
