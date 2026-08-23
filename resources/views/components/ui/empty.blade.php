@props(['message' => '', 'title' => null, 'icon' => null, 'action' => null])
<div {{ $attributes->merge(['class' => 'empty']) }}>
    @if($icon)
        <span class="mb-1 grid h-10 w-10 place-items-center rounded-full bg-surface-sunk text-faint">
            <x-icon :name="$icon" />
        </span>
    @endif
    @if($title)<p class="empty-title">{{ $title }}</p>@endif
    <p>{{ $slot->isEmpty() ? $message : $slot }}</p>
    @if($action)<div class="mt-2">{{ $action }}</div>@endif
</div>
