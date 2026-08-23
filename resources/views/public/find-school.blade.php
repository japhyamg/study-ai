<x-guest-layout title="Find your school"
                description="Enter the short name your school uses.">

    <form method="POST" action="{{ route('school.lookup') }}" class="space-y-4">
        @csrf

        <x-ui.field label="School address" name="subdomain" required
                    :hint="'For example: lincoln — we\'ll take you to lincoln.'.(config('tenancy.domain') ?? 'studyai.test')">
            <input id="subdomain" name="subdomain" class="input" value="{{ old('subdomain') }}"
                   required autofocus placeholder="yourschool">
        </x-ui.field>

        <button type="submit" class="btn btn-primary btn-block btn-lg">Continue</button>
    </form>
</x-guest-layout>
