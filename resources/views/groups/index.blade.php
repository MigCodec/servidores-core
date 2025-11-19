@extends('layouts.app')

@section('title', 'Grupos')

@section('content')
    <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
            <div>
                <h2 class="section-title">Grupos de acceso</h2>
                <p class="muted">Asigna servidores a los ayudantes mediante grupos.</p>
            </div>
            <div>
                <a class="btn btn-primary" href="{{ route('groups.create') }}">Nuevo grupo</a>
            </div>
        </div>

        @if ($groups->isEmpty())
            <p class="muted">Aun no hay grupos.</p>
        @else
            <div class="table-wrapper">
                <table class="table">
                    <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Slug</th>
                        <th>Tipo</th>
                        <th>Usuarios</th>
                        <th>Servidores</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($groups as $group)
                        <tr>
                            <td>{{ $group->name }}</td>
                            <td>{{ $group->slug }}</td>
                            <td>
                                <span class="badge {{ $group->is_admin ? 'badge-success' : '' }}">
                                    {{ $group->is_admin ? 'Administradores' : 'Ayudantes' }}
                                </span>
                            </td>
                            <td>{{ $group->users_count }}</td>
                            <td>{{ $group->is_admin ? 'Todos' : $group->servers_count }}</td>
                            <td>
                                <a class="btn btn-secondary" href="{{ route('groups.edit', $group) }}">Configurar</a>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endsection
