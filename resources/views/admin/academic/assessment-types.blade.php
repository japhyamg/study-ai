<x-layouts.studyai title="Assessment components"
                   subtitle="How a term score is made up — each component carries a weight">

    @unless ($balanced)
        <div class="alert alert-warn mb-5">
            <x-icon name="alert-circle" class="mt-px flex-none" />
            <span>
                Your weights total <strong>{{ rtrim(rtrim(number_format($totalWeight, 2), '0'), '.') }}%</strong>.
                They should add up to 100% for term scores to be calculated correctly.
            </span>
        </div>
    @endunless

    <div class="grid gap-5 lg:grid-cols-3">
        <div class="lg:col-span-2">
            <x-ui.card title="Components">
                <x-slot:actions>
                    <span class="badge {{ $balanced ? 'badge-ok' : 'badge-warn' }}">
                        {{ rtrim(rtrim(number_format($totalWeight, 2), '0'), '.') }}% of 100%
                    </span>
                </x-slot:actions>

                @if ($types->isEmpty())
                    <x-ui.empty icon="clipboard" title="No components yet"
                                message="Add components such as CA1, CA2 and Exam." />
                @else
                    <div class="table-wrap">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Component</th><th>Code</th>
                                    <th class="num">Max score</th><th class="num">Weight</th>
                                    <th>Status</th><th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($types as $type)
                                    <tr x-data="{ editing: false }">
                                        <td>
                                            <span x-show="!editing" class="font-medium text-ink">{{ $type->name }}</span>
                                            <form x-show="editing" x-cloak method="POST"
                                                  action="{{ route('admin.assessment-types.update', $type) }}"
                                                  class="flex flex-wrap items-end gap-2">
                                                @csrf @method('put')
                                                <input name="name" class="input w-40" value="{{ $type->name }}" required>
                                                <input name="code" class="input w-20" value="{{ $type->code }}" required>
                                                <input name="max_score" type="number" class="input w-20" value="{{ $type->max_score }}" required>
                                                <input name="weight_percent" type="number" step="0.01" class="input w-20" value="{{ $type->weight_percent }}" required>
                                                <label class="mb-2 inline-flex items-center gap-1.5 text-xs text-muted">
                                                    <input type="checkbox" name="is_active" value="1" class="checkbox" @checked($type->is_active)> Active
                                                </label>
                                                <button class="btn btn-primary btn-sm">Save</button>
                                                <button type="button" class="btn btn-ghost btn-sm" @click="editing = false">Cancel</button>
                                            </form>
                                        </td>
                                        <td x-show="!editing"><code class="text-xs text-muted">{{ $type->code }}</code></td>
                                        <td x-show="!editing" class="num tnum">{{ $type->max_score }}</td>
                                        <td x-show="!editing" class="num tnum font-medium">{{ rtrim(rtrim(number_format($type->weight_percent, 2), '0'), '.') }}%</td>
                                        <td x-show="!editing">
                                            <span class="badge {{ $type->is_active ? 'badge-ok' : '' }}">
                                                {{ $type->is_active ? 'Active' : 'Inactive' }}
                                            </span>
                                        </td>
                                        <td x-show="!editing">
                                            <div class="flex items-center justify-end gap-1.5">
                                                <button class="btn btn-ghost btn-sm" @click="editing = true"><x-icon name="pencil" /></button>
                                                <form method="POST" action="{{ route('admin.assessment-types.destroy', $type) }}"
                                                      onsubmit="return confirm('Delete {{ $type->name }}?')">
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

        <x-ui.card title="Add a component">
            <form method="POST" action="{{ route('admin.assessment-types.store') }}" class="space-y-4">
                @csrf
                <x-ui.field label="Name" name="name" required>
                    <input name="name" class="input" placeholder="Continuous Assessment 1" value="{{ old('name') }}" required>
                </x-ui.field>
                <x-ui.field label="Code" name="code" required>
                    <input name="code" class="input" placeholder="ca1" value="{{ old('code') }}" required>
                </x-ui.field>
                <div class="grid grid-cols-2 gap-3">
                    <x-ui.field label="Max score" name="max_score" required>
                        <input name="max_score" type="number" min="1" class="input" value="{{ old('max_score', 20) }}" required>
                    </x-ui.field>
                    <x-ui.field label="Weight %" name="weight_percent" required>
                        <input name="weight_percent" type="number" step="0.01" min="0" max="100"
                               class="input" value="{{ old('weight_percent', 20) }}" required>
                    </x-ui.field>
                </div>
                <button class="btn btn-primary btn-block">Add component</button>
            </form>
        </x-ui.card>
    </div>
</x-layouts.studyai>
