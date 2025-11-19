@extends('layouts.app')

@section('title', 'Editar grupo')

@section('content')
    @php $isProtected = $isProtected ?? false; @endphp
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
                <label for="user_ids">Usuarios</label>
                <select name="user_ids[]" id="user_ids" multiple size="6">
                    @foreach ($users as $user)
                        <option value="{{ $user->id }}" @selected(in_array($user->id, old('user_ids', $group->users->pluck('id')->all())))>{{ $user->name }} ({{ $user->email }})</option>
                    @endforeach
                </select>
                <div class="muted">Selecciona quienes pertenecen a este grupo.</div>
            </div>

            @if (! $group->is_admin)
                <div style="margin-top: 1.5rem;">
                    <label for="server_ids">Servidores asignados</label>
                    <select name="server_ids[]" id="server_ids" multiple size="8">
                        @foreach ($servers as $serverOption)
                            <option value="{{ $serverOption->id }}" @selected(in_array($serverOption->id, old('server_ids', $group->servers->pluck('id')->all())))>
                                {{ $serverOption->name }} ({{ $serverOption->ip_address }})
                            </option>
                        @endforeach
                    </select>
                    <div class="muted">Los ayudantes veran y modificaran solo los servidores seleccionados.</div>
                </div>
            @endif

            <div class="actions" style="margin-top: 1.5rem;">
                <a class="btn btn-light" href="{{ route('groups.index') }}">Cancelar</a>
                <button class="btn btn-primary" type="submit">Guardar</button>
            </div>
        </form>
    </div>
@endsection
