@extends('layouts.app')
@section('title', 'Usuarios')
@section('content')
<div class="page-heading"><div><p class="eyebrow">ADMINISTRACIÓN</p><h1>Usuarios</h1></div><a class="button" href="{{ route('admin.users.create') }}">Crear usuario</a></div>
<form class="search" method="get"><label for="q">Buscar por DNI o nombre</label><div><input id="q" name="q" value="{{ request('q') }}" maxlength="100"><button class="button secondary">Buscar</button></div></form>
<section class="card"><div class="table-wrap"><table><thead><tr><th>Persona</th><th>DNI</th><th>Estado</th><th>Origen</th><th>Acción</th></tr></thead><tbody>
@forelse($users as $account)<tr><td>{{ $account->name }} @if($account->is_admin)<span class="badge">Administrador</span>@endif</td><td>{{ $account->dni }}</td><td><span class="badge {{ $account->active ? '' : 'blocked' }}">{{ $account->active ? 'Activo' : 'Bloqueado' }}</span></td><td>{{ $account->source }}</td><td><a href="{{ route('admin.users.edit', $account) }}">Editar</a></td></tr>@empty<tr><td colspan="5">No se encontraron usuarios.</td></tr>@endforelse
</tbody></table></div>{{ $users->links('partials.pagination') }}</section>
@endsection
