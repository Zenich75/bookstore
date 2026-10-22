<x-layouts.app :title="__('ui.auth.login_title')">
    <x-card class="max-w-md">
        <form method="POST" action="{{ route('login') }}" class="space-y-4">
            @csrf
            <x-form.input type="email" name="email" :label="__('ui.auth.email')" required autofocus autocomplete="username" />
            {{-- type="password": never refilled from old() --}}
            <x-form.input type="password" name="password" :label="__('ui.auth.password')" required autocomplete="current-password" />
            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="remember" value="1" @checked(old('remember'))> {{ __('ui.auth.remember') }}
            </label>
            <div class="flex items-center justify-between">
                <button type="submit" class="btn">{{ __('ui.auth.login') }}</button>
                <a href="{{ route('register') }}" class="text-sm text-amber-700 hover:underline">{{ __('ui.auth.no_account') }}</a>
            </div>
        </form>
    </x-card>
</x-layouts.app>
