@php use App\Models\SchoolMember; @endphp

<x-ui.card>
    <x-slot:title>{{ $user->roleLabel() }} details</x-slot:title>
    <x-slot:subtitle>
        @if ($role === SchoolMember::ROLE_STUDENT)
            Academic records are maintained by your school. You can keep your contact details current.
        @else
            Information shown to colleagues and students across the school.
        @endif
    </x-slot:subtitle>

    <form method="POST" action="{{ route('profile.details') }}" class="space-y-4">
        @csrf
        @method('put')

        {{-- ───────── Administrator ───────── --}}
        @if ($role === SchoolMember::ROLE_ADMIN)
            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.field label="Staff number" name="staff_number">
                    <input name="staff_number" class="input" value="{{ old('staff_number', $profile?->staff_number) }}">
                </x-ui.field>
                <x-ui.field label="Job title" name="job_title">
                    <input name="job_title" class="input" value="{{ old('job_title', $profile?->job_title) }}"
                           placeholder="e.g. Head of School">
                </x-ui.field>
                <x-ui.field label="Department" name="department">
                    <input name="department" class="input" value="{{ old('department', $profile?->department) }}">
                </x-ui.field>
                <x-ui.field label="Office phone" name="office_phone">
                    <input name="office_phone" type="tel" class="input" value="{{ old('office_phone', $profile?->office_phone) }}">
                </x-ui.field>
            </div>

            @if ($profile?->is_primary)
                <div class="alert alert-info">
                    <x-icon name="info" class="mt-px flex-none" />
                    <span>You are the primary administrator for this school.</span>
                </div>
            @endif
        @endif

        {{-- ───────── Teacher ───────── --}}
        @if ($role === SchoolMember::ROLE_TEACHER)
            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.field label="Title" name="title">
                    <select name="title" class="select">
                        <option value="">—</option>
                        @foreach (['Mr', 'Mrs', 'Ms', 'Miss', 'Dr', 'Prof'] as $t)
                            <option value="{{ $t }}" @selected(old('title', $profile?->title) === $t)>{{ $t }}</option>
                        @endforeach
                    </select>
                </x-ui.field>
                <x-ui.field label="Staff number" name="staff_number">
                    <input name="staff_number" class="input" value="{{ old('staff_number', $profile?->staff_number) }}">
                </x-ui.field>
                <x-ui.field label="Department" name="department">
                    <input name="department" class="input" value="{{ old('department', $profile?->department) }}">
                </x-ui.field>
                <x-ui.field label="Qualification" name="qualification">
                    <input name="qualification" class="input" value="{{ old('qualification', $profile?->qualification) }}"
                           placeholder="e.g. B.Ed, M.Sc">
                </x-ui.field>
                <x-ui.field label="Subjects taught" name="specialisations" hint="Comma separated.">
                    <input name="specialisations" class="input"
                           value="{{ old('specialisations', implode(', ', $profile?->specialisations ?? [])) }}">
                </x-ui.field>
                <x-ui.field label="Office hours" name="office_hours">
                    <input name="office_hours" class="input" value="{{ old('office_hours', $profile?->office_hours) }}"
                           placeholder="e.g. Tue & Thu, 2–4pm">
                </x-ui.field>
            </div>

            <x-ui.field label="Short bio" name="bio" hint="Shown on your classes.">
                <textarea name="bio" rows="3" class="textarea">{{ old('bio', $profile?->bio) }}</textarea>
            </x-ui.field>
        @endif

        {{-- ───────── Student ───────── --}}
        @if ($role === SchoolMember::ROLE_STUDENT)
            <div class="surface-sunk grid gap-x-4 gap-y-2 p-3 sm:grid-cols-3">
                <div>
                    <p class="text-xs text-faint">Admission number</p>
                    <p class="text-sm font-medium text-ink">{{ $profile?->admission_number ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs text-faint">Class</p>
                    <p class="text-sm font-medium text-ink">{{ $profile?->classLabel() ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs text-faint">Status</p>
                    <p class="text-sm font-medium text-ink capitalize">{{ $profile?->status ?? '—' }}</p>
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.field label="Date of birth" name="date_of_birth">
                    <input name="date_of_birth" type="date" class="input"
                           value="{{ old('date_of_birth', $profile?->date_of_birth?->format('Y-m-d')) }}">
                </x-ui.field>
                <x-ui.field label="Gender" name="gender">
                    <select name="gender" class="select">
                        <option value="">Prefer not to say</option>
                        @foreach (['female' => 'Female', 'male' => 'Male', 'other' => 'Other'] as $v => $l)
                            <option value="{{ $v }}" @selected(old('gender', $profile?->gender) === $v)>{{ $l }}</option>
                        @endforeach
                    </select>
                </x-ui.field>
                <x-ui.field label="Guardian name" name="guardian_name">
                    <input name="guardian_name" class="input" value="{{ old('guardian_name', $profile?->guardian_name) }}">
                </x-ui.field>
                <x-ui.field label="Guardian phone" name="guardian_phone">
                    <input name="guardian_phone" type="tel" class="input" value="{{ old('guardian_phone', $profile?->guardian_phone) }}">
                </x-ui.field>
                <x-ui.field label="Guardian email" name="guardian_email">
                    <input name="guardian_email" type="email" class="input" value="{{ old('guardian_email', $profile?->guardian_email) }}">
                </x-ui.field>
            </div>

            <x-ui.field label="Home address" name="address">
                <textarea name="address" rows="2" class="textarea">{{ old('address', $profile?->address) }}</textarea>
            </x-ui.field>
        @endif

        <div class="flex justify-end">
            <button type="submit" class="btn btn-primary">Save details</button>
        </div>
    </form>
</x-ui.card>
