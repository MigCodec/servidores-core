@extends('layouts.app')

@section('title', 'Editar servidor')

@section('content')
    <div class="card">
        <h2 class="section-title">Editar servidor</h2>
        <form method="POST" action="{{ route('servers.update', $server) }}">
            @csrf
            @method('PUT')

            @include('servers.form')

            <div class="actions" style="margin-top: 1.5rem;">
                <a class="btn btn-light" href="{{ route('servers.show', $server) }}">Cancelar</a>
                <button class="btn btn-primary" type="submit">Actualizar</button>
            </div>
        </form>
    </div>
@endsection
