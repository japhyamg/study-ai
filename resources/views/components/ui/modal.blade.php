@props(['title' => 'Confirm'])
<div class="modal-backdrop" id="{{ $id ?? 'modal' }}" x-data x-on:keydown.escape.window="$el.removeAttribute('open')">
    <div class="modal">
        <div class="px-5 py-4" style="border-bottom:1px solid var(--line)">
            <h3 class="font-display text-lg">{{ $title }}</h3>
        </div>
        <div class="px-5 py-4 text-sm muted">{{ $slot }}</div>
        <div class="px-5 py-3 flex justify-end gap-2" style="border-top:1px solid var(--line)">
            <button type="button" class="btn btn-ghost" onclick="this.closest('.modal-backdrop').classList.remove('open')">Cancel</button>
            {{ $actions ?? '' }}
        </div>
    </div>
</div>
