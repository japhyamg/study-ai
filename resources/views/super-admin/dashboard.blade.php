<x-layouts.studyai title="Super Admin Dashboard">
    <div class="surface">
        <div class="px-5 py-4 border-b flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span class="text-accent text-xl">🛡️</span>
                <span class="font-semibold text-ink">Super Admin Dashboard</span>
            </div>
            <span class="text-sm text-muted">Platform Management</span>
        </div>

        {{-- Tab nav (query-param driven; links work without JS) --}}
        @php
            $tabs = [
                ['overview', 'Overview'],
                ['analytics', 'Analytics'],
                ['token-usage', 'Token Usage'],
                ['token-limits', 'Token Limits'],
                ['usage-teachers', 'Usage & Teachers'],
                ['ai-providers', 'AI Providers'],
                ['schools', 'Schools'],
            ];
            $activeTab = request('tab', 'overview');
        @endphp
        <nav class="flex gap-1 border-b bg-paper-sunk overflow-x-auto">
            @foreach($tabs as [$id, $label])
                @php $active = $id === $activeTab; @endphp
                <a href="{{ route('super-admin.dashboard') }}?tab={{ $id }}"
                   class="tab-link flex-shrink-0 flex items-center gap-2 px-4 py-2.5 text-sm font-medium border-b-2 whitespace-nowrap transition-colors {{ $active ? 'border-accent text-ink' : 'border-transparent text-muted hover:text-ink' }}">
                    <span class="text-base">{{ $emoji ?? '' }}</span>{{ $label }}
                </a>
            @endforeach
        </nav>

        {{-- Tab panels --}}
        <div class="p-5">
            {{-- Overview --}}
            <div data-tab="overview" class="tab-panel {{ $activeTab === 'overview' ? '' : 'hidden' }}">
                @include('super-admin.partials._tab-overview', ['stats' => $stats, 'recentSchools' => $recentSchools])
            </div>

            {{-- Analytics --}}
            <div data-tab="analytics" class="tab-panel {{ $activeTab === 'analytics' ? '' : 'hidden' }}">
                @include('super-admin.partials._tab-analytics', ['analyticsStats' => $analyticsStats, 'signupsTrend' => $signupsTrend, 'topSchools' => $topSchools])
            </div>

            {{-- Token Usage --}}
            <div data-tab="token-usage" class="tab-panel {{ $activeTab === 'token-usage' ? '' : 'hidden' }}">
                @include('super-admin.partials._tab-token-usage', ['tokenSummary' => $tokenSummary, 'byOperation' => $byOperation, 'byDay' => $byDay, 'days' => $days ?? 30])
            </div>

            {{-- Token Limits --}}
            <div data-tab="token-limits" class="tab-panel {{ $activeTab === 'token-limits' ? '' : 'hidden' }}">
                @include('super-admin.partials._tab-token-limits', ['tokenLimitRows' => $tokenLimitRows, 'defaultLimit' => $defaultLimit])
            </div>

            {{-- Usage & Teachers --}}
            <div data-tab="usage-teachers" class="tab-panel {{ $activeTab === 'usage-teachers' ? '' : 'hidden' }}">
                @include('super-admin.partials._tab-usage-teachers', ['usageTeachersSummary' => $usageTeachersSummary, 'schoolsData' => $schoolsData, 'days' => $days ?? 30])
            </div>

            {{-- AI Providers --}}
            <div data-tab="ai-providers" class="tab-panel {{ $activeTab === 'ai-providers' ? '' : 'hidden' }}">
                @include('super-admin.partials._tab-ai-providers', ['providers' => $providers])
            </div>

            {{-- Schools --}}
            <div data-tab="schools" class="tab-panel {{ $activeTab === 'schools' ? '' : 'hidden' }}">
                @include('super-admin.partials._tab-schools', ['schoolList' => $schoolList])
            </div>
        </div>
    </div>
</x-layouts.studyai>
