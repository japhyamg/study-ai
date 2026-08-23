<x-layouts.studyai :title="$arm->fullName()" :subtitle="$arm->classLevel?->name.($arm->stream ? ' · '.$arm->stream : '')">
    <x-slot:actions>
        <a href="{{ route('admin.classes.invite-codes', $arm) }}" class="btn btn-outline btn-sm">Invite codes</a>
        <a href="{{ route('admin.classes.edit', $arm) }}" class="btn btn-outline btn-sm"><x-icon name="pencil" /> Edit</a>
    </x-slot:actions>

    {{-- Summary --}}
    <div class="mb-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-ui.stat label="Students" :value="$arm->enrollments->count()"
                   :meta="$arm->availableSeats().' seat'.($arm->availableSeats() === 1 ? '' : 's').' left'"
                   icon="users" :tone="$arm->isFull() ? 'danger' : null" />
        <x-ui.stat label="Subjects taught" :value="$matrix->whereNotNull('assignment')->count()"
                   :meta="'of '.$matrix->count().' applicable'" icon="book" />
        <x-ui.stat label="Form teacher" :value="$arm->formTeacher?->name ?? '—'" icon="presentation" />
        <x-ui.stat label="Invite code" :value="$arm->invite_code" icon="key" />
    </div>

    <div class="grid gap-5 lg:grid-cols-5">

        {{-- ── Subjects & teachers ── --}}
        <div class="lg:col-span-3">
            <x-ui.card title="Subjects & teachers"
                       subtitle="One teacher owns each subject in this class.">
                @if ($matrix->isEmpty())
                    <x-ui.empty icon="book" title="No subjects apply to this level"
                                message="Add subjects, or widen which levels they apply to." />
                @else
                    <div class="table-wrap">
                        <table class="table">
                            <thead>
                                <tr><th>Subject</th><th>Teacher</th><th class="text-end">Action</th></tr>
                            </thead>
                            <tbody>
                                @foreach ($matrix as $row)
                                    @php $subject = $row['subject']; $assignment = $row['assignment']; @endphp
                                    <tr>
                                        <td>
                                            <span class="font-medium text-ink">{{ $subject->name }}</span>
                                            @if ($subject->code)
                                                <code class="ms-1 text-xs text-faint">{{ $subject->code }}</code>
                                            @endif
                                        </td>
                                        <td>
                                            <form method="POST" action="{{ route('admin.classes.subjects.assign', $arm) }}"
                                                  class="flex items-center gap-2">
                                                @csrf
                                                <input type="hidden" name="subject_id" value="{{ $subject->id }}">
                                                <select name="teacher_id" class="select w-auto min-w-[11rem]"
                                                        onchange="this.form.requestSubmit()">
                                                    <option value="">Unassigned</option>
                                                    @foreach ($teachers as $teacher)
                                                        <option value="{{ $teacher->id }}"
                                                            @selected($assignment?->teacher_id === $teacher->id)>
                                                            {{ $teacher->name }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                                <noscript><button class="btn btn-outline btn-sm">Save</button></noscript>
                                            </form>
                                        </td>
                                        <td>
                                            <div class="flex justify-end">
                                                @if ($assignment)
                                                    <form method="POST" action="{{ route('admin.classes.subjects.unassign', $arm) }}">
                                                        @csrf @method('delete')
                                                        <input type="hidden" name="subject_id" value="{{ $subject->id }}">
                                                        <button class="btn btn-ghost btn-sm" title="Unassign">
                                                            <x-icon name="x" />
                                                        </button>
                                                    </form>
                                                @else
                                                    <span class="badge badge-warn">Unassigned</span>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-ui.card>
        </div>

        {{-- ── Students ── --}}
        <div class="space-y-5 lg:col-span-2">
            <x-ui.card title="Students" :subtitle="$arm->enrollments->count().' enrolled'">
                <x-slot:actions>
                    @unless ($arm->isFull())
                        <button class="btn btn-outline btn-sm"
                                onclick="document.getElementById('enroll-form').classList.toggle('hidden')">
                            <x-icon name="plus" /> Add
                        </button>
                    @endunless
                </x-slot:actions>

                @if ($arm->isFull())
                    <div class="alert alert-warn mb-3">
                        <x-icon name="alert-circle" class="mt-px flex-none" />
                        <span>This class is at capacity ({{ $arm->capacity }}).</span>
                    </div>
                @endif

                <form id="enroll-form" method="POST" action="{{ route('admin.classes.enroll', $arm) }}"
                      class="hidden mb-3 flex items-end gap-2">
                    @csrf
                    <x-ui.field label="Student" name="user_id" class="flex-1">
                        <select name="user_id" class="select" required>
                            <option value="">Choose…</option>
                            @foreach ($students as $student)
                                <option value="{{ $student->id }}">{{ $student->name }}</option>
                            @endforeach
                        </select>
                    </x-ui.field>
                    <button class="btn btn-primary btn-sm mb-0.5">Enroll</button>
                </form>

                @if ($arm->enrollments->isEmpty())
                    <x-ui.empty message="No students in this class yet." />
                @else
                    <ul class="divide-y">
                        @foreach ($arm->enrollments->sortBy(fn ($e) => $e->user?->name) as $enrollment)
                            <li class="flex items-center gap-2.5 py-2">
                                <span class="avatar avatar-sm">{{ $enrollment->user?->initials() }}</span>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm text-ink">{{ $enrollment->user?->name }}</p>
                                    <p class="truncate text-xs text-faint">{{ $enrollment->user?->email }}</p>
                                </div>
                                <form method="POST" action="{{ route('admin.classes.unenroll', [$arm, $enrollment->user_id]) }}"
                                      onsubmit="return confirm('Remove {{ $enrollment->user?->name }} from this class?')">
                                    @csrf @method('delete')
                                    <button class="btn btn-ghost btn-sm" title="Remove"><x-icon name="x" /></button>
                                </form>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-ui.card>

            {{-- ── Promotion ── --}}
            @if ($promotionTarget)
                <x-ui.card title="End of session">
                    <form method="POST" action="{{ route('admin.classes.promote', $arm) }}" class="space-y-3"
                          onsubmit="return confirm('Promote all students in {{ $arm->fullName() }}?')">
                        @csrf
                        <p class="text-sm text-muted">
                            Move every student in this class up to the next level.
                        </p>
                        <x-ui.field label="Promote into" name="target_arm_id">
                            <select name="target_arm_id" class="select" required>
                                @foreach ($promotionTarget->classLevel->arms as $candidate)
                                    <option value="{{ $candidate->id }}" @selected($candidate->id === $promotionTarget->id)>
                                        {{ $candidate->fullName() }}
                                        ({{ $candidate->availableSeats() }} seat{{ $candidate->availableSeats() === 1 ? '' : 's' }} free)
                                    </option>
                                @endforeach
                            </select>
                        </x-ui.field>
                        <button class="btn btn-outline btn-block btn-sm">Promote students</button>
                    </form>
                </x-ui.card>
            @endif
        </div>
    </div>
</x-layouts.studyai>
