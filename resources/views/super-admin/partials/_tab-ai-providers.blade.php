@php
    /** @var \Illuminate\Database\Eloquent\Collection|null $providers */
@endphp

@if(!$providers)
    <div class="surface p-8 text-center text-faint">Loading…</div>
@elseif($providers->isEmpty())
    <div class="surface p-8 text-center text-faint">No providers configured.</div>
@else
<div class="space-y-6">
    {{-- Add provider form --}}
    <div class="surface">
        <div class="px-5 py-3 border-b flex items-center justify-between">
            <span class="font-semibold text-ink">Add AI Provider</span>
            <button onclick="document.getElementById('createProvider').classList.toggle('hidden')" class="px-3 py-1 btn btn-primary text-sm">New Provider</button>
        </div>
        <form id="createProvider" method="POST" action="{{ route('super-admin.ai-providers.store') }}" class="hidden px-5 py-4 space-y-3">
            @csrf
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div><label class="text-xs text-muted block">Name</label><input name="name" class="w-full border rounded px-2 py-1" required></div>
                <div><label class="text-xs text-muted block">Model</label><input name="model" class="w-full border rounded px-2 py-1" required></div>
                <div class="col-span-2"><label class="text-xs text-muted block">Base URL</label><input name="base_url" class="w-full border rounded px-2 py-1" placeholder="https://api.example.com/v1" required></div>
                <div class="col-span-2"><label class="text-xs text-muted block">API Key</label><input name="api_key" type="password" class="w-full border rounded px-2 py-1" required></div>
                <div><label class="text-xs text-muted flex items-center gap-1"><input type="checkbox" name="is_active" value="1"> Set active</label></div>
            </div>
            <button class="px-3 py-1 btn btn-primary text-sm">Save</button>
        </form>
    </div>

    {{-- Providers list --}}
    <div class="surface">
        <div class="px-5 py-3 border-b font-semibold text-ink">Providers</div>
        <div class="table-wrap"><table class="w-full text-sm">
            <thead class="text-left text-muted border-b">
                <tr><th class="px-5 py-2">Name</th><th class="px-5 py-2">Model</th><th class="px-5 py-2">Base URL</th><th class="px-5 py-2">Status</th><th class="px-5 py-2"></th></tr>
            </thead>
            <tbody>
                @foreach($providers as $p)
                    <tr class="border-b">
                        <td class="px-5 py-2 font-medium">{{ $p->name }}</td>
                        <td class="px-5 py-2">{{ $p->model }}</td>
                        <td class="px-5 py-2 text-muted text-xs">{{ \Illuminate\Support\Str::limit($p->base_url, 40) }}</td>
                        <td class="px-5 py-2">
                            @if($p->is_active)
                                <span class="px-2 py-0.5 bg-paper-sunk text-ok rounded text-xs">Active</span>
                            @else
                                <span class="px-2 py-0.5 bg-paper-sunk text-muted rounded text-xs">Inactive</span>
                            @endif
                        </td>
                        <td class="px-5 py-2 text-right whitespace-nowrap">
                            <form method="POST" action="{{ route('super-admin.ai-providers.update', $p) }}" class="inline">
                                @csrf @method('PUT')
                                <input name="name" value="{{ $p->name }}" class="border rounded px-1 py-0.5 text-xs w-24 mb-1">
                                <input name="model" value="{{ $p->model }}" class="border rounded px-1 py-0.5 text-xs w-20 mb-1">
                                <input name="base_url" value="{{ $p->base_url }}" class="border rounded px-1 py-0.5 text-xs w-40 mb-1">
                                <input name="api_key" type="password" placeholder="unchanged" class="border rounded px-1 py-0.5 text-xs w-24 mb-1">
                                <label class="text-xs flex items-center gap-1"><input type="checkbox" name="is_active" value="1" {{ $p->is_active ? 'checked' : '' }}> active</label>
                                <button class="text-xs text-accent ml-2">Save</button>
                            </form>
                            <form method="POST" action="{{ route('super-admin.ai-providers.destroy', $p) }}" class="inline" onsubmit="return confirm('Delete provider?')">
                                @csrf @method('DELETE')
                                <button class="text-xs text-danger">Delete</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table></div>
    </div>
</div>
@endif
