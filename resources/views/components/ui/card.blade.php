@props(['title' => null, 'subtitle' => null, 'padded' => true])

<section {{ $attributes->merge(['class' => 'surface']) }}>
    @if($title || isset($actions))
        <header class="card-head">
            <div class="min-w-0">
                @if($title)<h2 class="card-title">{{ $title }}</h2>@endif
                @if($subtitle)<p class="mt-0.5 text-xs text-faint">{{ $subtitle }}</p>@endif
            </div>
            @isset($actions)<div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>@endisset
        </header>
    @endif

    <div @class(['card-body' => $padded])>{{ $slot }}</div>

    @isset($footer)<footer class="card-foot">{{ $footer }}</footer>@endisset
</section>
