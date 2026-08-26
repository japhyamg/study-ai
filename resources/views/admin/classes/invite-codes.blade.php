<x-layouts.studyai :title="'Invite codes · '.$arm->fullName()" subtitle="Let students join this class themselves">
    <x-slot:actions>
        <a href="{{ route('admin.classes.show', $arm) }}" class="btn btn-outline btn-sm">Back to class</a>
    </x-slot:actions>

    <div class="grid gap-5 lg:grid-cols-3">
        <div class="lg:col-span-2">
            <x-ui.card title="Codes">
                <div class="mb-4 rounded-lg border bg-surface-sunk p-3">
                    <p class="text-xs text-faint">Permanent class code</p>
                    <p class="mt-1 font-mono text-lg font-semibold tracking-wider text-ink">{{ $arm->invite_code }}</p>
                    <p class="mt-1 text-xs text-muted">Always valid, unlimited uses.</p>
                </div>

                @if ($codes->isEmpty())
                    <x-ui.empty message="No one-off codes generated yet." />
                @else
                    <div class="table-wrap">
                        <table class="table">
                            <thead>
                                <tr><th>Code</th><th class="num">Used</th><th>Expires</th><th>Status</th></tr>
                            </thead>
                            <tbody>
                                @foreach ($codes as $code)
                                    @php
                                        $expired = $code->expires_at && $code->expires_at->isPast();
                                        $used = $code->max_uses && $code->used_count >= $code->max_uses;
                                    @endphp
                                    <tr>
                                        <td><code class="font-mono text-sm text-ink">{{ $code->code }}</code></td>
                                        <td class="num tnum">
                                            {{ $code->used_count }}{{ $code->max_uses ? ' / '.$code->max_uses : '' }}
                                        </td>
                                        <td class="text-muted">{{ $code->expires_at?->format('j M Y') ?? 'Never' }}</td>
                                        <td>
                                            <span class="badge {{ $expired || $used ? '' : 'badge-ok' }}">
                                                {{ $expired ? 'Expired' : ($used ? 'Used up' : 'Active') }}
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-ui.card>
        </div>

        <x-ui.card title="Generate a code" subtitle="Useful for a limited intake.">
            <form method="POST" action="{{ route('admin.classes.invite-codes.store', $arm) }}" class="space-y-4">
                @csrf
                <x-ui.field label="Maximum uses" name="max_uses" hint="Leave blank for unlimited.">
                    <input name="max_uses" type="number" min="1" max="500" class="input" value="{{ old('max_uses') }}">
                </x-ui.field>
                <x-ui.field label="Expires" name="expires_at" hint="Leave blank to never expire.">
                    <input name="expires_at" type="date" class="input" value="{{ old('expires_at') }}">
                </x-ui.field>
                <button class="btn btn-primary btn-block">Generate code</button>
            </form>
        </x-ui.card>
    </div>
</x-layouts.studyai>
