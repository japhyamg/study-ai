@props(['href' => null, 'variant' => 'primary', 'size' => '', 'type' => 'button'])
@if($href)
    <a href="{{ $href }}" class="btn btn-{{ $variant }} {{ $size === 'sm' ? 'btn-sm' : '' }}">{{ $slot }}</a>
@else
    <button type="{{ $type }}" class="btn btn-{{ $variant }} {{ $size === 'sm' ? 'btn-sm' : '' }}">{{ $slot }}</button>
@endif
