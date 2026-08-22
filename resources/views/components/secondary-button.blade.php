@props(['type' => 'button'])
<button type="{{ $type }}" {{ $attributes->merge(['class' => 'btn btn-ghost cursor-pointer']) }}>{{ $slot }}</button>
