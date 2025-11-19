@extends('layouts.app')

@section('title', 'Nuevo grupo')

@section('content')
    <div class="card">
        <h2 class="section-title">Crear grupo</h2>

        <form method="POST" action="{{ route('groups.store') }}">
            @csrf

            <div class="form-grid">
                <div>
                    <label for="name">Nombre</label>
                    <input type="text" id="name" name="name" value="{{ old('name') }}" required>
                </div>
                <div>
                    <label for="slug">Slug</label>
                    <input type="text" id="slug" name="slug" value="{{ old('slug') }}" placeholder="Opcional; se generará automáticamente.">
                </div>
                <div>
                    <label>&nbsp;</label>
                    <div class="checkbox-row">
                        <input type="checkbox" id="is_admin" name="is_admin" value="1" {{ old('is_admin') ? 'checked' : '' }}>
                        <label for="is_admin">Grupo de administración</label>
                    </div>
                    <div class="muted">Los administradores pueden ver y modificar todo.</div>
                </div>
            </div>

            <div style="margin-top: 1.5rem;">
                <label for="user_ids">Usuarios</label>
                <select name="user_ids[]" id="user_ids" multiple size="6">
                    @foreach ($users as $user)
                        <option value="{{ $user->id }}" @selected(in_array($user->id, old('user_ids', [])))>
                            {{ $user->name }} ({{ $user->email }})
                        </option>
                    @endforeach
                </select>
                <div class="muted">Selecciona quienes pertenecen a este grupo.</div>
            </div>

            <div style="margin-top: 1.5rem;">
                <label for="server_ids">Servidores asignados</label>
                <select name="server_ids[]" id="server_ids" multiple size="8">
                    @foreach ($servers as $serverOption)
                        <option value="{{ $serverOption->id }}" @selected(in_array($serverOption->id, old('server_ids', [])))>
                            {{ $serverOption->name }} ({{ $serverOption->ip_address }})
                        </option>
                    @endforeach
                </select>
                <div class="muted">Los ayudantes verán y editarán solo los servidores seleccionados. Ignorado si es grupo admin.</div>
            </div>

            <div class="actions" style="margin-top: 1.5rem;">
                <a class="btn btn-light" href="{{ route('groups.index') }}">Cancelar</a>
                <button class="btn btn-primary" type="submit">Crear</button>
            </div>
        </form>
    </div>
@endsection
