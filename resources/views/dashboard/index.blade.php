@extends('layouts.app')

@section('title', 'Dashboard')

@push('styles')
    <style>
        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .stat-grid {
            margin-top: 1.5rem;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 1rem;
        }

        .stat-card {
            border-radius: 12px;
            padding: 1rem 1.25rem;
            background: #f3f4f6;
        }

        .stat-label {
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #6b7280;
        }

        .stat-value {
            font-size: 1.8rem;
            font-weight: 600;
        }

        .stat-up {
            background: #dcfce7;
            color: #166534;
        }

        .stat-down {
            background: #fee2e2;
            color: #991b1b;
        }

        .server-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1rem;
        }

        .server-card {
            border-radius: 14px;
            padding: 1rem;
            border: 1px solid #e5e7eb;
            background: #fff;
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }

        .server-card.status-up {
            border-color: #bbf7d0;
            background: #f6fef9;
        }

        .server-card.status-down {
            border-color: #fecaca;
            background: #fff7f7;
        }

        .server-card-header {
            display: flex;
            justify-content: space-between;
            gap: 0.5rem;
            align-items: center;
        }

        .status-pill {
            border-radius: 999px;
            padding: 0.1rem 0.75rem;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .status-pill.up {
            background: #bbf7d0;
            color: #166534;
        }

        .status-pill.down {
            background: #fecaca;
            color: #991b1b;
        }

        .metric-row {
            display: flex;
            justify-content: space-between;
            font-size: 0.9rem;
        }

        .ssh-status {
            font-size: 0.85rem;
            color: #6b7280;
        }
    </style>
@endpush

@section('content')
    <div class="card">
        <div class="dashboard-header">
            <div>
                <h2 class="section-title" style="margin: 0;">Panel de salud</h2>
                <p class="muted" style="margin: 0;">Última actualización:
                    {{ $generatedAt ? $generatedAt->diffForHumans() : 'sin datos' }}</p>
            </div>
            <form method="POST" action="{{ route('dashboard.refresh') }}">
                @csrf
                <button class="btn btn-primary" type="submit">Actualizar ahora</button>
            </form>
        </div>

        <div class="stat-grid">
            <div class="stat-card stat-up">
                <div class="stat-label">Físicos en línea</div>
                <div class="stat-value">{{ $summary['physical']['up'] }}</div>
            </div>
            <div class="stat-card stat-down">
                <div class="stat-label">Físicos sin respuesta</div>
                <div class="stat-value">{{ $summary['physical']['down'] }}</div>
            </div>
            <div class="stat-card stat-up">
                <div class="stat-label">Virtuales en línea</div>
                <div class="stat-value">{{ $summary['virtual']['up'] }}</div>
            </div>
            <div class="stat-card stat-down">
                <div class="stat-label">Virtuales sin respuesta</div>
                <div class="stat-value">{{ $summary['virtual']['down'] }}</div>
            </div>
        </div>
    </div>

    <div class="card">
        <h2 class="section-title">Servidores</h2>
        <div class="server-grid">
            @forelse ($servers as $server)
                @php
                    $data = $health[$server->id] ?? null;
                    $isUp = ($data['status'] ?? 'down') === 'up';
                    $ssh = $data['ssh'] ?? null;
                @endphp
                @php
                    $logs = ($history->get($server->id) ?? collect())->take(5);
                    $servicesStatus = $ssh['services'] ?? ($logs->first()->services_status ?? null);
                @endphp
                <div class="server-card {{ $isUp ? 'status-up' : 'status-down' }}">
                    <div class="server-card-header">
                        <div>
                            <strong>{{ $server->name }}</strong>
                            <div class="muted" style="font-size: 0.85rem;">
                                {{ $server->ip_address }} · {{ $server->is_physical ? 'Físico' : 'Virtual' }}
                            </div>
                            <div class="muted" style="font-size: 0.8rem;">
                                {{ $server->environment ?? 'Ambiente N/D' }} · {{ $server->location ?? 'Ubicación N/D' }} · Responsable: {{ $server->owner ?? 'N/D' }}
                            </div>
                            <div class="muted" style="font-size: 0.75rem;">
                                {{ $server->os_name ?? 'SO N/D' }} {{ $server->os_version }} {{ $server->kernel_version ? '· Kernel '.$server->kernel_version : '' }} {{ $server->cpu_cores ? '· '.$server->cpu_cores.' cores' : '' }}
                            </div>
                        </div>
                        <span class="status-pill {{ $isUp ? 'up' : 'down' }}">
                            {{ $isUp ? 'Encendido' : 'Apagado' }}
                        </span>
                    </div>
                    <div class="metric-row">
                        <span>Latencia</span>
                        <span>{{ $data && $data['latency_ms'] ? $data['latency_ms'] . ' ms' : 'Sin datos' }}</span>
                    </div>
                    <div class="metric-row">
                        <span>RAM</span>
                        <span>
                            @if ($ssh && $ssh['connected'] && $ssh['ram'])
                                {{ $ssh['ram']['used_mb'] }} / {{ $ssh['ram']['total_mb'] }} MB
                                ({{ $ssh['ram']['usage_percent'] }}%)
                            @else
                                {{ $ssh && $ssh['connected'] ? 'Sin datos' : 'Sin SSH' }}
                            @endif
                        </span>
                    </div>
                    <div class="metric-row">
                        <span>CPU (load)</span>
                        <span>
                            @if ($ssh && $ssh['connected'] && $ssh['cpu'])
                                {{ $ssh['cpu']['load1'] }}, {{ $ssh['cpu']['load5'] }}, {{ $ssh['cpu']['load15'] }}
                            @else
                                {{ $ssh && $ssh['connected'] ? 'Sin datos' : 'Sin SSH' }}
                            @endif
                        </span>
                    </div>
                    @if ($ssh && $ssh['error'] && ! $ssh['connected'])
                        <div class="ssh-status">{{ $ssh['error'] }}</div>
                    @endif
                    @if ($servicesStatus)
                        <div>
                            <strong style="font-size: 0.85rem;">Servicios críticos</strong>
                            <ul style="margin: 0.35rem 0 0; padding-left: 1rem; font-size: 0.85rem;">
                                @foreach ($servicesStatus as $service => $status)
                                    <li>
                                        {{ $service }}:
                                        <span style="font-weight: 600; color: {{ $status === 'active' ? '#166534' : '#991b1b' }};">
                                            {{ $status }}
                                        </span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                    @if ($logs->isNotEmpty())
                        <div>
                            <strong style="font-size: 0.85rem;">Historial reciente</strong>
                            <div style="display: flex; gap: 0.4rem; flex-wrap: wrap; margin-top: 0.4rem;">
                                @foreach ($logs as $log)
                                    @php $up = $log->status === 'up'; @endphp
                                    <span class="status-pill {{ $up ? 'up' : 'down' }}" style="font-size: 0.75rem;">
                                        {{ $log->created_at->diffForHumans(null, null, true) }}
                                    </span>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            @empty
                <p class="muted">No hay servidores disponibles.</p>
            @endforelse
        </div>
    </div>
@endsection
