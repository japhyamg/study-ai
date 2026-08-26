@props(['label' => null, 'name' => null, 'hint' => null, 'required' => false, 'error' => null])

@php $error = $error ?? ($name ? ($errors->first($name) ?: null) : null); @endphp

<div {{ $attributes->merge(['class' => 'field']) }}>
    @if($label)
        <label class="field-label" @if($name) for="{{ $name }}" @endif>
            {{ $label }}
            @if($required)<span class="text-danger" aria-hidden="true">*</span>@endif
        </label>
    @endif

    {{ $slot }}

    @if($error)
        <p class="field-error">{{ $error }}</p>
    @elseif($hint)
        <p class="field-hint">{{ $hint }}</p>
    @endif
</div>
