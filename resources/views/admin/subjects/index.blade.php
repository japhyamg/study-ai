<x-layouts.studyai title="Subjects" subtitle="What your school teaches, and at which levels">

    <div class="grid gap-5 lg:grid-cols-3">
        <div class="lg:col-span-2">
            <x-ui.card title="Subjects" :subtitle="$subjects->total().' subject'.($subjects->total() === 1 ? '' : 's')">
                @if ($subjects->isEmpty())
                    <x-ui.empty icon="book" title="No subjects yet"
                                message="Add your first subject, or create a full structure from the academic overview." />
                @else
                    <div class="table-wrap">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Subject</th><th>Code</th><th>Category</th>
                                    <th>Levels</th><th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($subjects as $subject)
                                    <tr x-data="{ editing: false }">
                                        <td>
                                            <span x-show="!editing" class="font-medium text-ink">{{ $subject->name }}</span>

                                            {{-- Inline editor --}}
                                            <form x-show="editing" x-cloak method="POST"
                                                  action="{{ route('admin.subjects.update', $subject) }}"
                                                  class="space-y-3 py-1">
                                                @csrf @method('put')

                                                <div class="grid gap-2 sm:grid-cols-3">
                                                    <input name="name" class="input" value="{{ $subject->name }}" required>
                                                    <input name="code" class="input" value="{{ $subject->code }}" placeholder="Code">
                                                    <select name="category" class="select">
                                                        @foreach (['core' => 'Core', 'elective' => 'Elective', 'vocational' => 'Vocational'] as $v => $l)
                                                            <option value="{{ $v }}" @selected($subject->category === $v)>{{ $l }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>

                                                @if ($levels->isNotEmpty())
                                                    <fieldset>
                                                        <legend class="mb-1.5 text-xs font-medium text-muted">
                                                            Taught at (none selected = all levels)
                                                        </legend>
                                                        <div class="flex flex-wrap gap-x-3 gap-y-1.5">
                                                            @foreach ($levels as $level)
                                                                <label class="inline-flex items-center gap-1.5 text-xs text-muted">
                                                                    <input type="checkbox" name="applies_to[]" value="{{ $level->code }}"
                                                                           class="checkbox"
                                                                           @checked(in_array($level->code, $subject->applies_to ?? [], true))>
                                                                    {{ $level->name }}
                                                                </label>
                                                            @endforeach
                                                        </div>
                                                    </fieldset>
                                                @endif

                                                <div class="flex flex-wrap items-center gap-2">
                                                    <label class="inline-flex items-center gap-1.5 text-xs text-muted">
                                                        <input type="checkbox" name="is_active" value="1" class="checkbox"
                                                               @checked($subject->is_active)> Active
                                                    </label>
                                                    <button class="btn btn-primary btn-sm">Save</button>
                                                    <button type="button" class="btn btn-ghost btn-sm" @click="editing = false">Cancel</button>
                                                </div>
                                            </form>
                                        </td>

                                        <td x-show="!editing">
                                            @if ($subject->code)<code class="text-xs text-muted">{{ $subject->code }}</code>@else — @endif
                                        </td>
                                        <td x-show="!editing">
                                            <span class="badge">{{ ucfirst($subject->category ?? 'core') }}</span>
                                        </td>
                                        <td x-show="!editing" class="text-xs text-muted">
                                            @if (empty($subject->applies_to))
                                                All levels
                                            @else
                                                {{ collect($subject->applies_to)
                                                    ->map(fn ($c) => $levels->firstWhere('code', $c)?->name ?? $c)
                                                    ->join(', ') }}
                                            @endif
                                        </td>
                                        <td x-show="!editing">
                                            <div class="flex items-center justify-end gap-1.5">
                                                @unless ($subject->is_active)
                                                    <span class="badge">Inactive</span>
                                                @endunless
                                                <button class="btn btn-ghost btn-sm" @click="editing = true"><x-icon name="pencil" /></button>
                                                <form method="POST" action="{{ route('admin.subjects.destroy', $subject) }}"
                                                      onsubmit="return confirm('Delete {{ $subject->name }}?')">
                                                    @csrf @method('delete')
                                                    <button class="btn btn-danger-quiet btn-sm"><x-icon name="trash" /></button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    @if ($subjects->hasPages())
                        <div class="mt-4">{{ $subjects->links() }}</div>
                    @endif
                @endif
            </x-ui.card>
        </div>

        <x-ui.card title="Add a subject">
            <form method="POST" action="{{ route('admin.subjects.store') }}" class="space-y-4">
                @csrf

                <x-ui.field label="Name" name="name" required>
                    <input name="name" class="input" placeholder="Mathematics" value="{{ old('name') }}" required>
                </x-ui.field>

                <x-ui.field label="Code" name="code">
                    <input name="code" class="input" placeholder="MTH" value="{{ old('code') }}">
                </x-ui.field>

                <x-ui.field label="Category" name="category">
                    <select name="category" class="select">
                        @foreach (['core' => 'Core', 'elective' => 'Elective', 'vocational' => 'Vocational'] as $v => $l)
                            <option value="{{ $v }}" @selected(old('category') === $v)>{{ $l }}</option>
                        @endforeach
                    </select>
                </x-ui.field>

                @if ($levels->isNotEmpty())
                    <x-ui.field label="Taught at" hint="Leave all unchecked to apply to every level.">
                        <div class="max-h-44 space-y-1.5 overflow-y-auto rounded-lg border p-2">
                            @foreach ($levels as $level)
                                <label class="flex items-center gap-2 text-sm text-muted">
                                    <input type="checkbox" name="applies_to[]" value="{{ $level->code }}" class="checkbox">
                                    {{ $level->name }}
                                </label>
                            @endforeach
                        </div>
                    </x-ui.field>
                @endif

                <button class="btn btn-primary btn-block">Add subject</button>
            </form>
        </x-ui.card>
    </div>
</x-layouts.studyai>
