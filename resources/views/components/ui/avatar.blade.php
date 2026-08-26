@props(['name' => '', 'class' => ''])
@php
    $initials = collect(preg_split('/\s+/', trim((string) $name)))
        ->filter()
        ->map(fn ($word) => mb_strtoupper(mb_substr($word, 0, 1)))
        ->take(2)
        ->implode('');
@endphp
<span class="avatar {{ $class }}" aria-hidden="true">{{ $initials ?: '?' }}</span>
