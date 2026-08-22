@props(['messages' => null])
@if ($messages)
    <ul class="text-danger text-xs mt-1 space-y-0.5">
        @foreach ((array) $messages as $message)
            <li>{{ $message }}</li>
        @endforeach
    </ul>
@endif
