<x-layouts.studyai title="AI Providers">
    <div class="surface">
        <div class="px-5 py-3 border-b flex items-center justify-between">
            <span class="font-semibold text-ink">AI Providers</span>
            <button onclick="document.getElementById('createForm').classList.toggle('hidden')" class="px-3 py-1 btn btn-primary text-sm">New Provider</button>
        </div>

        <form id="createForm" method="POST" action="{{ route('super-admin.ai-providers.store') }}" class="hidden px-5 py-4 border-b bg-paper-sunk space-y-3">
            @csrf
            <div class="grid grid-cols-2 gap-3">
                <div><label class="text-xs text-muted">Name</label><input name="name" class="w-full border rounded px-2 py-1" required></div>
                <div><label class="text-xs text-muted">Model</label><input name="model" class="w-full border rounded px-2 py-1" required></div>
                <div class="col-span-2"><label class="text-xs text-muted">Base URL</label><input name="base_url" class="w-full border rounded px-2 py-1" placeholder="https://api.example.com/v1" required></div>
                <div class="col-span-2"><label class="text-xs text-muted">API Key</label><input name="api_key" type="password" class="w-full border rounded px-2 py-1" required></div>
                <div><label class="text-xs text-muted flex items-center gap-1"><input type="checkbox" name="is_active" value="1"> Set active</label></div>
            </div>
            <button class="px-3 py-1 btn btn-primary text-sm">Save</button>
        </form>

        <table class="w-full text-sm">
            <thead class="text-left text-muted border-b">
                <tr><th class="px-5 py-2">Name</th><th class="px-5 py-2">Model</th><th class="px-5 py-2">Base URL</th><th class="px-5 py-2">Status</th><th class="px-5 py-2"></th></tr>
            </thead>
            <tbody>
                @forelse($providers as $p)
                    <tr class="border-b">
                        <td class="px-5 py-2">{{ $p->name }}</td>
                        <td class="px-5 py-2">{{ $p->model }}</td>
                        <td class="px-5 py-2 text-muted text-xs">{{ $p->base_url }}</td>
                        <td class="px-5 py-2">
                            @if($p->is_active)<span class="px-2 py-0.5 bg-green-100 text-ok rounded text-xs">Active</span>@else<span class="px-2 py-0.5 bg-paper-sunk text-muted rounded text-xs">Inactive</span>@endif
                        </td>
                        <td class="px-5 py-2 text-right whitespace-nowrap">
                            <form method="POST" action="{{ route('super-admin.ai-providers.update', $p) }}" class="inline">
                                @csrf @method('PUT')
                                <input name="name" value="{{ $p->name }}" class="border rounded px-1 py-0.5 text-xs w-24">
                                <input name="model" value="{{ $p->model }}" class="border rounded px-1 py-0.5 text-xs w-20">
                                <input name="base_url" value="{{ $p->base_url }}" class="border rounded px-1 py-0.5 text-xs w-40">
                                <input name="api_key" type="password" placeholder="unchanged" class="border rounded px-1 py-0.5 text-xs w-24">
                                <label class="text-xs"><input type="checkbox" name="is_active" value="1" {{ $p->is_active ? 'checked' : '' }}> active</label>
                                <button class="text-xs text-accent">Save</button>
                            </form>
                            <form method="POST" action="{{ route('super-admin.ai-providers.destroy', $p) }}" class="inline" onsubmit="return confirm('Delete provider?')">
                                @csrf @method('DELETE')
                                <button class="text-xs text-danger">Delete</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-5 py-4 text-faint">No providers configured.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.studyai>
