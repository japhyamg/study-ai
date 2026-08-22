<x-guest-layout title="Get started">
    <div class="space-y-6">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <x-ui.icon name="building" class="w-4 h-4" style="color:var(--accent)"/>
                <span class="field-label !mb-0">Create a school</span>
            </div>
            <p class="muted text-sm mb-3">Start your own school workspace and become its administrator. You'll choose a subdomain (e.g. <code class="text-xs">lincoln.studyai.test</code>).</p>
            <form method="POST" action="{{ route('onboarding.school') }}" class="space-y-3">@csrf
                <div>
                    <label class="field-label" for="school_name">School name</label>
                    <input id="school_name" type="text" name="name" class="input" placeholder="e.g. Lincoln High School" required>
                </div>
                <div>
                    <label class="field-label" for="school_slug">Subdomain <span class="faint normal-case">(optional — generated from the name)</span></label>
                    <input id="school_slug" type="text" name="slug" class="input" placeholder="lincoln" pattern="[a-z0-9\-]*" title="Lowercase letters, numbers and dashes only">
                </div>
                <button class="btn btn-primary btn-block">Create school</button>
            </form>
        </div>

        <div class="border-t pt-6">
            <div class="flex items-center gap-2 mb-1">
                <x-ui.icon name="key" class="w-4 h-4" style="color:var(--accent)"/>
                <span class="field-label !mb-0">Join with a class code</span>
            </div>
            <p class="muted text-sm mb-3">Have an invite code from your teacher? Enter it to join as a student.</p>
            <form method="POST" action="{{ route('onboarding.join') }}" class="space-y-3">@csrf
                <input type="text" name="code" class="input" placeholder="Class code" required>
                <button class="btn btn-outline btn-block">Join class</button>
            </form>
        </div>
    </div>
</x-guest-layout>
