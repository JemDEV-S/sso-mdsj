@extends('layouts.app')
@section('title', 'Sesiones centrales')
@section('content')
<p class="eyebrow">CONTROL DE ACCESO</p><h1>Sesiones centrales</h1><p class="muted">Revocar una sesión impide seguir usándola en el SSO. El cierre de sesiones locales de otros sistemas corresponde a una fase posterior.</p>
<section class="card"><div class="table-wrap"><table><thead><tr><th>Usuario</th><th>IP</th><th>Última actividad (UTC)</th><th>Estado</th><th>Acción</th></tr></thead><tbody>
@forelse($sessions as $session)
@php($active = !$session->revoked_at && now()->lt($session->expires_at) && now()->subMinutes(config('session.lifetime'))->lt($session->last_activity_at))
<tr><td>{{ $session->nombres }} {{ $session->apellidos }}</td><td>{{ $session->ip_address }}</td><td>{{ $session->last_activity_at }}</td><td>{{ $session->revoked_at ? 'Revocada' : ($active ? 'Vigente' : 'Expirada') }}</td><td>@if($active)<form method="post" action="{{ route('admin.sessions.revoke', $session->id) }}">@csrf @method('DELETE')<button class="button danger" type="submit">Revocar</button></form>@endif</td></tr>
@empty<tr><td colspan="5">No hay sesiones registradas.</td></tr>@endforelse
</tbody></table></div>{{ $sessions->links('partials.pagination') }}</section>
@endsection
