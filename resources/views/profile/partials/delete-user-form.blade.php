<x-ui.card title="Delete account">
    <div class="space-y-3" x-data="{ open: false }">
        <p class="text-sm text-muted">
            Permanently remove your account and all personal data. This cannot be undone.
        </p>

        <button type="button" class="btn btn-danger-quiet btn-sm" @click="open = !open">
            <x-icon name="trash" /> Delete account
        </button>

        <form x-show="open" x-cloak method="POST" action="{{ route('profile.destroy') }}" class="space-y-3 border-t pt-3">
            @csrf
            @method('delete')

            <x-ui.field label="Confirm your password" name="password"
                        :error="$errors->userDeletion->first('password')">
                <input name="password" type="password" class="input" required autocomplete="current-password"
                       placeholder="Your password">
            </x-ui.field>

            <div class="flex gap-2">
                <button type="submit" class="btn btn-danger btn-sm">Delete permanently</button>
                <button type="button" class="btn btn-ghost btn-sm" @click="open = false">Cancel</button>
            </div>
        </form>
    </div>
</x-ui.card>
