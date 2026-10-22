{{-- Rating 1..max as radio buttons in a fieldset (one label for the group). --}}
@props(['name' => 'rating', 'label' => null, 'value' => null, 'max' => 5, 'bag' => 'default'])

@php
    $key = str_replace(['[', ']'], ['.', ''], $name);
    $invalid = $errors->getBag($bag)->has($key);
    $current = (string) old($key, $value);
@endphp

<fieldset {{ $attributes->merge(['class' => 'text-sm']) }}
          @if ($invalid) aria-invalid="true" aria-describedby="{{ 'error-'.Str::slug(str_replace('.', '-', $key)) }}" @endif>
    @if ($label)
        <legend class="mb-1 font-medium text-stone-700">{{ $label }}</legend>
    @endif
    <div class="flex gap-3">
        @for ($i = 1; $i <= $max; $i++)
            <label class="flex items-center gap-1">
                <input type="radio" name="{{ $name }}" value="{{ $i }}" @checked($current === (string) $i)>
                <span class="text-amber-500" aria-hidden="true">{{ str_repeat('★', $i) }}</span>
                <span class="sr-only">{{ __('ui.rating.title', ['rating' => $i, 'max' => $max]) }}</span>
            </label>
        @endfor
    </div>
    <x-form.error :name="$key" :bag="$bag" />
</fieldset>
