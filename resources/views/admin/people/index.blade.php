@php
    $tabs = [
        'teacher' => ['label' => 'Teachers', 'route' => 'admin.teachers'],
        'student' => ['label' => 'Students', 'route' => 'admin.students'],
        'admin' => ['label' => 'Administrators', 'route' => 'admin.administrators'],
    ];

    $roleLabels = [
        'teacher' => 'Teacher',
        'student' => 'Student',
        'admin' => 'Administrator',
    ];
@endphp

<x-layouts.studyai :title="$heading">
    <x-slot:actions>
        <x-ui.button :href="route('admin.members')" variant="ghost">Invite people</x-ui.button>
    </x-slot:actions>

    {{-- The three role lists are the same page with a different filter, so
         they read as tabs rather than separate destinations. --}}
    <div class="mb-5 flex flex-wrap gap-1 border-b border-line">
        @foreach ($tabs as $key => $tab)
            <a href="{{ route($tab['route']) }}" class="tab-btn {{ $role === $key ? 'active' : '' }}">
                {{ $tab['label'] }}
                <span class="tnum ms-1 text-xs text-faint">({{ $counts[$key] ?? 0 }})</span>
            </a>
        @endforeach
    </div>

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
