@extends('layouts.app')
@section('title', $account->exists ? 'Editar usuario' : 'Crear usuario')
@section('content')
<a href="{{ route('admin.users') }}">← Usuarios</a><section class="card narrow"><h1>{{ $account->exists ? 'Editar usuario' : 'Crear usuario' }}</h1>
<form method="post" action="{{ $account->exists ? route('admin.users.update', $account) : route('admin.users.store') }}">@csrf @if($account->exists)@method('PUT')@endif
    <label for="dni">DNI</label><input id="dni" name="dni" value="{{ old('dni', $account->dni) }}" pattern="[0-9]{8}" maxlength="8" inputmode="numeric" required>
    <label for="nombres">Nombres</label><input id="nombres" name="nombres" value="{{ old('nombres', $account->nombres) }}" maxlength="150" required>
    <label for="apellidos">Apellidos</label><input id="apellidos" name="apellidos" value="{{ old('apellidos', $account->apellidos) }}" maxlength="150" required>
    <label for="email">Correo (opcional)</label><input id="email" name="email" type="email" value="{{ old('email', $account->email) }}" maxlength="255">
    <label for="active">Estado</label><select id="active" name="active"><option value="1" @selected(old('active', $account->active ?? true))>Activo</option><option value="0" @selected(!old('active', $account->active ?? true))>Bloqueado</option></select>
    <label for="password">Contraseña temporal {{ $account->exists ? '(solo para restablecer)' : '' }}</label><input id="password" name="password" type="password" autocomplete="new-password" minlength="12" maxlength="72" @required(!$account->exists)>
    <p class="small muted">Mínimo 12 caracteres, mayúscula, minúscula, número y símbolo. El usuario deberá cambiarla al ingresar. Restablecerla o bloquear la cuenta revoca sus sesiones centrales.</p>
    <label for="password_confirmation">Repetir contraseña temporal</label><input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" @required(!$account->exists)>
    <button class="button" type="submit">Guardar usuario</button>
</form></section>
@endsection
