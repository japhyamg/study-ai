@props(['status'])

@if ($status)
    <div {{ $attributes->merge(['class' => 'alert alert-ok']) }} role="status">
        <x-icon name="check-circle" class="mt-px flex-none" />
        <span>{{ $status }}</span>
    </div>
@endif
