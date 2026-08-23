<x-guest-layout title="Welcome to StudyAI"
                description="A modern school management and study platform."
                pitch-title="Every school, its own workspace.">

    <div class="space-y-5">
        <p class="text-sm text-muted">
            Schools sign in at their own address, for example
            <code class="rounded bg-surface-sunk px-1.5 py-0.5 font-mono text-xs text-ink">yourschool.{{ config('tenancy.domain') ?? 'studyai.test' }}</code>.
        </p>

        <a href="{{ route('school.find') }}" class="btn btn-primary btn-block btn-lg">
            <x-icon name="search" /> Find your school
        </a>

        <div class="border-t pt-4">
            <p class="text-xs text-faint">
                Platform staff? <a href="{{ url('/super-admin/login') }}" class="font-medium text-accent hover:underline">Sign in to the console</a>.
            </p>
        </div>
    </div>
</x-guest-layout>
