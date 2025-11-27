@extends('layouts.app')

@section('title', 'Servidor '.$server->name)

@push('styles')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/xterm@5.3.0/css/xterm.css">
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

        .terminal-wrapper {
            display: grid;
            grid-template-columns: minmax(0, 2fr) minmax(240px, 1fr);
            gap: 1rem;
            align-items: start;
        }

        .terminal-shell {
            background: #0b1727;
            color: #e2e8f0;
            border-radius: 12px;
            min-height: 320px;
            border: 1px solid #111827;
            overflow: hidden;
        }

        .terminal-tips {
            background: #0f172a;
            color: #cbd5e1;
            border-radius: 12px;
            padding: 1rem;
            border: 1px dashed #1e293b;
        }

        .terminal-tips ul {
            padding-left: 1rem;
            margin: 0.35rem 0 0;
            color: #e5e7eb;
        }

        .terminal-meta {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
            font-size: 0.95rem;
        }

        @media (max-width: 900px) {
            .terminal-wrapper {
                grid-template-columns: 1fr;
            }
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

    @if ($canViewCredentials)
        @php
            $sessionCookieName = config('session.cookie');
            $sessionCookieRaw = $_COOKIE[$sessionCookieName] ?? '';
        @endphp
        <div class="card"
             id="ssh-terminal-card"
             data-cleanup-url="{{ route('servers.terminal.sessions.destroy', [$server, '__SESSION__']) }}"
             data-ws-port="{{ env('WS_PORT', 7001) }}"
             data-ws-url="{{ config('app.websocket_url') ?? env('WS_URL') }}"
             data-session-id="{{ session()->getId() }}"
             data-session-cookie="{{ e($sessionCookieRaw) }}">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem; flex-wrap: wrap;">
                <div>
                    <h3 class="section-title">Terminal remota</h3>
                    <p class="muted">xterm.js + phpseclib con clave efimera generada en el navegador.</p>
                    <div class="terminal-meta">
                        <span id="ssh-terminal-status">Listo para generar una clave efimera.</span>
                        <small class="muted">Fingerprint: <span id="ssh-terminal-fingerprint">-</span></small>
                    </div>
                </div>
                <div class="actions">
                    @if ($sshService)
                        <button type="button"
                                class="btn btn-primary"
                                id="ssh-terminal-popup"
                                data-terminal-url="{{ route('servers.terminal.view', $server) }}">
                            Abrir terminal
                        </button>
                        <button type="button" class="btn btn-light" id="ssh-terminal-close" style="display:none;">Cerrar</button>
                    @else
                        <span class="badge badge-warning">Configura el servicio SSH para habilitar la terminal</span>
                    @endif
                </div>
            </div>

            @if ($sshService)
                <div class="terminal-wrapper" id="terminal-wrapper" style="display:none; margin-top:1rem;"></div>
            @endif
        </div>
    @endif

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
    <script src="https://cdn.jsdelivr.net/npm/xterm@5.3.0/lib/xterm.min.js"></script>
    <script>
        (function () {
            const connectBtn = document.getElementById('ssh-terminal-connect');
            if (!connectBtn) return;

            const closeBtn = document.getElementById('ssh-terminal-close');
            const wrapper = document.getElementById('terminal-wrapper');
            const shellEl = document.getElementById('xterm-shell');
            const statusEl = document.getElementById('ssh-terminal-status');
            const fingerprintEl = document.getElementById('ssh-terminal-fingerprint');
            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            const card = document.getElementById('ssh-terminal-card');
            const cleanupTemplate = card?.dataset.cleanupUrl;
            const sessionUrl = connectBtn.dataset.sessionUrl;
            const serverId = {{ $server->id }};
            const wsUrlOverride = card?.dataset.wsUrl || '';
            const wsPort = card?.dataset.wsPort || '6001';
            const wsPath = '';
            const sessionCookieFromServer = card?.dataset.sessionCookie || '';

            let term = null;
            let sessionId = null;
            let privateKeyPem = null;
            let ws = null;

            function setStatus(text) {
                if (statusEl) {
                    statusEl.textContent = text;
                }
            }

            function toPem(buffer, label) {
                const base64 = btoa(String.fromCharCode(...new Uint8Array(buffer)));
                const lines = base64.match(/.{1,64}/g)?.join("\n") || base64;
                return `-----BEGIN ${label}-----\n${lines}\n-----END ${label}-----`;
            }

            async function sha256Hex(text) {
                const encoder = new TextEncoder();
                const digest = await crypto.subtle.digest('SHA-256', encoder.encode(text));
                return Array.from(new Uint8Array(digest))
                    .map((b) => b.toString(16).padStart(2, '0'))
                    .join('');
            }

            async function generateEphemeralKey() {
                if (!window.crypto?.subtle) {
                    throw new Error('Este navegador no soporta WebCrypto.');
                }

                const pair = await crypto.subtle.generateKey(
                    {
                        name: 'RSASSA-PKCS1-v1_5',
                        modulusLength: 2048,
                        publicExponent: new Uint8Array([1, 0, 1]),
                        hash: 'SHA-256',
                    },
                    true,
                    ['sign', 'verify']
                );

                const [publicKey, privateKey] = await Promise.all([
                    crypto.subtle.exportKey('spki', pair.publicKey),
                    crypto.subtle.exportKey('pkcs8', pair.privateKey),
                ]);

                const publicKeyPem = toPem(publicKey, 'PUBLIC KEY');
                const privateKeyPem = toPem(privateKey, 'PRIVATE KEY');
                const fingerprint = await sha256Hex(publicKeyPem);

                return { publicKeyPem, privateKeyPem, fingerprint };
            }

            async function registerSession(publicKey, fingerprint) {
                const response = await fetch(sessionUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                    },
                    body: JSON.stringify({ public_key: publicKey, fingerprint }),
                });

                if (!response.ok) {
                    const payload = await response.json().catch(() => ({}));
                    const message = payload.message || 'No se pudo registrar la sesion SSH.';
                    throw new Error(message);
                }

                return response.json();
            }

            function ensureTerminal() {
                if (term) return term;
                if (typeof window.Terminal === 'undefined') {
                    throw new Error('xterm.js no esta disponible.');
                }

                term = new window.Terminal({
                    convertEol: true,
                    cursorBlink: true,
                    fontSize: 14,
                    theme: {
                        background: '#0b1727',
                        foreground: '#f8fafc',
                        cursor: '#60a5fa',
                        black: '#0f172a',
                    },
                });
                term.open(shellEl);
                term.onData((data) => {
                    if (ws && ws.readyState === WebSocket.OPEN) {
                        ws.send(JSON.stringify({ type: 'stdin', data }));
                    }
                });
                term.onResize(({ cols, rows }) => sendResize(cols, rows));
                return term;
            }

            function getCookie(name) {
                const value = `; ${document.cookie}`;
                const parts = value.split(`; ${name}=`);
                if (parts.length === 2) {
                    return parts.pop().split(';').shift();
                }
                return '';
            }

            function connectWebSocket() {
                const proto = window.location.protocol === 'https:' ? 'wss' : 'ws';
                let baseUrl = wsUrlOverride?.trim()
                    ? wsUrlOverride.trim()
                    : `${proto}://${window.location.hostname}:${wsPort}${wsPath}`;

                const query = [];
                const encryptedSession = sessionCookieFromServer || getCookie('laravel_session') || getCookie('laravel-session');
                if (encryptedSession) {
                    query.push(`laravel_session=${encodeURIComponent(encryptedSession)}`);
                }
                if (query.length) {
                    const delimiter = baseUrl.includes('?') ? '&' : '?';
                    baseUrl = `${baseUrl}${delimiter}${query.join('&')}`;
                }

                const url = baseUrl;

                ws = new WebSocket(url);

                ws.onopen = () => {
                    setStatus('Enviando clave efimera al WS...');
                    ws.send(JSON.stringify({
                        type: 'init',
                        server_id: serverId,
                        session_id: sessionId,
                        private_key: privateKeyPem,
                        session_token: encryptedSession,
                    }));
                };

                ws.onmessage = (event) => {
                    try {
                        const msg = JSON.parse(event.data);
                        if (msg.type === 'ready') {
                            setStatus('Terminal conectada. Puedes interactuar.');
                            if (term) {
                                sendResize(term.cols, term.rows);
                            }
                            window.showToast?.('Terminal SSH conectada', 'success');
                            return;
                        }
                        if (msg.type === 'stdout') {
                            term?.write(msg.data ?? '');
                            return;
                        }
                        if (msg.type === 'error') {
                            setStatus(msg.message || 'Error en WebSocket');
                            window.showToast?.(msg.message || 'Error en WS', 'error');
                            return;
                        }
                    } catch (e) {
                        // noop
                    }
                };

                ws.onclose = () => {
                    setStatus('Sesion WS cerrada (verifica puerto/WS_URL y logs).');
                };

                ws.onerror = () => {
                    setStatus('Error en la conexion WS.');
                };
            }

            function sendResize(cols, rows) {
                if (ws && ws.readyState === WebSocket.OPEN) {
                    ws.send(JSON.stringify({
                        type: 'resize',
                        cols,
                        rows,
                    }));
                }
            }

            async function openTerminal() {
                try {
                    setStatus('Generando clave efimera en el navegador...');
                    const { publicKeyPem, privateKeyPem: privPem, fingerprint } = await generateEphemeralKey();
                    privateKeyPem = privPem;
                    fingerprintEl.textContent = fingerprint;

                    setStatus('Registrando clave publica y creando sesion...');
                    const payload = await registerSession(publicKeyPem, fingerprint);
                    sessionId = payload.session?.id ?? null;

                    if (wrapper) wrapper.style.display = 'grid';
                    if (closeBtn) closeBtn.style.display = 'inline-flex';

                    const terminal = ensureTerminal();
                    terminal.reset();
                    terminal.writeln('Sesion preparada: ' + (sessionId || 'sin id'));
                    if (payload.session?.target) {
                        const t = payload.session.target;
                        terminal.writeln(`Destino: ${t.username}@${t.host}:${t.port}`);
                    }
                    terminal.writeln('Clave privada efimera guardada solo en memoria.');
                    terminal.writeln('Abriendo canal WebSocket -> phpseclib -> SSH...');
                    setStatus('Sesion lista. Conectando WebSocket...');
                    connectWebSocket();

                    window.showToast?.('Sesion de terminal preparada', 'success');
                } catch (error) {
                    setStatus(error.message || 'Error al preparar la terminal.');
                    window.showToast?.(error.message || 'Error en terminal', 'error');
                }
            }

            async function closeSession() {
                if (wrapper) wrapper.style.display = 'none';
                fingerprintEl.textContent = '-';
                privateKeyPem = null;

                if (term) {
                    term.reset();
                }

                if (sessionId && cleanupTemplate) {
                    const url = cleanupTemplate.replace('__SESSION__', sessionId);
                    try {
                        await fetch(url, {
                            method: 'DELETE',
                            headers: {
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': csrf,
                            },
                        });
                    } catch (_) {
                        // noop
                    }
                }

                sessionId = null;
                if (ws) {
                    try { ws.close(); } catch (_) {}
                    ws = null;
                }
                setStatus('Sesion cerrada y marcada para limpieza.');
                if (closeBtn) closeBtn.style.display = 'none';
            }

            connectBtn.addEventListener('click', openTerminal);
            closeBtn?.addEventListener('click', closeSession);

            window.addEventListener('resize', () => {
                if (!term) return;
                const dims = term._core?._renderService?.dimensions;
                if (dims && term.cols && term.rows) {
                    sendResize(term.cols, term.rows);
                }
            });
        })();
    </script>
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

    <script>
        (() => {
            const popupBtn = document.getElementById('ssh-terminal-popup');
            if (!popupBtn) return;

            popupBtn.addEventListener('click', () => {
                const url = popupBtn.dataset.terminalUrl;
                const w = Math.floor(window.screen.width * 0.7);
                const h = Math.floor(window.screen.height * 0.7);
                const left = Math.floor((window.screen.width - w) / 2);
                const top = Math.floor((window.screen.height - h) / 2);
                window.open(
                    url,
                    'terminalWindow',
                    `width=${w},height=${h},left=${left},top=${top},menubar=no,toolbar=no,location=no,status=no,resizable=yes,scrollbars=yes`
                );
            });
        })();
    </script>
@endpush
