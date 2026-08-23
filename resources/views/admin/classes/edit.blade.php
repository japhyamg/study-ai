<x-layouts.studyai :title="'Edit '.$arm->fullName()" subtitle="Class details">
    <div class="max-w-2xl">
        <x-ui.card>
            <form method="POST" action="{{ route('admin.classes.update', $arm) }}" class="space-y-4">
                @csrf @method('put')
                @include('admin.classes._form', ['arm' => $arm])
                <div class="flex justify-end gap-2">
                    <a href="{{ route('admin.classes.show', $arm) }}" class="btn btn-ghost">Cancel</a>
                    <button class="btn btn-primary">Save changes</button>
                </div>
            </form>
        </x-ui.card>
    </div>
</x-layouts.studyai>
