<x-layouts.studyai title="{{ $role }} Workspace">
    <div class="surface p-8 text-center text-muted">
        <div class="text-lg font-semibold text-ink">{{ $role }} workspace</div>
        <p class="mt-2 text-sm">This area will be built in the next phase. You are authenticated as <strong>{{ auth()->user()->name }}</strong>.</p>
        <p class="mt-1 text-xs">Role: {{ auth()->user()->highestRole() }}</p>
    </div>
</x-layouts.studyai>
