{{-- Superseded by the tabbed profile screen; kept so any legacy include
     of this partial still renders the same form. --}}
<form method="POST" action="{{ route('profile.update') }}" class="space-y-4">
    @csrf
    @method('patch')

    <x-ui.field label="Full name" name="name" required>
        <input id="name" name="name" type="text" class="input" value="{{ old('name', $user->name) }}" required>
    </x-ui.field>

    <x-ui.field label="Email address" name="email" required>
        <input id="email" name="email" type="email" class="input" value="{{ old('email', $user->email) }}" required>
    </x-ui.field>

    <div class="flex justify-end">
        <button type="submit" class="btn btn-primary">Save</button>
    </div>
</form>
