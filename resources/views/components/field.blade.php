{{-- Form field wrapper: label, the input (slot) and its validation error. --}}
@props(['label', 'name'])

<label {{ $attributes->merge(['class' => 'block text-sm']) }}>
    <span class="mb-1 block font-medium text-stone-700">{{ $label }}</span>
    {{ $slot }}
    @error($name)
        <span class="mt-1 block text-xs text-red-700">{{ $message }}</span>
    @enderror
</label>
