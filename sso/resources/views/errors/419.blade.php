@extends('layouts.app')
@section('title', 'Formulario vencido')
@section('content')
<section class="card narrow"><h1>El formulario ha vencido</h1><p>Vuelve a cargar la página e intenta nuevamente.</p><a class="button" href="{{ route('home') }}">Continuar</a></section>
@endsection
