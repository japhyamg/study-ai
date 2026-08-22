<x-guest-layout title="Get started">
    <div class="space-y-5">
        <div>
            <div class="field-label">Create a school</div>
            <p class="muted text-sm mb-2">Start your own school and become its admin.</p>
            <form method="POST" action="{{ route('onboarding.school') }}">@csrf
                <input type="text" name="name" class="input" placeholder="e.g. Lincoln High School" required>
                <button class="btn btn-primary btn-block mt-2">Create school</button>
            </form>
        </div>

        <div class="border-t pt-5">
            <div class="field-label">Join with a class code</div>
            <p class="muted text-sm mb-2">Have an invite code from your teacher? Enter it to join.</p>
            <form method="POST" action="{{ route('onboarding.join') }}">@csrf
                <input type="text" name="code" class="input" placeholder="Class code" required>
                <button class="btn btn-outline btn-block mt-2">Join class</button>
            </form>
        </div>
    </div>
</x-guest-layout>
