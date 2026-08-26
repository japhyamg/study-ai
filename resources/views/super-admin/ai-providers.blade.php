@php
    /**
     * AI providers.
     *
     * Exactly one provider is active at a time — AiService resolves it with
     * `where('is_active', true)->first()` — so the table leads with that rather
     * than making the reader work it out from a column of badges.
     *
     * The API key is never sent to the browser: it is in the model's $hidden,
     * and the edit form treats an empty key field as "leave it alone".
     */
    $active = $providers->firstWhere('is_active', true);
@endphp

<x-layouts.studyai title="AI providers"
                   subtitle="The active provider serves every generation request.">

    <x-slot:actions>
        <button type="button" class="btn btn-primary btn-sm" @click="$dispatch('provider-create')">
            <x-icon name="plus" /> New provider
        </button>
    </x-slot:actions>

    <div x-data="{ open: false, mode: 'create', form: {} }"
         @provider-create.window="mode = 'create'; form = { id: null, name: '', model: '', base_url: '', is_active: {{ $providers->isEmpty() ? 'true' : 'false' }} }; open = true"
         @provider-edit.window="mode = 'edit'; form = { ...$event.detail }; open = true">

        {{-- ── Which provider is in use ── --}}
        @if ($active)
            <div class="surface mb-4 border-s-2 border-s-success p-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-xs text-faint">Currently serving requests</p>
                        <p class="mt-0.5 font-medium text-ink">{{ $active->name }}</p>
                        <p class="text-xs text-muted">{{ $active->model }}</p>
                    </div>
                    <span class="badge badge-ok">Active</span>
                </div>
            </div>
        @else
            <div class="alert alert-warn mb-4" role="alert">
                <x-icon name="alert-circle" class="mt-px flex-none" />
                <span>
                    No provider is active, so every generation will fail. Mark one as active below.
                </span>
            </div>
        @endif

        {{-- ── The list ── --}}
        <div class="surface table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Model</th>
                        <th class="hidden md:table-cell">Base URL</th>
                        <th>Status</th>
                        <th><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($providers as $provider)
                        <tr>
                            <td class="font-medium text-ink">{{ $provider->name }}</td>
                            <td class="text-muted">{{ $provider->model }}</td>
                            <td class="hidden max-w-xs truncate text-xs text-faint md:table-cell">
                                {{ $provider->base_url }}
                            </td>
                            <td>
                                @if ($provider->is_active)
                                    <span class="badge badge-ok">Active</span>
                                @else
                                    <span class="badge">Inactive</span>
                                @endif
                            </td>
                            <td>
                                <div class="flex items-center justify-end gap-1">
                                    @unless ($provider->is_active)
                                        {{-- One click to switch, rather than
                                             opening the editor to tick a box. --}}
                                        <form method="POST"
                                              action="{{ route('super-admin.ai-providers.update', $provider) }}">
                                            @csrf
                                            @method('PUT')
                                            <input type="hidden" name="name" value="{{ $provider->name }}">
                                            <input type="hidden" name="model" value="{{ $provider->model }}">
                                            <input type="hidden" name="base_url" value="{{ $provider->base_url }}">
                                            <input type="hidden" name="is_active" value="1">
                                            <button type="submit" class="text-xs text-accent hover:underline">
                                                Make active
                                            </button>
                                        </form>
                                    @endunless

                                    <button type="button" class="btn-icon" title="Edit provider"
                                            @click="$dispatch('provider-edit', {
                                                id: @js($provider->id),
                                                name: @js($provider->name),
                                                model: @js($provider->model),
                                                base_url: @js($provider->base_url),
                                                is_active: @js((bool) $provider->is_active),
                                            })">
                                        <x-icon name="pencil" />
                                    </button>

                                    <form method="POST"
                                          action="{{ route('super-admin.ai-providers.destroy', $provider) }}"
                                          x-data
                                          @submit.prevent="confirm(
                                              @js($provider->is_active
                                                  ? 'Delete “'.$provider->name.'”? It is the active provider, so generation will stop working until another is made active.'
                                                  : 'Delete “'.$provider->name.'”?')
                                          ) && $el.submit()">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn-icon text-danger" title="Delete provider">
                                            <x-icon name="trash" />
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-8 text-center text-faint">
                                No providers configured yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- ── Create / edit modal ── --}}
        <div x-show="open" x-cloak class="fixed inset-0 z-40 bg-black/40" @click="open = false"></div>

        <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto p-4 sm:items-center">
            <div class="surface w-full max-w-lg p-5" @click.outside="open = false">
                <h3 class="font-semibold text-ink" x-text="mode === 'edit' ? 'Edit provider' : 'New provider'"></h3>

                <form method="POST"
                      :action="mode === 'edit'
                          ? '{{ route('super-admin.ai-providers.update', ['provider' => '__ID__']) }}'.replace('__ID__', form.id)
                          : '{{ route('super-admin.ai-providers.store') }}'"
                      class="mt-4 space-y-3">
                    @csrf
                    <template x-if="mode === 'edit'">
                        <input type="hidden" name="_method" value="PUT">
                    </template>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <x-ui.field label="Name" name="name" required>
                            <input name="name" class="input" required x-model="form.name"
                                   placeholder="OpenRouter">
                        </x-ui.field>

                        <x-ui.field label="Model" name="model" required>
                            <input name="model" class="input" required x-model="form.model"
                                   placeholder="openai/gpt-4o-mini">
                        </x-ui.field>
                    </div>

                    <x-ui.field label="Base URL" name="base_url" required
                                hint="Without the /chat/completions suffix.">
                        <input name="base_url" type="url" class="input" required x-model="form.base_url"
                               placeholder="https://openrouter.ai/api/v1">
                    </x-ui.field>

                    {{-- `hint` is a plain prop on x-ui.field, not a slot, so the
                         mode-dependent wording is rendered here instead. --}}
                    <x-ui.field label="API key" name="api_key">
                        <input name="api_key" type="password" class="input" autocomplete="off"
                               :required="mode === 'create'"
                               :placeholder="mode === 'edit' ? 'Leave blank to keep the current key' : 'sk-…'">
                        <p class="field-hint" x-show="mode === 'edit'">
                            The stored key is never shown. Type a new one only to replace it.
                        </p>
                        <p class="field-hint" x-show="mode === 'create'">Sent only to the provider.</p>
                    </x-ui.field>

                    <label class="flex items-center gap-2 text-sm text-ink">
                        <input type="checkbox" name="is_active" value="1" class="checkbox"
                               x-model="form.is_active">
                        Make this the active provider
                    </label>
                    <p class="field-hint">Only one provider can be active; the others are switched off.</p>

                    <div class="flex justify-end gap-2 border-t border-line pt-4">
                        <button type="button" class="btn btn-ghost btn-sm" @click="open = false">Cancel</button>
                        <button type="submit" class="btn btn-primary btn-sm"
                                x-text="mode === 'edit' ? 'Save changes' : 'Add provider'"></button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-layouts.studyai>
