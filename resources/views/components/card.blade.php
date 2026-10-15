{{-- Anonymous component with named slots: <x-slot:header>, <x-slot:footer>, default $slot. --}}
<div {{ $attributes->merge(['class' => 'rounded-lg border border-stone-200 bg-white']) }}>
    {{-- @isset: named slots are only defined when the caller passes them. --}}
    @isset($header)
        <div {{ $header->attributes->merge(['class' => 'border-b border-stone-200 px-4 py-3 font-semibold']) }}>
            {{ $header }}
        </div>
    @endisset

    <div class="px-4 py-3">
        {{ $slot }}
    </div>

    @isset($footer)
        <div class="border-t border-stone-200 px-4 py-2 text-sm text-stone-500">
            {{ $footer }}
        </div>
    @endisset
</div>
