@props(['href' => null])
@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => 'btn btn-danger']) }}>{{ $slot }}</a>
@else
    <button type="submit" {{ $attributes->merge(['class' => 'btn btn-danger cursor-pointer']) }}>{{ $slot }}</button>
@endif
