{{--
    <x-form.input name="authors[0][sort_order]" :label="..." :value="$model->attr" bag="..." />
    - old() takes the dot key (authors.0.sort_order), so the bracket name is converted;
    - after a failed submit old() wins over the model value; passwords are never refilled;
    - aria-invalid / aria-describedby link the field to its error for screen readers.
--}}
@props(['name', 'label' => null, 'type' => 'text', 'value' => null, 'bag' => 'default', 'hint' => null])

@php
    $key = str_replace(['[', ']'], ['.', ''], $name);
    $id = $attributes->get('id', 'field-'.Str::slug(str_replace('.', '-', $key)));
    $invalid = $errors->getBag($bag)->has($key);
@endphp

<div {{ $attributes->only('class')->merge(['class' => 'block text-sm']) }}>
    @if ($label)
        <label for="{{ $id }}" class="mb-1 block font-medium text-stone-700">{{ $label }}</label>
    @endif
    <input type="{{ $type }}" name="{{ $name }}" id="{{ $id }}"
           @unless ($type === 'password') value="{{ old($key, $value) }}" @endunless
           @if ($invalid) aria-invalid="true" aria-describedby="{{ 'error-'.Str::slug(str_replace('.', '-', $key)) }}" @endif
           {{ $attributes->except(['class', 'id'])->class(['form-input', 'border-red-500' => $invalid]) }}>
    @if ($hint)
        <span class="mt-1 block text-xs text-stone-500">{{ $hint }}</span>
    @endif
    <x-form.error :name="$key" :bag="$bag" />
</div>
