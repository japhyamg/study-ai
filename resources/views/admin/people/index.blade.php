@php
    $createRoute = match ($role) {
        'teacher' => 'admin.teachers.create',
        'student' => 'admin.students.create',
        default => 'admin.administrators.create',
    };

    $addLabel = match ($role) {
        'teacher' => 'Add teacher',
        'student' => 'Add student',
        default => 'Add administrator',
    };
@endphp

<x-layouts.studyai :title="$heading">
    <x-slot:actions>
        {{-- Administrators are added one at a time; there are never enough of
             them to be worth a spreadsheet. --}}
        @if ($role !== 'admin')
            <x-ui.button :href="route('admin.people.import', $role)" variant="ghost">Import</x-ui.button>
        @endif

        <x-ui.button :href="route($createRoute)" icon="plus">{{ $addLabel }}</x-ui.button>
    </x-slot:actions>

    {{-- Shown once, straight after the account is made: there is no mail set
         up, so the admin has to hand these over themselves. --}}
    @if (session('credentials'))
        @php $c = session('credentials'); @endphp
        <div class="alert-info mb-5">
            <p class="font-medium">{{ $c['name'] }} was added.</p>
            <p class="mt-1 text-sm">
                Sign in with <span class="font-medium">{{ $c['login'] }}</span>
                and password <span class="font-mono font-medium">{{ $c['password'] }}</span>.
            </p>
            <p class="mt-1 text-xs">
                This password is not shown again. Pass it on and ask them to change it.
            </p>
        </div>
    @endif

    <form method="GET" class="mb-4 flex max-w-md gap-2">
        <input name="search" value="{{ $search }}" class="input"
               placeholder="Search by name or email" aria-label="Search {{ strtolower($heading) }}">

        <x-ui.button type="submit" variant="ghost">Search</x-ui.button>

        @if ($search)
            <x-ui.button :href="url()->current()" variant="ghost">Clear</x-ui.button>
        @endif
    </form>

    @if ($members->isEmpty())
        <x-ui.empty icon="users"
                    title="{{ $search ? 'No matches' : 'No '.strtolower($heading).' yet' }}"
                    message="{{ $search
                        ? 'No one here matches that name or email.'
                        : 'Invite people to the school and give them this role.' }}" />
    @else
        <div class="surface overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-surface-sunk text-left text-xs text-muted">
                    <tr>
                        <th class="px-4 py-2.5 font-medium">Name</th>
                        <th class="px-4 py-2.5 font-medium">Email</th>
                        <th class="px-4 py-2.5 font-medium">Status</th>
                        <th class="px-4 py-2.5 font-medium">Joined</th>
                        <th class="px-4 py-2.5"></th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-line">
                    @foreach ($members as $m)
                        <tr class="transition-colors hover:bg-surface-sunk/50">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2.5">
                                    <span class="avatar shrink-0">{{ $m->user?->initials() }}</span>
                                    <a href="{{ route('admin.people.show', $m->user) }}"
                                       class="font-medium text-ink hover:text-accent">
                                        {{ $m->user?->name ?? 'Unknown' }}
                                    </a>
                                </div>
                            </td>

                            <td class="px-4 py-3 text-muted">{{ $m->user?->email }}</td>

                            <td class="px-4 py-3">
                                @if ($m->user?->is_active)
                                    <x-ui.badge tone="success">Active</x-ui.badge>
                                @else
                                    <x-ui.badge>Inactive</x-ui.badge>
                                @endif
                            </td>

                            <td class="px-4 py-3 text-xs text-faint">
                                {{ $m->created_at?->format('j M Y') ?? '—' }}
                            </td>

                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('admin.people.show', $m->user) }}"
                                   class="text-xs text-accent">Open</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if ($members->hasPages())
            <div class="mt-4">{{ $members->links() }}</div>
        @endif
    @endif
</x-layouts.studyai>
