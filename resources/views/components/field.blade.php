@props(['name', 'label', 'required' => false, 'hint' => null])
@php($errorKey = str_replace(['[', ']'], ['.', ''], $name))
<div {{ $attributes->merge(['class' => 'field']) }}>
    <label for="{{ $attributes->get('for', $name) }}">{{ $label }} @if ($required)<span class="req" aria-hidden="true">*</span>@endif</label>
    {{ $slot }}
    @if ($hint)<div class="field-hint">{{ $hint }}</div>@endif
    @error($errorKey)<div class="field-error">{{ $message }}</div>@enderror
</div>
