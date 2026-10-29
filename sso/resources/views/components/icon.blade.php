@props(['name' => 'apps'])
<svg {{ $attributes->class(['icon']) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
    @switch($name)
        @case('document')
            <path d="M6 2h8l4 5v15H6zM14 2v6h4M9 13h6M9 17h6" />
            @break
        @case('inbox')
            <path d="m3 13 3-9h12l3 9v7H3zM3 13h6l1 3h4l1-3h6" />
            @break
        @case('users')
            <circle cx="9" cy="7" r="3.5" /><path d="M2 21v-1a7 7 0 0 1 14 0v1M17 3.5a3.5 3.5 0 0 1 0 7M19 15a7 7 0 0 1 3 6" />
            @break
        @case('building')
            <path d="m2 9 10-7 10 7M5 8v14M19 8v14M3 22h18M9 22v-9h6v9" />
            @break
        @case('key')
            <circle cx="8" cy="8" r="5" /><path d="m11.5 11.5 9 9M16 16l3-3M19 19l3-3" />
            @break
        @case('logout')
            <path d="M9 3H4v18h5M9 12h12m-4-4 4 4-4 4" />
            @break
        @case('grid')
            <rect x="3" y="3" width="7" height="7" rx="2" /><rect x="14" y="3" width="7" height="7" rx="2" /><rect x="3" y="14" width="7" height="7" rx="2" /><rect x="14" y="14" width="7" height="7" rx="2" />
            @break
        @case('shield')
            <path d="m12 2 8 3v6c0 5-4 8-8 11-4-3-8-6-8-11V5zM8 12l3 3 5-6" />
            @break
        @case('clock')
            <circle cx="12" cy="12" r="9" /><path d="M12 7v5l3 2" />
            @break
        @case('arrow')
            <path d="M4 12h16m-6-6 6 6-6 6" />
            @break
        @default
            <rect x="3" y="3" width="7" height="7" rx="2" /><rect x="14" y="3" width="7" height="7" rx="2" /><rect x="3" y="14" width="7" height="7" rx="2" /><path d="M17.5 14v7M14 17.5h7" />
    @endswitch
</svg>
