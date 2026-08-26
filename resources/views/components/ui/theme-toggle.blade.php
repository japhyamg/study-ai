{{-- Theme switching lives in the topbar of the app shell; this shim keeps
     older views that still reference <x-ui.theme-toggle /> rendering. --}}
<button type="button"
        {{ $attributes->merge(['class' => 'btn btn-ghost btn-icon']) }}
        aria-label="Toggle theme"
        onclick="(function(){var d=document.documentElement,n=!d.classList.contains('dark');d.classList.toggle('dark',n);try{localStorage.setItem('theme',n?'dark':'light')}catch(e){}})()">
    <x-icon name="sun" class="hidden dark:block" />
    <x-icon name="moon" class="dark:hidden" />
</button>
