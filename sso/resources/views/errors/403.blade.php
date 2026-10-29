@extends('layouts.app')
@section('title', 'Acceso restringido')
@section('content')
<section class="card narrow"><p class="eyebrow">ACCESO RESTRINGIDO</p><h1>No tienes permiso para acceder</h1><p>Esta sección está reservada a administradores del SSO.</p><a class="button" href="{{ route('home') }}">Volver a mi cuenta</a></section>
@endsection
