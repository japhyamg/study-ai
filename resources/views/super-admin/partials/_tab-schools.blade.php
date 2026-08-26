@php
    /** @var \Illuminate\Database\Eloquent\Collection|null $schoolList */
@endphp
@if(!$schoolList)
    <div class="surface p-8 text-center text-faint">Loading…</div>
@elseif($schoolList->isEmpty())
    <div class="space-y-6">
        {{-- Add school form --}}
        <div class="surface">
            <div class="px-5 py-3 border-b flex items-center justify-between">
                <span class="font-semibold text-ink">Add School</span>
                <button onclick="document.getElementById('createSchool').classList.toggle('hidden')" class="px-3 py-1 btn btn-primary text-sm">New School</button>
            </div>
            <form id="createSchool" method="POST" action="{{ route('super-admin.schools.store') }}" class="hidden px-5 py-4 border-b bg-paper-sunk space-y-3">
                @csrf
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div><label class="text-xs text-muted block">Name</label><input name="name" class="w-full border rounded px-2 py-1" required></div>
                    <div><label class="text-xs text-muted block">Slug</label><input name="slug" class="w-full border rounded px-2 py-1" required></div>
                    <div><label class="text-xs text-muted block">Logo URL</label><input name="logo" class="w-full border rounded px-2 py-1"></div>
                </div>
                <button class="px-3 py-1 btn btn-primary text-sm">Create</button>
            </form>
        </div>
        <div class="surface p-8 text-center text-faint">No schools created yet.</div>
    </div>
@else
<div class="space-y-6">
    {{-- Add school form --}}
    <div class="surface">
        <div class="px-5 py-3 border-b flex items-center justify-between">
            <span class="font-semibold text-ink">Add School</span>
            <button onclick="document.getElementById('createSchool').classList.toggle('hidden')" class="px-3 py-1 btn btn-primary text-sm">New School</button>
        </div>
        <form id="createSchool" method="POST" action="{{ route('super-admin.schools.store') }}" class="hidden px-5 py-4 border-b bg-paper-sunk space-y-3">
            @csrf
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div><label class="text-xs text-muted block">Name</label><input name="name" class="w-full border rounded px-2 py-1" required></div>
                <div><label class="text-xs text-muted block">Slug</label><input name="slug" class="w-full border rounded px-2 py-1" required></div>
                <div><label class="text-xs text-muted block">Logo URL</label><input name="logo" class="w-full border rounded px-2 py-1"></div>
            </div>
            <button class="px-3 py-1 btn btn-primary text-sm">Create</button>
        </form>
    </div>

    {{-- Recent schools list --}}
    <div class="surface">
        <div class="px-5 py-3 border-b font-semibold text-ink">Recent Schools</div>
        <div class="table-wrap"><table class="w-full text-sm">
            <thead class="text-left text-muted border-b">
                <tr><th class="px-5 py-2">Name</th><th class="px-5 py-2">Slug</th><th class="px-5 py-2">Members</th><th class="px-5 py-2">Created</th><th class="px-5 py-2"></th></tr>
            </thead>
            <tbody>
                @foreach($schoolList as $s)
                    <tr class="border-b">
                        <td class="px-5 py-2 font-medium">{{ $s->name }}</td>
                        <td class="px-5 py-2 text-muted">{{ $s->slug }}</td>
                        <td class="px-5 py-2">{{ $s->members_count }}</td>
                        <td class="px-5 py-2 text-muted">{{ $s->created_at->format('M j, Y') }}</td>
                        <td class="px-5 py-2 text-right"><a href="{{ route('super-admin.schools.show', $s) }}" class="text-xs text-accent">View</a></td>
                    </tr>
                @endforeach
            </tbody>
        </table></div>
    </div>
</div>
@endif
