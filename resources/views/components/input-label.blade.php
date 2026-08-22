@props(['value' => null, 'for' => null])
<label for="{{ $for }}" class="field-label">{{ $value ?? $slot }}</label>
