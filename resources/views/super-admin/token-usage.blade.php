<x-layouts.studyai title="Token Usage">
    <div class="mb-4">
        <a href="{{ route('super-admin.dashboard') }}" class="text-xs text-accent">← Back to Dashboard</a>
    </div>
    @include('super-admin.partials._tab-token-usage', ['tokenSummary' => $summary, 'byOperation' => $byOperation, 'byDay' => $byDay, 'days' => $days])
</x-layouts.studyai>
