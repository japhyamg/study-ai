@props(['message' => ''])
<div class="empty">{{ $slot ?: $message }}</div>
