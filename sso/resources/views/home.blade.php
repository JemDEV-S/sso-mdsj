@extends('layouts.app')
@section('title', 'Mi cuenta')
@section('content')
@php
    $account = auth()->user();
    $hour = now('America/Lima')->hour;
    $greeting = $hour < 12 ? 'Buenos días' : ($hour < 19 ? 'Buenas tardes' : 'Buenas noches');
@endphp
<div class="desktop">
    <section class="launcher" aria-labelledby="welcome-title">
        <h1 id="welcome-title">{{ $greeting }}, <span>{{ $account->nombres }}</span></h1>
        <h2 class="sr-only">Tus sistemas</h2>
        @if($systems->isEmpty())
            <div class="systems-empty">
                <span class="empty-icon"><x-icon name="apps" /></span>
                <h2>Tu espacio está listo</h2>
                <p>No tienes sistemas disponibles por ahora.</p>
                <p class="small">Cuando se habilite un sistema, lo encontrarás aquí.</p>
            </div>
        @else
            <div class="systems-grid">
                @foreach($systems as $system)
                    <x-system-tile :system="$system" :index="$loop->index" />
                @endforeach
            </div>
        @endif
    </section>
    <x-identity-bar :account="$account" />
</div>
@endsection
