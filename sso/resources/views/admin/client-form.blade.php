@extends('layouts.app')
@section('title', 'Sistema cliente')
@section('content')
<a href="{{ route('admin.clients') }}">← Sistemas</a><section class="card narrow"><h1>{{ $client->exists ? 'Editar sistema' : 'Registrar sistema' }}</h1>
<form method="post" action="{{ $client->exists ? route('admin.clients.update', $client) : route('admin.clients.store') }}">@csrf @if($client->exists)@method('PUT')@endif
    <label for="name">Nombre del sistema</label><input id="name" name="name" value="{{ old('name', $client->name) }}" maxlength="150" required>
    <label for="code">Código institucional</label><input id="code" name="code" value="{{ old('code', $client->code) }}" placeholder="sistema-piloto" maxlength="80" required>
    <label for="redirect_uris">URL de retorno autorizadas</label><textarea id="redirect_uris" name="redirect_uris" rows="5" required>{{ old('redirect_uris', $client->exists ? $client->redirectUris->pluck('redirect_uri')->implode("\n") : '') }}</textarea>
    <p class="small muted">Una URL HTTPS por línea. Se exige coincidencia exacta. Máximo 10 URL, sin comodines ni fragmentos.</p>
    <label for="active">Estado</label><select id="active" name="active"><option value="1" @selected(old('active', $client->active ?? true))>Activo</option><option value="0" @selected(!old('active', $client->active ?? true))>Inactivo</option></select>
    <button class="button" type="submit">Guardar sistema</button>
</form></section>
@endsection
