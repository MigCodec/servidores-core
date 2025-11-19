@extends('layouts.app')

@section('title', 'Servidor '.$server->name)

@section('content')
    <div class="card">
        <div style="display: flex; justify-content: space-between; gap: 1rem; flex-wrap: wrap;">
            <div>
                <h2 class="section-title">{{ $server->name }}</h2>
                <p class="muted">IP {{ $server->ip_address }}</p>
                @if ($server->in_maintenance)
                    <span class="badge" style="background:#bfdbfe;color:#1d4ed8;">En mantenimiento</span>
                @endif
            </div>
            <div class="actions">
                <a class="btn btn-light" href="{{ route('servers.index') }}">Volver</a>
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
            </div>
        </div>

        <div class="form-grid" style="margin-top: 1rem;">
            <div>
                <label>Tipo</label>
                <div>
                    <span class="badge {{ $server->is_physical ? 'badge-success' : 'badge-warning' }}">
                        {{ $server->is_physical ? 'Fisico' : 'Virtual' }}
                    </span>
                </div>
            </div>
            <div>
                <label>RAM</label>
                <div>{{ $server->ram_gb }} GB</div>
            </div>
            <div>
                <label>Almacenamiento</label>
                <div>{{ $server->storage_gb }} GB</div>
            </div>
            <div>
                <label>Host</label>
                <div>
                    @if ($server->parent)
                        {{ $server->parent->name }} ({{ $server->parent->ip_address }})
                    @else
                        <span class="muted">No aplica</span>
                    @endif
                </div>
            </div>
        </div>

        <div style="margin-top: 1.5rem;">
            <label>Grupos con acceso</label>
            @if ($groups->isEmpty())
                <p class="muted">Sin ayudantes asignados.</p>
            @else
                <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                    @foreach ($groups as $group)
                        <span class="badge">{{ $group->name }}</span>
                    @endforeach
                </div>
            @endif
        </div>

        @if ($server->is_physical)
            <div style="margin-top: 1.5rem;">
                <label>Maquinas virtuales</label>
                @if ($server->virtualMachines->isEmpty())
                    <p class="muted">No hay maquinas registradas.</p>
                @else
                    <ul>
                        @foreach ($server->virtualMachines as $vm)
                            <li>{{ $vm->name }} ({{ $vm->ip_address }})</li>
                        @endforeach
                    </ul>
                @endif
            </div>
        @endif
    </div>

    <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: center; gap: 1rem; flex-wrap: wrap;">
            <div>
                <h3 class="section-title">Servicios</h3>
                <p class="muted">Credenciales y puertos de este servidor.</p>
            </div>
            @can('create', [App\Models\Service::class, $server])
                <a class="btn btn-primary" href="{{ route('servers.services.create', $server) }}">Agregar servicio</a>
            @endcan
        </div>

        @if ($server->services->isEmpty())
            <p class="muted">No hay servicios registrados.</p>
        @else
            <div class="table-wrapper">
                <table class="table">
                    <thead>
                    <tr>
                        <th>Servicio</th>
                        <th>URL</th>
                        <th>Puerto</th>
                        <th>Usuario</th>
                        <th>Contrasena</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($server->services as $service)
                        <tr>
                            <td>{{ $service->name }}</td>
                            <td>
                                @if ($service->url)
                                    <a href="{{ $service->url }}" target="_blank" rel="noreferrer">{{ $service->url }}</a>
                                @else
                                    <span class="muted">No definida</span>
                                @endif
                            </td>
                            <td>{{ $service->port }}</td>
                            <td>{{ $service->username }}</td>
                            <td>{{ $service->password }}</td>
                            <td class="actions">
                                @can('update', $service)
                                    <a class="btn btn-secondary" href="{{ route('services.edit', $service) }}">Editar</a>
                                @endcan
                                @can('delete', $service)
                                    <form method="POST" action="{{ route('services.destroy', $service) }}" onsubmit="return confirm('Eliminar servicio?');">
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
        @endif
    </div>

    <div class="card">
        <h3 class="section-title">Historial de disponibilidad</h3>
        @php
            $logs = $server->healthLogs ?? collect();
        @endphp
        @if ($logs->isEmpty())
            <p class="muted">No hay registros recientes.</p>
        @else
            <div class="table-wrapper">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Estado</th>
                            <th>Latencia</th>
                            <th>RAM</th>
                            <th>CPU (load1)</th>
                            <th>Servicios críticos</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($logs as $log)
                            <tr>
                                <td>{{ $log->created_at->toDayDateTimeString() }}</td>
                                <td>
                                    <span class="status-pill {{ $log->status === 'up' ? 'up' : 'down' }}">
                                        {{ $log->status === 'up' ? 'Encendido' : 'Apagado' }}
                                    </span>
                                </td>
                                <td>{{ $log->latency_ms ? $log->latency_ms.' ms' : 'N/D' }}</td>
                                <td>{{ $log->ram_usage_percent ? $log->ram_usage_percent.'%' : 'N/D' }}</td>
                                <td>{{ $log->cpu_load1 ?? 'N/D' }}</td>
                                <td>
                                    @if ($log->services_status)
                                        <ul style="margin: 0; padding-left: 1rem;">
                                            @foreach ($log->services_status as $service => $status)
                                                <li>
                                                    {{ $service }}:
                                                    <strong style="color: {{ $status === 'active' ? '#166534' : '#991b1b' }};">
                                                        {{ $status }}
                                                    </strong>
                                                </li>
                                            @endforeach
                                        </ul>
                                    @else
                                        <span class="muted">N/D</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endsection
