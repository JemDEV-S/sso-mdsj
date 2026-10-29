@extends('layouts.app')
@section('title', 'Sistema registrado')
@section('content')
<section class="card narrow"><p class="eyebrow">REGISTRO COMPLETADO</p><h1>{{ $client->name }}</h1><div class="notice">Guarda el secreto en el gestor de credenciales del sistema. Se muestra únicamente en esta respuesta.</div>
<dl><dt>Client ID</dt><dd><code>{{ $client->client_id }}</code></dd><dt>Client secret</dt><dd><code>{{ $secret }}</code></dd></dl><p>Configura estas credenciales en el servidor del sistema cliente.</p><a class="button" href="{{ route('admin.clients') }}">Volver a sistemas</a></section>
@endsection
