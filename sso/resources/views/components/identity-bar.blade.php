@props(['account'])
@php($initials = mb_strtoupper(mb_substr($account->nombres, 0, 1).mb_substr($account->apellidos, 0, 1)))
<section class="identity-bar" aria-label="Datos de tu cuenta">
    <span class="avatar" aria-hidden="true">{{ $initials }}</span>
    <div class="identity-details">
        <h2>{{ $account->name }}</h2>
        <p>DNI {{ $account->dni }} <span aria-hidden="true">·</span> {{ $account->email ?? 'Correo no registrado' }}</p>
    </div>
    <div class="identity-actions">
        <a class="button secondary" href="{{ route('password.edit') }}">Cambiar contraseña</a>
        @if($account->is_admin)<a class="button" href="{{ route('admin.index') }}" aria-label="Administrar SSO">Administrar</a>@endif
    </div>
</section>
