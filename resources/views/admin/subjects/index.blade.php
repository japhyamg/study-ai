<x-layouts.studyai title="Subjects">
    <h2 class="font-semibold text-ink mb-3">Subjects</h2>
    @if(session('status'))<div class="text-ok text-sm mb-3">{{ session('status') }}</div>@endif

    <form method="POST" action="{{ route('admin.subjects.store') }}" class="surface p-4 mb-4 flex gap-2 max-w-xl">
        @csrf
        <input name="name" required placeholder="Subject name" class="flex-1 border rounded px-2 py-1 text-sm">
        <input name="code" placeholder="Code (optional)" class="w-32 border rounded px-2 py-1 text-sm">
        <button class="btn btn-primary">Add</button>
    </form>

    <div class="surface">
        <ul class="divide-y text-sm">
            @forelse($subjects as $s)
                <li class="px-5 py-3 flex items-center justify-between">
                    <div><span class="font-medium">{{ $s->name }}</span> @if($s->code)<span class="text-xs text-faint">({{ $s->code }})</span>@endif</div>
                    <div class="flex gap-3">
                        <form method="POST" action="{{ route('admin.subjects.update', $s) }}">@csrf @method('PUT')<input name="name" value="{{ $s->name }}" class="border rounded px-1 py-0.5 text-xs w-40"><button class="text-accent text-xs ml-1">Save</button></form>
                        <form method="POST" action="{{ route('admin.subjects.destroy', $s) }}">@csrf @method('DELETE')<button class="text-danger text-xs">Delete</button></form>
                    </div>
                </li>
            @empty
                <li class="px-5 py-4 text-faint">No subjects.</li>
            @endforelse
        </ul>
    </div>
    {{ $subjects->links() }}
</x-layouts.studyai>
