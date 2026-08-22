<x-layouts.studyai title="Terms">
    <h2 class="font-semibold text-ink mb-3">Terms</h2>
    @if(session('status'))<div class="text-ok text-sm mb-3">{{ session('status') }}</div>@endif

    <form method="POST" action="{{ route('admin.terms.store') }}" class="surface p-4 mb-4 flex flex-wrap gap-2 items-end max-w-2xl">
        @csrf
        <div><label class="text-xs">Name</label><input name="name" required class="border rounded px-2 py-1 text-sm w-40"></div>
        <div><label class="text-xs">Start</label><input name="start_date" type="date" class="border rounded px-2 py-1 text-sm"></div>
        <div><label class="text-xs">End</label><input name="end_date" type="date" class="border rounded px-2 py-1 text-sm"></div>
        <label class="flex items-center gap-1 text-xs"><input type="checkbox" name="active" value="1"> Active</label>
        <button class="btn btn-primary">Add</button>
    </form>

    <div class="surface">
        <ul class="divide-y text-sm">
            @forelse($terms as $t)
                <li class="px-5 py-3 flex items-center justify-between">
                    <div>
                        <span class="font-medium">{{ $t->name }}</span>
                        @if($t->active)<span class="ml-2 text-xs px-2 py-0.5 rounded bg-green-50 text-ok border border-green-200">Active</span>@endif
                        @if($t->start_date)<span class="text-xs text-faint ml-2">{{ $t->start_date }} → {{ $t->end_date }}</span>@endif
                    </div>
                    <div class="flex gap-3">
                        <form method="POST" action="{{ route('admin.terms.update', $t) }}">@csrf @method('PUT')<input name="name" value="{{ $t->name }}" class="border rounded px-1 py-0.5 text-xs w-36"><button class="text-accent text-xs ml-1">Save</button></form>
                        <form method="POST" action="{{ route('admin.terms.destroy', $t) }}">@csrf @method('DELETE')<button class="text-danger text-xs">Delete</button></form>
                    </div>
                </li>
            @empty
                <li class="px-5 py-4 text-faint">No terms.</li>
            @endforelse
        </ul>
    </div>
    {{ $terms->links() }}
</x-layouts.studyai>
