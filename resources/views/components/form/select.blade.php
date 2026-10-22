{{--
    <x-form.select name="format" :options="BookFormat::cases()" :value="$book->format" />
    options: backed enum cases (value + label()) or a [value => label] array.
    placeholder: adds an empty first option (e.g. "—" for "none").
--}}
@props(['name', 'label' => null, 'options' => [], 'value' => null, 'bag' => 'default', 'placeholder' => null, 'hint' => null])

@php
    $key = str_replace(['[', ']'], ['.', ''], $name);
    $id = $attributes->get('id', 'field-'.Str::slug(str_replace('.', '-', $key)));
    $invalid = $errors->getBag($bag)->has($key);
    $selected = old($key, $value instanceof BackedEnum ? $value->value : $value);
    $choices = collect($options)->mapWithKeys(fn ($option, $optionValue) => $option instanceof BackedEnum
        ? [$option->value => $option->label()]
        : [$optionValue => $option]);
@endphp

<div {{ $attributes->only('class')->merge(['class' => 'block text-sm']) }}>
    @if ($label)
        <label for="{{ $id }}" class="mb-1 block font-medium text-stone-700">{{ $label }}</label>
    @endif
    <select name="{{ $name }}" id="{{ $id }}"
            @if ($invalid) aria-invalid="true" aria-describedby="{{ 'error-'.Str::slug(str_replace('.', '-', $key)) }}" @endif
            {{ $attributes->except(['class', 'id'])->class(['form-input', 'border-red-500' => $invalid]) }}>
        @isset($placeholder)
            <option value="">{{ $placeholder }}</option>
        @endisset
        @foreach ($choices as $optionValue => $optionLabel)
            {{-- Compare as strings: old() is always a string, model values may be ints. --}}
            <option value="{{ $optionValue }}" @selected((string) $selected === (string) $optionValue)>{{ $optionLabel }}</option>
        @endforeach
    </select>
    @if ($hint)
        <span class="mt-1 block text-xs text-stone-500">{{ $hint }}</span>
    @endif
    <x-form.error :name="$key" :bag="$bag" />
</div>
