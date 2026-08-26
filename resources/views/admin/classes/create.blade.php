<x-layouts.studyai title="New class" subtitle="Add a class group to a level">
    <div class="max-w-2xl">
        <x-ui.card>
            <form method="POST" action="{{ route('admin.classes.store') }}" class="space-y-4">
                @csrf
                @include('admin.classes._form', ['arm' => null])
                <div class="flex justify-end gap-2">
                    <a href="{{ route('admin.classes.index') }}" class="btn btn-ghost">Cancel</a>
                    <button class="btn btn-primary">Create class</button>
                </div>
            </form>
        </x-ui.card>
    </div>
</x-layouts.studyai>
