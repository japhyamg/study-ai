@php
    $isStudent = $role === 'student';
    $isTeacher = $role === 'teacher';
    $isAdmin = $role === 'admin';

    [$backTo, $backLabel] = match ($role) {
        'teacher' => [route('admin.teachers'), 'All teachers'],
        'student' => [route('admin.students'), 'All students'],
        default => [route('admin.administrators'), 'All administrators'],
    };
@endphp

<x-layouts.studyai :title="$heading" :back-to="$backTo" :back-label="$backLabel">
    <div class="surface max-w-2xl">
        <form method="POST" action="{{ route('admin.people.store') }}" class="space-y-5 px-6 py-5">
            @csrf
            <input type="hidden" name="role" value="{{ $role }}">

            <x-ui.field label="Full name" name="name" required>
                <input id="name" name="name" class="input" required value="{{ old('name') }}">
            </x-ui.field>

            @if ($isStudent)
                {{-- Students sign in with this, not an email address. --}}
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.field label="Admission number" name="admission_number" required
                                hint="This is what the student signs in with.">
                        <input id="admission_number" name="admission_number" class="input" required
                               value="{{ old('admission_number') }}" placeholder="e.g. STU/2026/014">
                    </x-ui.field>

                    <x-ui.field label="Class" name="class_arm_id" hint="Can be set later.">
                        <select id="class_arm_id" name="class_arm_id" class="select">
                            <option value="">Not assigned</option>
                            @foreach ($classes as $class)
                                <option value="{{ $class->id }}" @selected(old('class_arm_id') == $class->id)>
                                    {{ $class->fullName() }}
                                </option>
                            @endforeach
                        </select>
                    </x-ui.field>
                </div>

                <x-ui.field label="Email address" name="email"
                            hint="Optional. Students sign in with their admission number.">
                    <input id="email" name="email" type="email" class="input" value="{{ old('email') }}">
                </x-ui.field>

                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.field label="Date of birth" name="date_of_birth">
                        <input id="date_of_birth" name="date_of_birth" type="date" class="input"
                               value="{{ old('date_of_birth') }}">
                    </x-ui.field>

                    <x-ui.field label="Gender" name="gender">
                        <select id="gender" name="gender" class="select">
                            <option value="">Not stated</option>
                            <option value="female" @selected(old('gender') === 'female')>Female</option>
                            <option value="male" @selected(old('gender') === 'male')>Male</option>
                        </select>
                    </x-ui.field>
                </div>

                <div class="border-t border-line pt-4">
                    <p class="text-sm font-medium text-ink">Guardian</p>
                    <p class="field-hint mb-3">Who the school contacts about this student.</p>

                    <div class="space-y-4">
                        <x-ui.field label="Name" name="guardian_name">
                            <input id="guardian_name" name="guardian_name" class="input"
                                   value="{{ old('guardian_name') }}">
                        </x-ui.field>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <x-ui.field label="Phone" name="guardian_phone">
                                <input id="guardian_phone" name="guardian_phone" class="input"
                                       value="{{ old('guardian_phone') }}">
                            </x-ui.field>

                            <x-ui.field label="Email" name="guardian_email">
                                <input id="guardian_email" name="guardian_email" type="email" class="input"
                                       value="{{ old('guardian_email') }}">
                            </x-ui.field>
                        </div>
                    </div>
                </div>
            @else
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.field label="Email address" name="email" required
                                hint="Used to sign in.">
                        <input id="email" name="email" type="email" class="input" required
                               value="{{ old('email') }}">
                    </x-ui.field>

                    <x-ui.field label="Phone" name="phone">
                        <input id="phone" name="phone" class="input" value="{{ old('phone') }}">
                    </x-ui.field>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.field label="Staff number" name="staff_number">
                        <input id="staff_number" name="staff_number" class="input"
                               value="{{ old('staff_number') }}">
                    </x-ui.field>

                    @if ($isTeacher)
                        <x-ui.field label="Department" name="department">
                            <input id="department" name="department" class="input"
                                   value="{{ old('department') }}" placeholder="e.g. Science">
                        </x-ui.field>
                    @else
                        <x-ui.field label="Job title" name="job_title">
                            <input id="job_title" name="job_title" class="input"
                                   value="{{ old('job_title') }}" placeholder="e.g. Vice Principal">
                        </x-ui.field>
                    @endif
                </div>

                @if ($isTeacher)
                    <x-ui.field label="Qualification" name="qualification">
                        <input id="qualification" name="qualification" class="input"
                               value="{{ old('qualification') }}" placeholder="e.g. B.Ed Mathematics">
                    </x-ui.field>
                @endif
            @endif

            <div class="border-t border-line pt-4">
                <x-ui.field label="Password" name="password"
                            hint="Leave blank to generate one. It is shown once after saving.">
                    <input id="password" name="password" type="text" class="input"
                           autocomplete="new-password">
                </x-ui.field>
            </div>

            <div class="flex justify-end gap-2 border-t border-line pt-4">
                <x-ui.button :href="$backTo" variant="ghost">Cancel</x-ui.button>
                <x-ui.button type="submit">
                    Add {{ $isStudent ? 'student' : ($isTeacher ? 'teacher' : 'administrator') }}
                </x-ui.button>
            </div>
        </form>
    </div>
</x-layouts.studyai>
