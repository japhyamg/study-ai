@props(['type' => 'submit'])
<input type="{{ $type }}" {{ $attributes->merge(['class' => 'btn btn-primary cursor-pointer']) }}>
