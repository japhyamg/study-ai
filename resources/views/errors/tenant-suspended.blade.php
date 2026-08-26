<x-guest-layout title="Account suspended"
                :description="$school->name.' is currently unavailable.'">

    <div class="alert alert-warn">
        <x-icon name="alert-circle" class="mt-px flex-none" />
        <div>
            <p class="font-medium">This school's workspace has been suspended.</p>
            <p class="mt-1 text-xs">Please contact your school administrator or our support team.</p>
        </div>
    </div>
</x-guest-layout>
