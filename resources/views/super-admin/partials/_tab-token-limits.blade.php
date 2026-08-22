@php
    /** @var array $tokenLimitRows */
    /** @var int $defaultLimit */
@endphp
@if(empty($tokenLimitRows))
    <div class="surface p-8 text-center text-faint">No teacher data found.</div>
@else
<div class="space-y-6">
    <div class="surface">
        <form method="POST" action="{{ route('super-admin.token-limits.default') }}" class="px-5 py-4 flex items-end gap-3">
            @csrf @method('PUT')
            <div><label class="text-xs text-muted block">Platform default monthly token limit</label>
                <input name="default_limit" type="number" value="{{ $defaultLimit }}" class="border rounded px-2 py-1 w-40"></div>
            <button class="px-3 py-1 btn btn-primary text-sm">Save Default</button>
        </form>
    </div>
    <div class="surface">
        <div class="px-5 py-3 border-b font-semibold text-ink">Teacher Limits</div>
        <div class="table-wrap"><table class="w-full text-sm">
            <thead class="text-left text-muted border-b">
                <tr>
                    <th class="px-5 py-2">Teacher</th><th class="px-5 py-2">School</th><th class="px-5 py-2">Role</th>
                    <th class="px-5 py-2">Limit</th><th class="px-5 py-2">Used</th><th class="px-5 py-2">Remaining</th><th class="px-5 py-2"></th>
                </tr>
            </thead>
            <tbody>
                @foreach($tokenLimitRows as $t)
                    <tr class="border-b">
                        <td class="px-5 py-2">{{ $t['name'] }}<div class="text-xs text-faint">{{ $t['email'] }}</div></td>
                        <td class="px-5 py-2 text-muted">{{ $t['school_name'] }}</td>
                        <td class="px-5 py-2">{{ $t['role'] }}</td>
                        <td class="px-5 py-2">{{ number_format($t['monthly_limit']) }}</td>
                        <td class="px-5 py-2">{{ number_format($t['used_this_month']) }}</td>
                        <td class="px-5 py-2">{{ number_format($t['remaining']) }}</td>
                        <td class="px-5 py-2 text-right whitespace-nowrap">
                            <form method="POST" action="{{ route('super-admin.token-limits.user', $t['user_id']) }}" class="inline">
                                @csrf @method('PUT')
                                <input name="monthly_limit" type="number" value="{{ $t['monthly_limit'] }}" class="border rounded px-1 py-0.5 text-xs w-24">
                                <label class="text-xs"><input type="checkbox" name="is_enabled" value="1" {{ $t['is_enabled'] ? 'checked' : '' }}> on</label>
                                <button class="text-xs text-accent">Set</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table></div>
    </div>
</div>
@endif
