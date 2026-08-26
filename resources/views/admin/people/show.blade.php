@php
    $roleLabels = [
        'admin' => 'Administrator',
        'teacher' => 'Teacher',
        'student' => 'Student',
    ];

    $backTo = match ($membership->role) {
        'teacher' => route('admin.teachers'),
        'student' => route('admin.students'),
        default => route('admin.administrators'),
    };

    $backLabel = match ($membership->role) {
        'teacher' => 'All teachers',
        'student' => 'All students',
        default => 'All administrators',
    };

    $profile = $user->profile();
@endphp

<x-layouts.studyai :title="$user->name" :subtitle="$roleLabels[$membership->role] ?? $membership->role"
                   :back-to="$backTo" :back-label="$backLabel">

    {{-- Shown once after a reset: there is no mail set up, so the admin hands
         the password over themselves. --}}
    @if (session('credentials'))
        @php $c = session('credentials'); @endphp
        <div class="alert-info mb-5">
            <p class="font-medium">New password for {{ $c['name'] }}.</p>
            <p class="mt-1 text-sm">
                Sign in with <span class="font-medium">{{ $c['login'] }}</span>
                and password <span class="font-mono font-medium">{{ $c['password'] }}</span>.
            </p>
            <p class="mt-1 text-xs">This is not shown again.</p>
        </div>
    @endif

    @if ($errors->any())
        <div class="alert-danger mb-5">
            <ul class="list-disc ps-4">
                @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        {{-- ── Editable details ── --}}
        <div class="lg:col-span-2">
            <div class="surface p-5">
                <p class="mb-4 text-sm font-medium text-ink">Details</p>

                <form method="POST" action="{{ route('admin.people.update', $user) }}" class="space-y-4">
                    @csrf @method('PUT')

                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-ui.field label="Name" name="name" required>
                            <input id="name" name="name" class="input" required
                                   value="{{ old('name', $user->name) }}">
                        </x-ui.field>

                        <x-ui.field label="Email" name="email" required>
                            <input id="email" name="email" type="email" class="input" required
                                   value="{{ old('email', $user->email) }}">
                        </x-ui.field>

                        <x-ui.field label="Phone" name="phone">
                            <input id="phone" name="phone" class="input"
                                   value="{{ old('phone', $user->phone) }}">
                        </x-ui.field>

                        <x-ui.field label="Role" name="role"
                                    hint="Decides what they can reach in the app.">
                            <select id="role" name="role" class="select">
                                @foreach ($roleLabels as $value => $label)
                                    <option value="{{ $value }}"
                                        @selected(old('role', $membership->role) === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </x-ui.field>
                    </div>

                    <label class="flex items-start gap-2.5 text-sm">
                        <input type="checkbox" name="is_active" value="1" class="mt-0.5 shrink-0"
                               @checked(old('is_active', $user->is_active))>
                        <span>
                            <span class="text-ink">Active</span>
                            <span class="block text-xs text-faint">
                                An inactive person keeps their record but cannot sign in.
                            </span>
                        </span>
                    </label>

                    <div class="flex justify-end border-t border-line pt-3">
                        <x-ui.button type="submit">Save changes</x-ui.button>
                    </div>
                </form>

            </div>

            {{-- ── Account actions ── --}}
            <div class="surface mt-6 p-5">
                <p class="mb-4 text-sm font-medium text-ink">Account</p>

                <div class="space-y-4">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-sm text-ink">Reset password</p>
                            <p class="text-xs text-faint">
                                Issues a new one and shows it once so you can pass it on.
                            </p>
                        </div>

                        <form method="POST" action="{{ route('admin.people.password', $user) }}"
                              onsubmit="return confirm('Issue a new password for {{ $user->name }}?')">
                            @csrf @method('PUT')
                            <x-ui.button type="submit" variant="ghost" size="sm">Reset</x-ui.button>
                        </form>
                    </div>

                    @if ($membership->role !== 'admin')
                        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-line pt-4">
                            <div class="min-w-0">
                                <p class="text-sm text-ink">Sign in as {{ $user->name }}</p>
                                <p class="text-xs text-faint">
                                    See exactly what they see. A banner stays up until you stop.
                                </p>
                            </div>

                            <form method="POST" action="{{ route('admin.people.impersonate', $user) }}"
                                  onsubmit="return confirm('Sign in as {{ $user->name }}?')">
                                @csrf
                                <x-ui.button type="submit" variant="ghost" size="sm">Impersonate</x-ui.button>
                            </form>
                        </div>
                    @endif

                    <div class="flex flex-wrap items-center justify-between gap-3 border-t border-line pt-4">
                        <div class="min-w-0">
                            <p class="text-sm text-ink">Remove from school</p>
                            <p class="text-xs text-faint">Keeps the account; drops their place here.</p>
                        </div>

                        <form method="POST" action="{{ route('admin.members.remove', $membership) }}"
                              onsubmit="return confirm('Remove {{ $user->name }} from the school?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-xs text-danger hover:underline">Remove</button>
                        </form>
                    </div>

                    <div class="flex flex-wrap items-center justify-between gap-3 border-t border-line pt-4">
                        <div class="min-w-0">
                            <p class="text-sm text-ink">Delete account</p>
                            <p class="text-xs text-faint">
                                Permanent. Their work goes with it.
                            </p>
                        </div>

                        <form method="POST" action="{{ route('admin.people.destroy', $user) }}"
                              onsubmit="return confirm('Delete {{ $user->name }} permanently? This cannot be undone.')">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-xs text-danger hover:underline">Delete</button>
                        </form>
                    </div>
                </div>
            </div>

            {{-- ── What they do here ── --}}
            @if ($membership->role === 'teacher')
                <div class="surface mt-6 p-5">
                    <p class="mb-3 text-sm font-medium text-ink">Teaching</p>

                    @if ($subjects->isEmpty() && $classes->isEmpty())
                        <p class="text-xs text-faint">
                            Not assigned to any subject or class yet. Assignments are made
                            from the class page under Academics.
                        </p>
                    @else
                        @if ($subjects->isNotEmpty())
                            <ul class="space-y-1.5">
                                @foreach ($subjects as $assignment)
                                    <li class="flex flex-wrap items-center gap-2 text-sm">
                                        <span class="text-ink">{{ $assignment->subject?->name ?? 'Subject' }}</span>
                                        <span class="text-faint">·</span>
                                        <span class="text-muted">{{ $assignment->classArm?->fullName() ?? 'Class' }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        @endif

                        @if ($classes->isNotEmpty())
                            <p class="mt-3 text-xs text-faint">
                                Form teacher for
                                {{ $classes->map(fn ($c) => $c->fullName())->join(', ') }}.
                            </p>
                        @endif
                    @endif
                </div>
            @endif

            @if ($membership->role === 'student')
                <div class="surface mt-6 p-5">
                    <p class="mb-3 text-sm font-medium text-ink">Classes</p>

                    @if ($classes->isEmpty())
                        <p class="text-xs text-faint">Not enrolled in a class yet.</p>
                    @else
                        <ul class="space-y-1.5">
                            @foreach ($classes as $class)
                                <li class="text-sm text-ink">{{ $class->fullName() }}</li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            @endif
        </div>

        {{-- ── Record ── --}}
        <div>
            <div class="surface p-5">
                <div class="flex items-center gap-3">
                    <span class="avatar avatar-lg shrink-0">{{ $user->initials() }}</span>
                    <div class="min-w-0">
                        <p class="truncate font-medium text-ink">{{ $user->name }}</p>
                        <p class="truncate text-xs text-faint">{{ $user->email }}</p>
                    </div>
                </div>

                <dl class="mt-4 space-y-3 border-t border-line pt-4 text-sm">
                    <div>
                        <dt class="text-xs text-faint">Role</dt>
                        <dd class="text-ink">{{ $roleLabels[$membership->role] ?? $membership->role }}</dd>
                    </div>

                    <div>
                        <dt class="text-xs text-faint">Status</dt>
                        <dd>
                            @if ($user->is_active)
                                <x-ui.badge tone="success">Active</x-ui.badge>
                            @else
                                <x-ui.badge>Inactive</x-ui.badge>
                            @endif
                        </dd>
                    </div>

                    <div>
                        <dt class="text-xs text-faint">Joined</dt>
                        <dd class="text-ink">{{ $membership->created_at?->format('j M Y') ?? '—' }}</dd>
                    </div>

                    @if ($profile?->staff_number ?? null)
                        <div>
                            <dt class="text-xs text-faint">Staff number</dt>
                            <dd class="text-ink">{{ $profile->staff_number }}</dd>
                        </div>
                    @endif

                    @if ($profile?->admission_number ?? null)
                        <div>
                            <dt class="text-xs text-faint">Admission number</dt>
                            <dd class="text-ink">{{ $profile->admission_number }}</dd>
                        </div>
                    @endif
                </dl>
            </div>
        </div>
    </div>
</x-layouts.studyai>
