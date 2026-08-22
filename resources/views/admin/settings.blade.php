<x-layouts.studyai title="Settings">
    <div class="surface max-w-xl">
        <form method="PUT" action="{{ route('admin.settings.update') }}" class="px-6 py-5 space-y-4">
            @csrf @method('PUT')
            <div>
                <label class="text-xs text-muted block">School Name</label>
                <input name="name" value="{{ $school->name }}" class="w-full border rounded px-2 py-1" required>
            </div>
            <div>
                <label class="text-xs text-muted block">Logo URL</label>
                <input name="logo" value="{{ $school->logo }}" class="w-full border rounded px-2 py-1">
            </div>
            <button class="px-4 py-1.5 btn btn-primary text-sm">Save Settings</button>
        </form>
    </div>
</x-layouts.studyai>
