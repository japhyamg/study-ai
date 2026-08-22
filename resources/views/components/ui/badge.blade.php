@props(['tone' => ''])
<span class="badge {{ $tone ? 'badge-'.$tone : '' }}">{{ $slot }}</span>
