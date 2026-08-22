<x-layouts.studyai title="Usage & Teachers">
    <div class="mb-4">
        <a href="{{ route('super-admin.dashboard') }}" class="text-xs text-accent">← Back to Dashboard</a>
    </div>
    @include('super-admin.partials._tab-usage-teachers', ['usageTeachersSummary' => $summary, 'schoolsData' => $schoolsData, 'days' => $days])
</x-layouts.studyai>
