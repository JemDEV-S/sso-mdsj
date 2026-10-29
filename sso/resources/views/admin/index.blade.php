@extends('layouts.app')
@section('title', 'Administración')
@section('content')
<p class="eyebrow">CONSOLA ADMINISTRATIVA</p><h1>Identidad y acceso</h1><p class="muted">Administra las cuentas y los sistemas institucionales de la Municipalidad.</p>
<div class="stats"><a class="card" href="{{ route('admin.users') }}"><span class="stat-icon"><x-icon name="users" /></span><strong>{{ $users }}</strong>Usuarios registrados</a><a class="card" href="{{ route('admin.clients') }}"><span class="stat-icon"><x-icon name="apps" /></span><strong>{{ $clients }}</strong>Sistemas activos</a><a class="card" href="{{ route('admin.sessions') }}"><span class="stat-icon"><x-icon name="clock" /></span><strong>{{ $sessions }}</strong>Sesiones vigentes</a></div>
<section class="card"><h2>Actividad reciente</h2><div class="table-wrap"><table><thead><tr><th>Fecha (UTC)</th><th>Evento</th><th>Usuario / actor</th><th>IP</th></tr></thead><tbody>
@forelse($events as $event)<tr><td>{{ $event->created_at }}</td><td>{{ $event->event }}</td><td>{{ $event->user_id ?? '—' }} / {{ $event->actor_id ?? '—' }}</td><td>{{ $event->ip_address }}</td></tr>@empty<tr><td colspan="4">Aún no hay eventos.</td></tr>@endforelse
</tbody></table></div></section>
@endsection
