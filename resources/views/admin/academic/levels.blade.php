<x-layouts.studyai title="Class levels" subtitle="Curriculum bands students progress through">

    <div class="grid gap-5 lg:grid-cols-3">
        <div class="lg:col-span-2">
            <x-ui.card title="Levels" :subtitle="$levels->count().' level'.($levels->count() === 1 ? '' : 's').', ordered by progression'">
                @if ($levels->isEmpty())
                    <x-ui.empty icon="layers" title="No class levels yet"
                                message="Add your first level, or create a full structure from the academic overview." />
                @else
                    <div class="table-wrap">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>#</th><th>Level</th><th>Code</th><th>Stage</th>
                                    <th class="num">Classes</th><th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($levels as $level)
                                    <tr x-data="{ editing: false }">
                                        <td class="tnum text-faint">{{ $level->position }}</td>
                                        <td>
                                            <span x-show="!editing" class="font-medium text-ink">{{ $level->name }}</span>
                                            <form x-show="editing" x-cloak method="POST"
                                                  action="{{ route('admin.levels.update', $level) }}"
                                                  class="flex flex-wrap items-end gap-2">
                                                @csrf @method('put')
                                                <input name="name" class="input w-32" value="{{ $level->name }}" required>
                                                <input name="code" class="input w-24" value="{{ $level->code }}" required>
                                                <input name="stage" class="input w-32" value="{{ $level->stage }}" placeholder="Stage">
                                                <input name="position" type="number" class="input w-20" value="{{ $level->position }}">
                                                <button class="btn btn-primary btn-sm">Save</button>
                                                <button type="button" class="btn btn-ghost btn-sm" @click="editing = false">Cancel</button>
                                            </form>
                                        </td>
                                        <td x-show="!editing"><code class="text-xs text-muted">{{ $level->code }}</code></td>
                                        <td x-show="!editing" class="text-muted">{{ $level->stage ? Str::headline($level->stage) : '—' }}</td>
                                        <td x-show="!editing" class="num tnum">{{ $level->arms_count }}</td>
                                        <td x-show="!editing">
                                            <div class="flex items-center justify-end gap-1.5">
                                                <button class="btn btn-ghost btn-sm" @click="editing = true"><x-icon name="pencil" /></button>
                                                <form method="POST" action="{{ route('admin.levels.destroy', $level) }}"
                                                      onsubmit="return confirm('Delete {{ $level->name }}?')">
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
                @endif
            </x-ui.card>
        </div>

        <x-ui.card title="Add a level">
            <form method="POST" action="{{ route('admin.levels.store') }}" class="space-y-4">
                @csrf
                <x-ui.field label="Name" name="name" required>
                    <input name="name" class="input" placeholder="JSS 1" value="{{ old('name') }}" required>
                </x-ui.field>
                <x-ui.field label="Code" name="code" required hint="Short identifier, e.g. jss1">
                    <input name="code" class="input" placeholder="jss1" value="{{ old('code') }}" required>
                </x-ui.field>
                <x-ui.field label="Stage" name="stage" hint="Optional grouping, e.g. junior_secondary">
                    <input name="stage" class="input" value="{{ old('stage') }}">
                </x-ui.field>
                <x-ui.field label="Position" name="position" hint="Order students progress through.">
                    <input name="position" type="number" min="0" class="input" value="{{ old('position') }}">
                </x-ui.field>
                <button class="btn btn-primary btn-block">Add level</button>
            </form>
        </x-ui.card>
    </div>
</x-layouts.studyai>
