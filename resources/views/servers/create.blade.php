@extends('layouts.app')

@section('title', 'Nuevo servidor')

@section('content')
    <div class="card">
        <h2 class="section-title">Registrar servidor</h2>
        <form method="POST" action="{{ route('servers.store') }}">
            @csrf

            @include('servers.form')

            <div class="actions" style="margin-top: 1.5rem;">
                <a class="btn btn-light" href="{{ route('servers.index') }}">Cancelar</a>
                <button class="btn btn-primary" type="submit">Guardar</button>
            </div>
        </form>
    </div>
@endsection
