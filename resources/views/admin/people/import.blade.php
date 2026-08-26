@php
    $isTeacher = $role === 'teacher';

    [$backTo, $backLabel] = $isTeacher
        ? [route('admin.teachers'), 'All teachers']
        : [route('admin.students'), 'All students'];
@endphp

<x-layouts.studyai :title="$heading"
                   subtitle="Add many people at once from a spreadsheet."
                   :back-to="$backTo" :back-label="$backLabel">

    @if (session('import_errors'))
        <div class="alert-danger mb-5">
            <p class="font-medium">Nothing was imported.</p>
            <p class="mt-1 text-sm">
                Fix these rows and upload the file again. The whole file is checked
                first, so no one is created until it is clean.
            </p>
            <ul class="mt-2 list-disc space-y-0.5 ps-4 text-sm">
                @foreach (session('import_errors') as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2 space-y-6">
            {{-- Step one --}}
            <div class="surface p-5">
                <p class="text-sm font-medium text-ink">1. Download the template</p>
                <p class="field-hint mb-3">
                    It comes with two example rows showing the expected format. Delete
                    them and put your own people in their place.
                </p>

                <x-ui.button :href="route('admin.people.import.template', $role)" variant="ghost" icon="download">
                    {{ $isTeacher ? 'teacher' : 'student' }}-import-template.csv
                </x-ui.button>
            </div>

            {{-- Step two --}}
            <div class="surface p-5">
                <p class="text-sm font-medium text-ink">2. Upload it back</p>
                <p class="field-hint mb-3">
                    Save as CSV from Excel or Google Sheets. Up to 2&nbsp;MB.
                </p>

                <form method="POST" action="{{ route('admin.people.import.store') }}"
                      enctype="multipart/form-data" class="space-y-4">
                    @csrf
                    <input type="hidden" name="role" value="{{ $role }}">

                    <x-ui.field label="File" name="file" required>
                        <input id="file" name="file" type="file" accept=".csv,text/csv" class="input" required>
                    </x-ui.field>

                    <div class="flex justify-end gap-2 border-t border-line pt-3">
                        <x-ui.button :href="$backTo" variant="ghost">Cancel</x-ui.button>
                        <x-ui.button type="submit">Import</x-ui.button>
                    </div>
                </form>
            </div>
        </div>

        {{-- What the columns mean --}}
        <div>
            <div class="surface p-5">
                <p class="mb-3 text-sm font-medium text-ink">Columns</p>

                <dl class="space-y-2.5 text-sm">
                    @foreach ($columns as $column)
                        <div>
                            <dt class="font-mono text-xs text-ink">{{ $column }}</dt>
                            <dd class="text-xs text-muted">
                                @switch($column)
                                    @case('name') Required. @break
                                    @case('email')
                                        {{ $isTeacher ? 'Required — used to sign in.' : 'Optional for students.' }}
                                        @break
                                    @case('admission_number') Required. The student signs in with this. @break
                                    @case('class') Must match a class name exactly, e.g. JSS 1 A. @break
                                    @case('date_of_birth') Any readable date, e.g. 2013-04-12. @break
                                    @case('password') Leave blank to generate one. @break
                                    @default Optional.
                                @endswitch
                            </dd>
                        </div>
                    @endforeach
                </dl>

                <p class="mt-4 border-t border-line pt-3 text-xs text-faint">
                    Column order does not matter — the headings are read by name.
                </p>
            </div>
        </div>
    </div>
</x-layouts.studyai>
