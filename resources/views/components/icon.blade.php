@props(['name' => 'dot'])

@php
    // Single-stroke outline set (24px grid, 1.75 stroke) — one visual language,
    // no icon-font dependency.
    $paths = [
        'home'          => '<path d="M3 10.5 12 3l9 7.5"/><path d="M5.5 9.5V20a1 1 0 0 0 1 1h4v-6h3v6h4a1 1 0 0 0 1-1V9.5"/>',
        'users'         => '<path d="M16 20v-1.5a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4V20"/><circle cx="9" cy="7" r="3.25"/><path d="M22 20v-1.5a4 4 0 0 0-3-3.87"/><path d="M16.5 3.87a4 4 0 0 1 0 6.26"/>',
        'user'          => '<circle cx="12" cy="8" r="3.75"/><path d="M4.5 20a7.5 7.5 0 0 1 15 0"/>',
        'building'      => '<path d="M3.5 21h17"/><path d="M5.5 21V5a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v16"/><path d="M9.5 7h1.5M13 7h1.5M9.5 11h1.5M13 11h1.5M9.5 15h1.5M13 15h1.5"/>',
        'academic-cap'  => '<path d="m12 3.5 9.5 4.75L12 13 2.5 8.25 12 3.5Z"/><path d="M6.5 10.5v4.75c0 1.52 2.46 2.75 5.5 2.75s5.5-1.23 5.5-2.75V10.5"/><path d="M20.5 9v5"/>',
        'presentation'  => '<path d="M3 4h18"/><path d="M4.5 4v9.5a1.5 1.5 0 0 0 1.5 1.5h12a1.5 1.5 0 0 0 1.5-1.5V4"/><path d="m9.5 20 2.5-5 2.5 5"/>',
        'book'          => '<path d="M4 4.5A1.5 1.5 0 0 1 5.5 3H19v15.5H5.5A1.5 1.5 0 0 0 4 20V4.5Z"/><path d="M4 18.5A1.5 1.5 0 0 0 5.5 21H19"/>',
        'document'      => '<path d="M14 3H7a1.5 1.5 0 0 0-1.5 1.5v15A1.5 1.5 0 0 0 7 21h10a1.5 1.5 0 0 0 1.5-1.5V7.5L14 3Z"/><path d="M13.75 3.25V8h4.75"/><path d="M9 13h6M9 16.5h4"/>',
        'clipboard'     => '<path d="M9 4.5H7.5A1.5 1.5 0 0 0 6 6v13.5A1.5 1.5 0 0 0 7.5 21h9a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H15"/><rect x="9" y="2.75" width="6" height="3.5" rx="1"/><path d="M9.5 12h5M9.5 15.5h3"/>',
        'chat'          => '<path d="M20.5 12c0 4-3.8 7.25-8.5 7.25a9.8 9.8 0 0 1-2.6-.35L4.5 20.5l1.3-3.6A6.85 6.85 0 0 1 3.5 12c0-4 3.8-7.25 8.5-7.25s8.5 3.25 8.5 7.25Z"/>',
        'link'          => '<path d="M10 13.5a3.5 3.5 0 0 0 5 0l3-3a3.54 3.54 0 0 0-5-5l-1.5 1.5"/><path d="M14 10.5a3.5 3.5 0 0 0-5 0l-3 3a3.54 3.54 0 0 0 5 5l1.5-1.5"/>',
        'database'      => '<ellipse cx="12" cy="6" rx="7.5" ry="3"/><path d="M4.5 6v12c0 1.66 3.36 3 7.5 3s7.5-1.34 7.5-3V6"/><path d="M4.5 12c0 1.66 3.36 3 7.5 3s7.5-1.34 7.5-3"/>',
        'layers'        => '<path d="m12 3 8.5 4.5L12 12 3.5 7.5 12 3Z"/><path d="m3.5 12.25 8.5 4.5 8.5-4.5"/><path d="m3.5 16.75 8.5 4.5 8.5-4.5"/>',
        'chart'         => '<path d="M4 20h16"/><path d="M7 20v-6.5M12 20V7M17 20v-9.5"/>',
        'activity'      => '<path d="M3 12h4l2.5-6.5L14 18l2.5-6H21"/>',
        'gauge'         => '<path d="M4.5 18a8.5 8.5 0 1 1 15 0"/><path d="m12 14 3.5-3.5"/><circle cx="12" cy="14" r="1.1" fill="currentColor" stroke="none"/>',
        'sparkles'      => '<path d="m12 3 1.6 4.7L18 9.5l-4.4 1.8L12 16l-1.6-4.7L6 9.5l4.4-1.8L12 3Z"/><path d="M18.5 15.5 19.3 18l2.2.8-2.2.9-.8 2.3-.8-2.3-2.2-.9 2.2-.8.8-2.5Z"/>',
        'shield'        => '<path d="M12 3 5 5.75V11c0 4.3 2.9 8.2 7 9.5 4.1-1.3 7-5.2 7-9.5V5.75L12 3Z"/><path d="m9.25 11.75 1.9 1.9 3.6-3.7"/>',
        'cog'           => '<circle cx="12" cy="12" r="2.75"/><path d="M19.4 14.5a1.4 1.4 0 0 0 .28 1.55l.05.05a1.7 1.7 0 1 1-2.4 2.4l-.05-.05a1.4 1.4 0 0 0-1.55-.28 1.4 1.4 0 0 0-.85 1.28V19.7a1.7 1.7 0 1 1-3.4 0v-.1a1.4 1.4 0 0 0-.92-1.28 1.4 1.4 0 0 0-1.55.28l-.05.05a1.7 1.7 0 1 1-2.4-2.4l.05-.05a1.4 1.4 0 0 0 .28-1.55 1.4 1.4 0 0 0-1.28-.85H4.3a1.7 1.7 0 1 1 0-3.4h.1a1.4 1.4 0 0 0 1.28-.92 1.4 1.4 0 0 0-.28-1.55l-.05-.05a1.7 1.7 0 1 1 2.4-2.4l.05.05a1.4 1.4 0 0 0 1.55.28h.07a1.4 1.4 0 0 0 .85-1.28V4.3a1.7 1.7 0 1 1 3.4 0v.1a1.4 1.4 0 0 0 .85 1.28 1.4 1.4 0 0 0 1.55-.28l.05-.05a1.7 1.7 0 1 1 2.4 2.4l-.05.05a1.4 1.4 0 0 0-.28 1.55v.07a1.4 1.4 0 0 0 1.28.85h.1a1.7 1.7 0 1 1 0 3.4h-.1a1.4 1.4 0 0 0-1.28.85Z"/>',
        'sliders'       => '<path d="M4 7h9M17 7h3M4 17h3M11 17h9"/><circle cx="15" cy="7" r="2"/><circle cx="9" cy="17" r="2"/>',
        'calendar'      => '<rect x="3.5" y="5" width="17" height="16" rx="2"/><path d="M3.5 10h17M8 3v4M16 3v4"/>',
        'menu'          => '<path d="M4 7h16M4 12h16M4 17h16"/>',
        'x'             => '<path d="m6 6 12 12M18 6 6 18"/>',
        'chevron-down'  => '<path d="m6 9.5 6 6 6-6"/>',
        'chevron-right' => '<path d="m9.5 6 6 6-6 6"/>',
        'chevron-left'  => '<path d="m14.5 6-6 6 6 6"/>',
        'plus'          => '<path d="M12 5v14M5 12h14"/>',
        'search'        => '<circle cx="11" cy="11" r="6.5"/><path d="m16 16 4.5 4.5"/>',
        'logout'        => '<path d="M15 17.5V19a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h7a2 2 0 0 1 2 2v1.5"/><path d="M10 12h11M18 9l3 3-3 3"/>',
        'sun'           => '<circle cx="12" cy="12" r="4"/><path d="M12 2.5v2M12 19.5v2M2.5 12h2M19.5 12h2M5.2 5.2l1.4 1.4M17.4 17.4l1.4 1.4M18.8 5.2l-1.4 1.4M6.6 17.4l-1.4 1.4"/>',
        'moon'          => '<path d="M20 14.5A8.5 8.5 0 0 1 9.5 4a8.5 8.5 0 1 0 10.5 10.5Z"/>',
        'check'         => '<path d="m5 12.5 4.5 4.5L19 7.5"/>',
        'check-circle'  => '<circle cx="12" cy="12" r="9"/><path d="m8.5 12.5 2.5 2.5 4.5-5"/>',
        'alert-circle'  => '<circle cx="12" cy="12" r="9"/><path d="M12 7.5v5"/><circle cx="12" cy="16" r="1" fill="currentColor" stroke="none"/>',
        'info'          => '<circle cx="12" cy="12" r="9"/><path d="M12 11v5.5"/><circle cx="12" cy="8" r="1" fill="currentColor" stroke="none"/>',
        'trash'         => '<path d="M4.5 7h15M9.5 7V5.5a1 1 0 0 1 1-1h3a1 1 0 0 1 1 1V7"/><path d="M6.5 7v12a2 2 0 0 0 2 2h7a2 2 0 0 0 2-2V7"/><path d="M10.5 11.5v5M13.5 11.5v5"/>',
        'pencil'        => '<path d="M4 20h4L19 9a2.1 2.1 0 0 0-3-3L5 17l-1 3Z"/><path d="m15.5 6.5 3 3"/>',
        'download'      => '<path d="M12 3.5v11M8 11l4 4 4-4"/><path d="M4.5 17.5v1A2.5 2.5 0 0 0 7 21h10a2.5 2.5 0 0 0 2.5-2.5v-1"/>',
        'copy'          => '<rect x="8.5" y="8.5" width="12" height="12" rx="2"/><path d="M15.5 5.5A2 2 0 0 0 13.5 3.5h-8a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2"/>',
        'key'           => '<circle cx="8" cy="14" r="4.5"/><path d="m11.5 11 8-8M17 5.5l2 2M14.5 8l2 2"/>',
        'mail'          => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3.5 6.5 8.5 6 8.5-6"/>',
        'phone'         => '<path d="M7 3.5h3l1.5 4-2 1.5a11 11 0 0 0 5.5 5.5l1.5-2 4 1.5v3a2 2 0 0 1-2.2 2A16.5 16.5 0 0 1 3.5 5.7 2 2 0 0 1 5.5 3.5H7Z"/>',
        'dot'           => '<circle cx="12" cy="12" r="3" fill="currentColor" stroke="none"/>',
    ];

    $path = $paths[$name] ?? $paths['dot'];
@endphp

<svg {{ $attributes->merge(['class' => 'h-[1.125rem] w-[1.125rem]', 'aria-hidden' => 'true']) }}
     viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"
     stroke-linecap="round" stroke-linejoin="round">
    {!! $path !!}
</svg>
