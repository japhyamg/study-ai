@props(['label', 'value', 'tone' => ''])
<div class="stat">
    <div class="stat-label">{{ $label }}</div>
    <div class="stat-value {{ $tone ? 'text-['.$tone.']' : '' }}">{{ $value }}</div>
</div>
