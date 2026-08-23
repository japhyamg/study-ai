@props(['label', 'value', 'meta' => null, 'icon' => null, 'href' => null, 'tone' => null])

@php $tag = $href ? 'a' : 'div'; @endphp

<{{ $tag }}
    @if($href) href="{{ $href }}" @endif
    {{ $attributes->merge(['class' => 'stat'.($href ? ' transition-colors hover:border-line-strong' : '')]) }}>
    <div class="flex items-start justify-between gap-2">
        <span class="stat-label">{{ $label }}</span>
        @if($icon)
            <span class="text-faint"><x-icon :name="$icon" /></span>
        @endif
    </div>
    <div @class(['stat-value', 'text-success' => $tone === 'ok', 'text-danger' => $tone === 'danger'])>{{ $value }}</div>
    @if($meta)<div class="stat-meta">{{ $meta }}</div>@endif
    @isset($slot){{ $slot }}@endisset
</{{ $tag }}>
