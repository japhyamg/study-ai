@props(['href' => null, 'variant' => 'primary', 'size' => '', 'type' => 'button', 'icon' => null])

@php
    $classes = 'btn btn-'.$variant.($size === 'sm' ? ' btn-sm' : ($size === 'lg' ? ' btn-lg' : ''));
@endphp

@if($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        @if($icon)<x-icon :name="$icon" />@endif{{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>
        @if($icon)<x-icon :name="$icon" />@endif{{ $slot }}
    </button>
@endif
