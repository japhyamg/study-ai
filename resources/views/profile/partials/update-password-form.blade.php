<x-ui.card title="Password" subtitle="Use a long, unique password you don't reuse elsewhere.">
    <form method="POST" action="{{ route('password.update') }}" class="space-y-4">
        @csrf
        @method('put')

        <x-ui.field label="Current password" name="current_password"
                    :error="$errors->updatePassword->first('current_password')">
            <input id="current_password" name="current_password" type="password"
                   class="input" autocomplete="current-password" required>
        </x-ui.field>

        <x-ui.field label="New password" name="password"
                    hint="At least 8 characters."
                    :error="$errors->updatePassword->first('password')">
            <input id="password" name="password" type="password"
                   class="input" autocomplete="new-password" required>
        </x-ui.field>

        <x-ui.field label="Confirm new password" name="password_confirmation"
                    :error="$errors->updatePassword->first('password_confirmation')">
            <input id="password_confirmation" name="password_confirmation" type="password"
                   class="input" autocomplete="new-password" required>
        </x-ui.field>

        <div class="flex items-center justify-end gap-3">
            @if (session('status') === 'password-updated')
                <p class="text-xs text-success">Saved.</p>
            @endif
            <button type="submit" class="btn btn-primary">Update password</button>
        </div>
    </form>
</x-ui.card>
