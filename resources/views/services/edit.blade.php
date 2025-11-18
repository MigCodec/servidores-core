@extends('layouts.app')

@section('title', 'Editar servicio')

@section('content')
    <div class="card">
        <h2 class="section-title">Editar servicio de {{ $service->server->name }}</h2>

        <form method="POST" action="{{ route('services.update', $service) }}">
            @csrf
            @method('PUT')
            <div class="form-grid">
                <div>
                    <label for="name">Nombre</label>
                    <input type="text" id="name" name="name" value="{{ old('name', $service->name) }}" required>
                    @error('name')
                        <div class="muted">{{ $message }}</div>
                    @enderror
                </div>
                <div>
                    <label for="url">URL</label>
                    <input type="text" id="url" name="url" value="{{ old('url', $service->url) }}">
                    @error('url')
                        <div class="muted">{{ $message }}</div>
                    @enderror
                </div>
                <div>
                    <label for="port">Puerto</label>
                    <input type="number" id="port" name="port" min="1" max="65535" value="{{ old('port', $service->port) }}" required>
                    @error('port')
                        <div class="muted">{{ $message }}</div>
                    @enderror
                </div>
                <div>
                    <label for="username">Usuario</label>
                    <input type="text" id="username" name="username" value="{{ old('username', $service->username) }}" required>
                    @error('username')
                        <div class="muted">{{ $message }}</div>
                    @enderror
                </div>
                <div>
                    <label for="password">Contrasena (dejar vacio para no cambiar)</label>
                    <input type="text" id="password" name="password" value="">
                    @error('password')
                        <div class="muted">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="actions" style="margin-top: 1.5rem;">
                <a class="btn btn-light" href="{{ route('servers.show', $service->server) }}">Cancelar</a>
                <button class="btn btn-primary" type="submit">Guardar cambios</button>
            </div>
        </form>
    </div>
@endsection
