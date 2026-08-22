<x-layouts.studyai title="Token Limits">
    <div class="mb-4">
        <a href="{{ route('super-admin.dashboard') }}" class="text-xs text-accent">← Back to Dashboard</a>
    </div>
    @include('super-admin.partials._tab-token-limits', ['tokenLimitRows' => $rows, 'defaultLimit' => $defaultLimit])
</x-layouts.studyai>
