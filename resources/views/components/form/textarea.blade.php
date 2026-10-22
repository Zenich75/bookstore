{{-- Like <x-form.input>, for multi-line text; the value goes between the tags (escaped by {{ }}). --}}
@props(['name', 'label' => null, 'value' => null, 'bag' => 'default', 'hint' => null])

@php
    $key = str_replace(['[', ']'], ['.', ''], $name);
    $id = $attributes->get('id', 'field-'.Str::slug(str_replace('.', '-', $key)));
    $invalid = $errors->getBag($bag)->has($key);
@endphp

<div {{ $attributes->only('class')->merge(['class' => 'block text-sm']) }}>
    @if ($label)
        <label for="{{ $id }}" class="mb-1 block font-medium text-stone-700">{{ $label }}</label>
    @endif
    <textarea name="{{ $name }}" id="{{ $id }}"
              @if ($invalid) aria-invalid="true" aria-describedby="{{ 'error-'.Str::slug(str_replace('.', '-', $key)) }}" @endif
              {{ $attributes->except(['class', 'id'])->class(['form-input', 'border-red-500' => $invalid]) }}>{{ old($key, $value) }}</textarea>
    @if ($hint)
        <span class="mt-1 block text-xs text-stone-500">{{ $hint }}</span>
    @endif
    <x-form.error :name="$key" :bag="$bag" />
</div>
