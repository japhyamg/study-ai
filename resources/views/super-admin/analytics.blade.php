<x-layouts.studyai title="Analytics">
    <div class="mb-4">
        <a href="{{ route('super-admin.dashboard') }}" class="text-xs text-accent">← Back to Dashboard</a>
    </div>
    @include('super-admin.partials._tab-analytics', ['analyticsStats' => $stats, 'signupsTrend' => $signupsTrend, 'topSchools' => $topSchools])
</x-layouts.studyai>
