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

        .server-card.status-maintenance {
            border-color: #bfdbfe;
            background: #eff6ff;
        }

        .server-card.loading {
            background: #f3f4f6;
            border-color: #d1d5db;
            opacity: 0.85;
        }

        .server-card.loading .status-pill {
            background: #e5e7eb !important;
            color: #374151 !important;
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

        .loading-indicator {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            font-size: 0.85rem;
            color: #6b7280;
        }

        .loading-indicator::before {
            content: '';
            width: 12px;
            height: 12px;
            border-radius: 50%;
            border: 2px solid #93c5fd;
            border-top-color: transparent;
            animation: spin 0.8s linear infinite;
            display: inline-block;
        }

        @keyframes spin {
            from {
                transform: rotate(0deg);
            }
            to {
                transform: rotate(360deg);
            }
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
            <button class="btn btn-primary" type="button" id="refresh-dashboard">Actualizar ahora</button>
        </div>

        <div class="stat-grid">
            <div class="stat-card stat-up">
                <div class="stat-label">Físicos en línea</div>
                <div class="stat-value" data-summary="physical-up">{{ $summary['physical']['up'] }}</div>
            </div>
            <div class="stat-card stat-down">
                <div class="stat-label">Físicos sin respuesta</div>
                <div class="stat-value" data-summary="physical-down">{{ $summary['physical']['down'] }}</div>
            </div>
            <div class="stat-card stat-up">
                <div class="stat-label">Virtuales en línea</div>
                <div class="stat-value" data-summary="virtual-up">{{ $summary['virtual']['up'] }}</div>
            </div>
            <div class="stat-card stat-down">
                <div class="stat-label">Virtuales sin respuesta</div>
                <div class="stat-value" data-summary="virtual-down">{{ $summary['virtual']['down'] }}</div>
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
                    $logs = ($history->get($server->id) ?? collect())->take(5);
                    $servicesStatus = $ssh['services'] ?? ($logs->first()->services_status ?? null);
                    $isMaintenance = $server->in_maintenance;
                @endphp
                <div class="server-card {{ $isMaintenance ? 'status-maintenance' : ($isUp ? 'status-up' : 'status-down') }}"
                    data-server-id="{{ $server->id }}"
                    data-server-type="{{ $server->is_physical ? 'physical' : 'virtual' }}"
                    data-server-name="{{ $server->name }}"
                    data-health-url="{{ route('dashboard.server-health', $server) }}"
                    data-current-status="{{ $isMaintenance ? 'maintenance' : ($isUp ? 'up' : 'down') }}"
                    data-maintenance="{{ $isMaintenance ? '1' : '0' }}">
                    <div class="server-card-header">
                        <div>
                            <strong>{{ $server->name }}</strong>
                            <div class="muted" style="font-size: 0.85rem;">
                                {{ $server->ip_address }} · {{ $server->is_physical ? 'Físico' : 'Virtual' }}
                            </div>
                            <div class="muted" style="font-size: 0.8rem;">
                                {{ $server->environment ?? 'Ambiente N/D' }} · {{ $server->location ?? 'Ubicación N/D' }}
                                · Responsable: {{ $server->owner ?? 'N/D' }}
                            </div>
                            <div class="muted" style="font-size: 0.75rem;">
                                {{ $server->os_name ?? 'SO N/D' }} {{ $server->os_version }}
                                {{ $server->kernel_version ? '· Kernel '.$server->kernel_version : '' }}
                                {{ $server->cpu_cores ? '· '.$server->cpu_cores.' cores' : '' }}
                            </div>
                        </div>
                        <span class="status-pill {{ $isMaintenance ? 'down' : ($isUp ? 'up' : 'down') }} js-status-pill">
                            <span class="js-status-text">{{ $isMaintenance ? 'Mantenimiento' : ($isUp ? 'Encendido' : 'Apagado') }}</span>
                        </span>
                    </div>
                    <div class="metric-row js-latency-row">
                        <span>Latencia</span>
                        @php $latencyText = isset($data['latency_ms']) ? $data['latency_ms'].' ms' : 'No hay datos'; @endphp
                        <span class="js-latency">{{ $latencyText }}</span>
                    </div>
                    <div class="metric-row js-ram-row">
                        <span>RAM</span>
                        @php
                            $ramText = ($ssh && $ssh['connected'] && $ssh['ram'])
                                ? sprintf('%s / %s MB (%s%%)', $ssh['ram']['used_mb'], $ssh['ram']['total_mb'], $ssh['ram']['usage_percent'])
                                : 'No hay datos';
                        @endphp
                        <span class="js-ram">{{ $ramText }}</span>
                    </div>
                    <div class="metric-row js-cpu-row">
                        <span>CPU (load)</span>
                        @php
                            $cpuText = ($ssh && $ssh['connected'] && $ssh['cpu'])
                                ? sprintf('%s, %s, %s', $ssh['cpu']['load1'], $ssh['cpu']['load5'], $ssh['cpu']['load15'])
                                : 'No hay datos';
                        @endphp
                        <span class="js-cpu">{{ $cpuText }}</span>
                    </div>
                    <div class="ssh-status js-ssh-error">
                        @if ($ssh && $ssh['error'] && ! $ssh['connected'])
                            {{ $ssh['error'] }}
                        @endif
                    </div>
                    <div class="js-services">
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
                    </div>
                    <div>
                        <strong style="font-size: 0.85rem;">Historial reciente</strong>
                        <div class="js-history" style="display: flex; gap: 0.4rem; flex-wrap: wrap; margin-top: 0.4rem;">
                            @if ($logs->isNotEmpty())
                                @foreach ($logs->take(4) as $log)
                                    @php $up = $log->status === 'up'; @endphp
                                    <span class="status-pill {{ $up ? 'up' : ($log->status === 'maintenance' ? 'down' : 'down') }}" style="font-size: 0.75rem;">
                                        {{ $log->created_at->diffForHumans(null, null, true) }}
                                    </span>
                                @endforeach
                            @else
                                <span class="muted" style="font-size: 0.8rem;">No hay datos</span>
                            @endif
                        </div>
                    </div>
                </div>
            @empty
                <p class="muted">No hay servidores disponibles.</p>
            @endforelse
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const cards = Array.from(document.querySelectorAll('.server-card[data-server-id]'));
            const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
            const summaryEls = {
                physical: {
                    up: document.querySelector('[data-summary="physical-up"]'),
                    down: document.querySelector('[data-summary="physical-down"]'),
                },
                virtual: {
                    up: document.querySelector('[data-summary="virtual-up"]'),
                    down: document.querySelector('[data-summary="virtual-down"]'),
                },
            };

            function updateSummary(card, newStatus) {
                const type = card.dataset.serverType;
                const prev = card.dataset.currentStatus;
                if (!summaryEls[type]) {
                    return;
                }
                if (prev && summaryEls[type][prev] && (prev === 'up' || prev === 'down')) {
                    summaryEls[type][prev].textContent = Math.max(0, Number(summaryEls[type][prev].textContent) - 1);
                }
                card.dataset.currentStatus = newStatus;
                if (summaryEls[type][newStatus] && (newStatus === 'up' || newStatus === 'down')) {
                    summaryEls[type][newStatus].textContent = Number(summaryEls[type][newStatus].textContent) + 1;
                }
            }

            function renderServices(container, services) {
                container.innerHTML = '';
                if (!services || Object.keys(services).length === 0) {
                    return;
                }
                const wrapper = document.createElement('div');
                wrapper.innerHTML = '<strong style="font-size: 0.85rem;">Servicios críticos</strong>';
                const list = document.createElement('ul');
                list.style.margin = '0.35rem 0 0';
                list.style.paddingLeft = '1rem';
                list.style.fontSize = '0.85rem';
                Object.entries(services).forEach(([service, status]) => {
                    const li = document.createElement('li');
                    const span = document.createElement('span');
                    span.style.fontWeight = '600';
                    span.style.color = status === 'active' ? '#166534' : '#991b1b';
                    span.textContent = status;
                    li.textContent = `${service}: `;
                    li.appendChild(span);
                    list.appendChild(li);
                });
                wrapper.appendChild(list);
                container.appendChild(wrapper);
            }

            function renderHistory(container, log) {
                if (!log || !container) {
                    return;
                }
                while (container.firstElementChild && container.firstElementChild.classList.contains('muted')) {
                    container.removeChild(container.firstElementChild);
                }
                const pill = document.createElement('span');
                const statusClass = log.status === 'up' ? 'up' : (log.status === 'maintenance' ? 'down' : 'down');
                pill.className = `status-pill ${statusClass}`;
                pill.style.fontSize = '0.75rem';
                const date = new Date(log.created_at);
                pill.textContent = date.toLocaleTimeString();
                container.prepend(pill);
                if (container.children.length > 4) {
                    container.lastElementChild.remove();
                }
            }

            function updateCard(card, data) {
                card.classList.remove('loading');
                const result = data.result;
                const statusPill = card.querySelector('.js-status-pill');
                statusPill.classList.remove('up', 'down');
                statusPill.classList.add(result.status === 'up' ? 'up' : 'down');
                if (result.status === 'maintenance') {
                    card.querySelector('.js-status-text').textContent = 'Mantenimiento';
                } else if (result.status === 'up') {
                    card.querySelector('.js-status-text').textContent = 'Encendido';
                } else {
                    card.querySelector('.js-status-text').textContent = 'Apagado';
                }
                card.classList.toggle('status-up', result.status === 'up');
                card.classList.toggle('status-down', result.status === 'down');
                card.classList.toggle('status-maintenance', result.status === 'maintenance');
                card.dataset.currentStatus = result.status;
                card.dataset.maintenance = result.status === 'maintenance' ? '1' : '0';
                const latencyText = typeof result.latency_ms === 'number'
                    ? `${result.latency_ms} ms`
                    : 'No hay datos';
                card.querySelector('.js-latency').textContent = latencyText;

                if (result.ssh && result.ssh.connected && result.ssh.ram) {
                    const ram = result.ssh.ram;
                    card.querySelector('.js-ram').textContent = `${ram.used_mb} / ${ram.total_mb} MB (${ram.usage_percent}%)`;
                } else {
                    card.querySelector('.js-ram').textContent = 'No hay datos';
                }

                const cpuText = (result.ssh && result.ssh.connected && result.ssh.cpu)
                    ? `${result.ssh.cpu.load1}, ${result.ssh.cpu.load5}, ${result.ssh.cpu.load15}`
                    : 'No hay datos';
                card.querySelector('.js-cpu').textContent = cpuText;

                const errorBox = card.querySelector('.js-ssh-error');
                if (result.ssh && result.ssh.error && !result.ssh.connected) {
                    errorBox.textContent = result.ssh.error;
                } else {
                    errorBox.textContent = '';
                }

                const services = result.ssh && result.ssh.services ? result.ssh.services : null;
                renderServices(card.querySelector('.js-services'), services);
                renderHistory(card.querySelector('.js-history'), data.log);
                updateSummary(card, result.status === 'up' ? 'up' : 'down');
            }

            const filteredQueue = () => cards.filter(card => card.dataset.maintenance !== '1');
            let queue = filteredQueue();
            let active = 0;
            const CONCURRENCY = 4;

            function processQueue(reset = false) {
                if (reset) {
                    queue = filteredQueue();
                    active = 0;
                }
                while (active < CONCURRENCY && queue.length > 0) {
                    const card = queue.shift();
                    if (card.dataset.maintenance === '1') {
                        continue;
                    }
                    active++;
                    card.classList.add('loading');
                    card.querySelector('.js-status-text').textContent = 'Actualizando…';
                    card.querySelectorAll('.js-latency, .js-ram, .js-cpu').forEach((el) => {
                        el.innerHTML = '<span class="loading-indicator">Actualizando…</span>';
                    });
                    fetch(card.dataset.healthUrl, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                        },
                    })
                        .then((response) => {
                            if (!response.ok) {
                                throw new Error('No se pudo actualizar '+card.dataset.serverName);
                            }
                            return response.json();
                        })
                        .then((data) => {
                            updateCard(card, data);
                        })
                        .catch((error) => {
                            showToast(error.message, 'error');
                        })
                        .finally(() => {
                            active--;
                            processQueue();
                        });
                }
            }

            document.getElementById('refresh-dashboard').addEventListener('click', () => {
                processQueue(true);
            });

            processQueue();
        });
    </script>
@endpush
