@extends('layouts.app')

@section('title', 'Servidor '.$server->name)

@push('styles')
    <style>
        .chart-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .chart-box {
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 1rem;
            background: linear-gradient(135deg, #f8fafc, #f1f5f9);
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.6);
        }

        .chart-canvas {
            position: relative;
            height: 240px;
            min-height: 240px;
        }

        .chart-canvas canvas {
            width: 100% !important;
            height: 100% !important;
        }

        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            margin-bottom: 0.5rem;
        }

        .chart-title {
            font-weight: 700;
        }
    </style>
@endpush

@section('content')
    <div class="card">
        @php
            $canViewCredentials = auth()->user()->can('viewCredentials', $server);
            $sshService = $server->sshService;
            $sshPassword = null;
            try {
                $sshPassword = optional($sshService)->password;
            } catch (\Illuminate\Contracts\Encryption\DecryptException $e) {
                $sshPassword = 'No disponible (clave inválida)';
            }
        @endphp
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

        @if ($canViewCredentials && $sshService)
            <div style="margin-top: 1.5rem;">
                <label>Credenciales SSH</label>
                <div>Host: {{ optional($sshService)->host ?? $server->ip_address ?? 'N/D' }} · Puerto: {{ optional($sshService)->port ?? 22 }}</div>
                <div>Usuario: {{ optional($sshService)->username ?? 'N/D' }}</div>
                <div>Contraseña: {{ $sshPassword ?? 'N/D' }}</div>
            </div>
        @endif

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
                        <th>Contraseña</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($server->services as $service)
                        @php
                            $servicePassword = null;
                            try {
                                $servicePassword = $service->password;
                            } catch (\Illuminate\Contracts\Encryption\DecryptException $e) {
                                $servicePassword = 'No disponible (clave inválida)';
                            }
                        @endphp
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
                        <td>
                            @if ($canViewCredentials)
                                {{ $servicePassword ?? 'N/D' }}
                            @else
                                <span class="muted">Sin acceso</span>
                            @endif
                        </td>
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
            $orderedLogs = $logs->sortBy('created_at');
            $chartLabels = $orderedLogs->map(fn($log) => $log->created_at->format('d M H:i'))->values();
            $cpuSeries = $orderedLogs->map(fn($log) => $log->cpu_load1 !== null ? (float) $log->cpu_load1 : null)->values();
            $ramSeries = $orderedLogs->map(fn($log) => $log->ram_usage_percent !== null ? (float) $log->ram_usage_percent : null)->values();
            $latencySeries = $orderedLogs->map(fn($log) => $log->latency_ms !== null ? (float) $log->latency_ms : null)->values();
            $statusColors = $orderedLogs->map(fn($log) => $log->status === 'up' ? '#16a34a' : '#dc2626')->values();
        @endphp
        @if ($logs->isEmpty())
            <p class="muted">No hay registros recientes.</p>
        @else
            <div class="chart-grid">
                <div class="chart-box">
                    <div class="chart-header">
                        <span class="chart-title">Uso de CPU</span>
                        <span class="muted">load1</span>
                    </div>
                    <div class="chart-canvas">
                        <canvas id="cpuChart"></canvas>
                    </div>
                </div>
                <div class="chart-box">
                    <div class="chart-header">
                        <span class="chart-title">Uso de RAM</span>
                        <span class="muted">%</span>
                    </div>
                    <div class="chart-canvas">
                        <canvas id="ramChart"></canvas>
                    </div>
                </div>
                <div class="chart-box">
                    <div class="chart-header">
                        <span class="chart-title">Latencia</span>
                        <span class="muted">ms</span>
                    </div>
                    <div class="chart-canvas">
                        <canvas id="latencyChart"></canvas>
                    </div>
                </div>
            </div>
            <div class="table-wrapper">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Estado</th>
                            <th>Latencia</th>
                            <th>RAM</th>
                            <th>CPU (load1)</th>
                            <th>Servicios crÃ­ticos</th>
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

    @if ($canViewCredentials)
        <div class="card">
            <h3 class="section-title">Agregar credencial al vault</h3>
            <div class="form-grid">
                <div>
                    <h4>SSH</h4>
                    <form method="POST" action="{{ route('servers.vault.store', $server) }}">
                        @csrf
                        <input type="hidden" name="entry_type" value="ssh">
                        <div>
                            <label for="ssh_host_form">Host</label>
                            <input type="text" id="ssh_host_form" name="host" value="{{ optional($sshService)->host ?? $server->ip_address }}" required>
                        </div>
                        <div>
                            <label for="ssh_port_form">Puerto</label>
                            <input type="number" id="ssh_port_form" name="port" min="1" max="65535" value="{{ optional($sshService)->port ?? 22 }}" required>
                        </div>
                        <div>
                            <label for="ssh_username_form">Usuario</label>
                            <input type="text" id="ssh_username_form" name="username" value="{{ optional($sshService)->username }}" required>
                        </div>
                        <div>
                            <label for="ssh_password_form">Contraseña</label>
                            <input type="text" id="ssh_password_form" name="password" required>
                        </div>
                        <button class="btn btn-secondary" type="submit" style="margin-top:0.75rem;">Guardar en vault</button>
                    </form>
                </div>
                <div>
                    <h4>Servicio</h4>
                    <form method="POST" action="{{ route('servers.vault.store', $server) }}">
                        @csrf
                        <input type="hidden" name="entry_type" value="service">
                        @if ($server->services->isNotEmpty())
                            <label for="existing_service_id">Servicio existente</label>
                            <select name="service_id" id="existing_service_id">
                                <option value="">Crear nuevo</option>
                                @foreach ($server->services as $service)
                                    <option value="{{ $service->id }}">{{ $service->name }}</option>
                                @endforeach
                            </select>
                        @endif
                        <div>
                            <label for="service_name_form">Nombre</label>
                            <input type="text" id="service_name_form" name="name" required>
                        </div>
                        <div>
                            <label for="service_port_form">Puerto</label>
                            <input type="number" id="service_port_form" name="port" min="1" max="65535" required>
                        </div>
                        <div>
                            <label for="service_username_form">Usuario</label>
                            <input type="text" id="service_username_form" name="username" required>
                        </div>
                        <div>
                            <label for="service_password_form">Contraseña</label>
                            <input type="text" id="service_password_form" name="password" required>
                        </div>
                        <div>
                            <label for="service_url_form">URL (opcional)</label>
                            <input type="url" id="service_url_form" name="url">
                        </div>
                        <button class="btn btn-secondary" type="submit" style="margin-top:0.75rem;">Guardar en vault</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="card">
            <h3 class="section-title">Vault de contraseñas</h3>
            <div class="form-grid">
                <div>
                    <h4>SSH</h4>
                    @forelse ($server->passwordLogs as $log)
                        @php
                            $logPassword = null;
                            try {
                                $logPassword = $log->password;
                            } catch (\Illuminate\Contracts\Encryption\DecryptException $e) {
                                $logPassword = 'No disponible (clave inválida)';
                            }
                        @endphp
                        <div style="margin-bottom:0.35rem;">
                            <strong>{{ $log->created_at->toDayDateTimeString() }}</strong>
                            <div>Contraseña: {{ $logPassword ?? 'N/D' }}</div>
                            <div class="muted">Registrado por {{ optional($log->recordedBy)->name ?? 'sistema' }}</div>
                        </div>
                    @empty
                        <p class="muted">Sin registros.</p>
                    @endforelse
                </div>
                <div>
                    <h4>Servicios</h4>
                    @foreach ($server->services as $service)
                        <div style="margin-bottom:0.75rem;">
                            <strong>{{ $service->name }}</strong>
                            @forelse ($service->passwordLogs->take(5) as $log)
                                @php
                                    $serviceLogPassword = null;
                                    try {
                                        $serviceLogPassword = $log->password;
                                    } catch (\Illuminate\Contracts\Encryption\DecryptException $e) {
                                        $serviceLogPassword = 'No disponible (clave inválida)';
                                    }
                                @endphp
                                <div style="font-size:0.9rem;">
                                    {{ $log->created_at->toDayDateTimeString() }} — {{ $serviceLogPassword ?? 'N/D' }}
                                </div>
                            @empty
                                <div class="muted">Sin registros.</div>
                            @endforelse
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endif
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            if (typeof Chart === 'undefined') return;

            const labels = @json($chartLabels);
            if (!labels.length) return;

            const statusColors = @json($statusColors);

            function createChart(canvasId, seriesLabel, color, data) {
                const canvas = document.getElementById(canvasId);
                if (!canvas) return;
                const ctx = canvas.getContext('2d');
                const gradient = ctx.createLinearGradient(0, 0, 0, canvas.height);
                gradient.addColorStop(0, `${color}55`);
                gradient.addColorStop(1, `${color}05`);

                new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels,
                        datasets: [{
                            label: seriesLabel,
                            data,
                            borderColor: color,
                            backgroundColor: gradient,
                            fill: true,
                            borderWidth: 2,
                            tension: 0.35,
                            spanGaps: true,
                            pointRadius: 4.5,
                            pointHoverRadius: 6,
                            pointBackgroundColor: statusColors,
                            pointBorderColor: '#0b1727',
                            pointBorderWidth: 1.2
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            x: {
                                grid: { display: false },
                                ticks: { color: '#4b5563', maxRotation: 0, autoSkip: true, maxTicksLimit: 6 }
                            },
                            y: {
                                beginAtZero: true,
                                grid: { color: 'rgba(15, 23, 42, 0.06)' },
                                ticks: { color: '#4b5563' }
                            }
                        },
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                backgroundColor: '#0f172a',
                                borderColor: '#1e293b',
                                borderWidth: 1,
                                titleColor: '#e2e8f0',
                                bodyColor: '#e2e8f0',
                                callbacks: {
                                    label: (ctx) => `${seriesLabel}: ${ctx.parsed.y ?? 'N/D'}`
                                }
                            }
                        },
                        interaction: { mode: 'index', intersect: false }
                    }
                });
            }

            createChart('cpuChart', 'CPU (load1)', '#2563eb', @json($cpuSeries));
            createChart('ramChart', 'RAM (%)', '#16a34a', @json($ramSeries));
            createChart('latencyChart', 'Latencia (ms)', '#f59e0b', @json($latencySeries));
        });
    </script>
@endpush
