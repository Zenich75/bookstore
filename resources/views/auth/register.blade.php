<x-layouts.app :title="__('ui.auth.register_title')">
    <x-card class="max-w-md">
        <form method="POST" action="{{ route('register') }}" class="space-y-4">
            @csrf
            <x-field :label="__('ui.auth.name')" name="name">
                <input type="text" name="name" value="{{ old('name') }}" required autofocus autocomplete="name" class="form-input">
            </x-field>
            <x-field :label="__('ui.auth.email')" name="email">
                <input type="email" name="email" value="{{ old('email') }}" required autocomplete="username" class="form-input">
            </x-field>
            <x-field :label="__('ui.auth.password')" name="password">
                <input type="password" name="password" required autocomplete="new-password" class="form-input">
            </x-field>
            <x-field :label="__('ui.auth.password_confirmation')" name="password_confirmation">
                <input type="password" name="password_confirmation" required autocomplete="new-password" class="form-input">
            </x-field>
            <div class="flex items-center justify-between">
                <button type="submit" class="btn">{{ __('ui.auth.register') }}</button>
                <a href="{{ route('login') }}" class="text-sm text-amber-700 hover:underline">{{ __('ui.auth.have_account') }}</a>
            </div>
        </form>
    </x-card>
</x-layouts.app>
