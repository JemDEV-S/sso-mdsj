@extends('layouts.app')
@section('title', 'Iniciar sesión')
@section('content')
<div class="login-grid">
    <section class="intro">
        <p class="eyebrow">UN SOLO ACCESO</p>
        <h1>Tu identidad.<br><span>Todos tus sistemas.</span></h1>
        <p>Accede a los sistemas de la Municipalidad Distrital de San Jerónimo con tu cuenta institucional.</p>
        <x-access-illustration />
        <div class="intro-note"><strong>Acceso para personal autorizado</strong><p>Si necesitas una cuenta o recuperar tu acceso, comunícate con el área de soporte de la Municipalidad.</p></div>
    </section>
    <section class="card login-card">
        <span class="form-heading-icon"><x-icon name="key" /></span>
        <p class="eyebrow">BIENVENIDO</p><h2>Iniciar sesión</h2>
        <p class="muted">Ingresa tu DNI y contraseña institucional.</p>
        <form action="{{ route('login') }}" method="post">
            @csrf
            <label for="dni">DNI</label><input id="dni" name="dni" value="{{ old('dni') }}" inputmode="numeric" pattern="[0-9]{8}" minlength="8" maxlength="8" autocomplete="username" placeholder="8 dígitos" required autofocus>
            <label for="password">Contraseña</label><input id="password" name="password" type="password" autocomplete="current-password" required>
            <button class="button full" type="submit">Ingresar</button>
        </form>
        <p class="small muted">Por seguridad, cierra tu sesión al terminar de usar un equipo compartido.</p>
    </section>
</div>
@endsection
