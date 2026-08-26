<x-guest-layout title="School not found"
                :description="'We could not match '.$host.' to a school.'">

    <div class="space-y-5">
        <div class="alert alert-warn">
            <x-icon name="alert-circle" class="mt-px flex-none" />
            <span>Check the web address your school gave you, or pick it from the list below.</span>
        </div>

        @if ($schools->isNotEmpty())
            <div class="surface divide-y">
                @foreach ($schools as $school)
                    <a href="{{ $school->url('/login') }}"
                       class="flex items-center gap-3 px-3 py-2.5 transition-colors hover:bg-surface-sunk">
                        <span class="avatar avatar-sm">{{ $school->initials() }}</span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-sm font-medium text-ink">{{ $school->name }}</span>
                            <span class="block truncate text-xs text-faint">{{ $school->subdomain }}</span>
                        </span>
                        <x-icon name="chevron-right" class="text-faint" />
                    </a>
                @endforeach
            </div>
        @endif
    </div>
</x-guest-layout>
