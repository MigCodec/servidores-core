@extends('layouts.app')

@section('title', 'Servidores')

@section('content')
    <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: center; gap: 1rem; flex-wrap: wrap;">
            <div>
                <h2 class="section-title">Servidores registrados</h2>
                <p class="muted">Gestiona tus servidores fisicos y virtuales.</p>
            </div>
            @can('create', App\Models\Server::class)
                <a class="btn btn-primary" href="{{ route('servers.create') }}">Nuevo servidor</a>
            @endcan
        </div>

        <form method="GET" action="{{ route('servers.index') }}" style="margin: 1rem 0; display: flex; gap: 1rem; flex-wrap: wrap;">
            <input type="text" name="search" placeholder="Buscar por nombre o IP" value="{{ $filters['search'] ?? '' }}">
            <select name="type">
                <option value="">Todos</option>
                <option value="physical" @selected(($filters['type'] ?? '') === 'physical')>Fisicos</option>
                <option value="virtual" @selected(($filters['type'] ?? '') === 'virtual')>Virtuales</option>
            </select>
            <button class="btn btn-secondary" type="submit">Filtrar</button>
        </form>

        @if ($servers->isEmpty())
            <p class="muted">No hay servidores disponibles.</p>
        @else
            <div class="table-wrapper">
                <table class="table">
                    <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>IP</th>
                        <th>Tipo</th>
                        <th>RAM</th>
                        <th>Almacenamiento</th>
                        <th>Servicios</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($servers as $server)
                        <tr>
                            <td>
                                <div>{{ $server->name }}</div>
                                @if ($server->parent)
                                    <div class="muted">Host: {{ $server->parent->name }}</div>
                                @endif
                            </td>
                            <td>{{ $server->ip_address }}</td>
                            <td>
                                <span class="badge {{ $server->is_physical ? 'badge-success' : 'badge-warning' }}">
                                    {{ $server->is_physical ? 'Fisico' : 'Virtual' }}
                                </span>
                            </td>
                            <td>{{ $server->ram_gb }} GB</td>
                            <td>{{ $server->storage_gb }} GB</td>
                            <td>{{ $server->services_count }}</td>
                            <td class="actions">
                                <a class="btn btn-light" href="{{ route('servers.show', $server) }}">Ver</a>
                                @can('update', $server)
                                    <a class="btn btn-secondary" href="{{ route('servers.edit', $server) }}">Editar</a>
                                @endcan
                                @can('delete', $server)
                                    <form method="POST" action="{{ route('servers.destroy', $server) }}" onsubmit="return confirm('Eliminar servidor?');">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-danger" type="submit">Eliminar</button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>

            <div class="pagination-container">
                {{ $servers->links() }}
            </div>
        @endif
    </div>
@endsection
