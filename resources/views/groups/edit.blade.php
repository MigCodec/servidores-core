@extends('layouts.app')

@section('title', 'Editar grupo')

@section('content')
    @php
        $isProtected = $isProtected ?? false;
        $selectedUsers = old('user_ids', $group->users->pluck('id')->all());
        $selectedServers = old('server_ids', $group->servers->pluck('id')->all());
    @endphp

    @once
        @push('styles')
            <style>
                .checklist-grid {
                    display: grid;
                    gap: 0.5rem;
                    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
                }

                .checklist-item {
                    display: flex;
                    gap: 0.45rem;
                    align-items: center;
                    border: 1px solid #e5e7eb;
                    border-radius: 10px;
                    padding: 0.45rem 0.6rem;
                    background: #fff;
                }

                .checklist-item input[type='checkbox'] {
                    width: 16px;
                    height: 16px;
                }
            </style>
        @endpush
    @endonce

    <div class="card">
        <h2 class="section-title">Configurar grupo</h2>

        @if ($errors->any())
            <div class="alert alert-error">
                @foreach ($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('groups.update', $group) }}">
            @csrf
            @method('PUT')

            <div class="form-grid">
                <div>
                    <label for="name">Nombre</label>
                    <input type="text" id="name" name="name" value="{{ old('name', $group->name) }}" {{ $isProtected ? 'readonly' : 'required' }}>
                </div>
                <div>
                    <label for="slug">Slug</label>
                    <input type="text" id="slug" name="slug" value="{{ old('slug', $group->slug) }}" {{ $isProtected ? 'readonly' : 'required' }}>
                </div>
                <div>
                    <label>&nbsp;</label>
                    <div class="checkbox-row">
                        <input type="checkbox" id="is_admin" name="is_admin" value="1" {{ old('is_admin', $group->is_admin) ? 'checked' : '' }} {{ $group->is_admin || $isProtected ? 'disabled' : '' }}>
                        <label for="is_admin">Grupo de administracion</label>
                    </div>
                    <div class="muted">Los administradores pueden ver y modificar todo.</div>
                </div>
            </div>

            <div style="margin-top: 1.5rem;">
                <label>Usuarios</label>
                @if ($users->isEmpty())
                    <p class="muted">No hay usuarios disponibles.</p>
                @else
                    <div class="checklist-grid">
                        @foreach ($users as $user)
                            <label class="checklist-item">
                                <input type="checkbox" name="user_ids[]" value="{{ $user->id }}" {{ in_array($user->id, $selectedUsers) ? 'checked' : '' }}>
                                <span>{{ $user->name }} ({{ $user->email }})</span>
                            </label>
                        @endforeach
                    </div>
                    <div class="muted">
                        Selecciona quienes pertenecen a este grupo.
                        @if ($group->is_admin)
                            No te quites del grupo administrador o perderás acceso a esta sección.
                        @endif
                    </div>
                @endif
            </div>

            @if (! $group->is_admin)
                <div style="margin-top: 1.5rem;">
                    <label>Servidores asignados</label>
                    @if ($servers->isEmpty())
                        <p class="muted">No hay servidores registrados.</p>
                    @else
                        <div class="checklist-grid">
                            @foreach ($servers as $serverOption)
                                <label class="checklist-item">
                                    <input type="checkbox" name="server_ids[]" value="{{ $serverOption->id }}" {{ in_array($serverOption->id, $selectedServers) ? 'checked' : '' }}>
                                    <span>{{ $serverOption->name }} ({{ $serverOption->ip_address }})</span>
                                </label>
                            @endforeach
                        </div>
                        <div class="muted">Los ayudantes verán y modificarán solo los servidores seleccionados.</div>
                    @endif
                </div>
            @endif

            <div class="actions" style="margin-top: 1.5rem;">
                <a class="btn btn-light" href="{{ route('groups.index') }}">Cancelar</a>
                <button class="btn btn-primary" type="submit">Guardar</button>
            </div>
        </form>
    </div>
@endsection
