@extends('layouts.app')
@section('title', 'Sistemas')
@section('content')
<div class="page-heading"><div><p class="eyebrow">INTEGRACIONES</p><h1>Sistemas clientes</h1></div><a class="button" href="{{ route('admin.clients.create') }}">Registrar sistema</a></div>
<p class="muted">Registra únicamente sistemas institucionales autorizados para recibir la identidad de los usuarios.</p>
<section class="card"><div class="table-wrap"><table><thead><tr><th>Sistema</th><th>Identificador de cliente</th><th>Estado</th><th>Acción</th></tr></thead><tbody>
@forelse($clients as $client)<tr><td>{{ $client->name }}<small>{{ $client->code }}</small></td><td><code>{{ $client->client_id }}</code></td><td><span class="badge {{ $client->active ? '' : 'blocked' }}">{{ $client->active ? 'Activo' : 'Inactivo' }}</span></td><td><a href="{{ route('admin.clients.edit', $client) }}">Editar</a></td></tr>@empty<tr><td colspan="4">Todavía no hay sistemas registrados.</td></tr>@endforelse
</tbody></table></div>{{ $clients->links('partials.pagination') }}</section>
@endsection
