@php
    $isPortal = request()->routeIs('home');
    $isAdmin = request()->routeIs('admin.*');
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f7f6f2">
    <title>@yield('title', 'Identidad institucional') · SSO MDSJ</title>
    <link rel="stylesheet" href="{{ asset('css/sso.css') }}?v={{ filemtime(public_path('css/sso.css')) }}">
</head>
<body class="{{ $isPortal ? 'portal-page' : ($isAdmin ? 'admin-page' : 'account-page') }}">
<a class="skip-link" href="#main">Ir al contenido</a>
<header class="masthead">
    <a class="brand" href="{{ route('home') }}" aria-label="Municipalidad Distrital de San Jerónimo, inicio">
        <x-brand-mark />
        <span>Muni San Jerónimo</span>
    </a>
    @auth
        <form class="logout-form" action="{{ route('logout') }}" method="post">@csrf<button class="link-button" type="submit"><span>Salir</span><x-icon name="logout" /></button></form>
    @else
        <span class="service-label">IDENTIDAD INSTITUCIONAL</span>
    @endauth
</header>
<div class="app-shell">
    @if($isAdmin)
        <aside class="admin-sidebar">
            <p class="eyebrow">ADMINISTRACIÓN SSO</p>
            <nav class="navigation" aria-label="Navegación administrativa">
                @foreach(['admin.index' => ['grid', 'Resumen'], 'admin.users' => ['users', 'Usuarios'], 'admin.clients' => ['apps', 'Sistemas'], 'admin.sessions' => ['clock', 'Sesiones']] as $destination => [$icon, $label])
                    @php($active = $destination === 'admin.index' ? request()->routeIs($destination) : request()->routeIs($destination, $destination.'.*'))
                    <a href="{{ route($destination) }}" @if($active) aria-current="page" @endif><x-icon :name="$icon" />{{ $label }}</a>
                @endforeach
                <a class="back-to-portal" href="{{ route('home') }}"><x-icon name="arrow" />Mi escritorio</a>
            </nav>
            <div class="sidebar-note"><x-icon name="shield" /><span>Identidad institucional<br><strong>San Jerónimo</strong></span></div>
        </aside>
    @endif
    <main id="main" class="container" tabindex="-1">
        @if(session('status'))<div class="notice" role="status">{{ session('status') }}</div>@endif
        @if($errors->any())
            <div class="error" role="alert"><strong>Revise los datos ingresados.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
        @endif
        @yield('content')
    </main>
</div>
@unless($isPortal)
    <footer>Municipalidad Distrital de San Jerónimo <span>Plataforma de acceso institucional · SSO</span></footer>
@endunless
</body>
</html>
