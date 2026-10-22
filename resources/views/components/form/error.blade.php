{{--
    Validation message for one field. "name" is the dot key (authors.0.role);
    "bag" picks a named error bag ($errors->reviewEdit). The id is what the
    field's aria-describedby points to.
--}}
@props(['name', 'bag' => 'default'])

@error($name, $bag)
    <span id="{{ 'error-'.Str::slug(str_replace('.', '-', $name)) }}" {{ $attributes->merge(['class' => 'mt-1 block text-xs text-red-700']) }}>{{ $message }}</span>
@enderror
