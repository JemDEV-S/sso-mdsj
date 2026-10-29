@extends('layouts.app')
@section('title', 'Cambiar contraseña')
@section('content')
@unless(auth()->user()->must_change_password)<a class="back-link" href="{{ route('home') }}">← Mi escritorio</a>@endunless
<section class="card narrow"><span class="form-heading-icon"><x-icon name="shield" /></span><p class="eyebrow">SEGURIDAD DE LA CUENTA</p><h1>Cambiar contraseña</h1>
    @if(auth()->user()->must_change_password)<p>Antes de continuar, reemplaza tu contraseña temporal.</p>@endif
    <p class="muted">Usa entre 12 y 72 caracteres e incluye mayúsculas, minúsculas, números y símbolos.</p>
    <form method="post" action="{{ route('password.update') }}">@csrf
        <label for="current_password">Contraseña actual</label><input id="current_password" name="current_password" type="password" autocomplete="current-password" required>
        <label for="password">Nueva contraseña</label><input id="password" name="password" type="password" autocomplete="new-password" minlength="12" maxlength="72" required>
        <label for="password_confirmation">Repetir nueva contraseña</label><input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required>
        <button class="button" type="submit">Guardar contraseña</button>
    </form>
</section>
@endsection
