<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Terminal - {{ $server->name }}</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/xterm@5.3.0/css/xterm.css">
    <style>
        :root { color-scheme: dark; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            background: #0b1727;
            color: #e5e7eb;
            font-family: "Segoe UI", Arial, sans-serif;
            height: 100vh;
            display: flex;
            flex-direction: column;
        }
        .toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: 0.75rem 1rem;
            background: #0f1b2d;
            border-bottom: 1px solid #1f2937;
        }
        .toolbar .status { font-size: 0.95rem; color: #cbd5e1; }
        .btn {
            border: none;
            border-radius: 8px;
            padding: 0.5rem 0.9rem;
            cursor: pointer;
            font-size: 0.95rem;
        }
        .btn-primary { background: #2563eb; color: #fff; }
        .btn-light { background: #1f2937; color: #e5e7eb; }
        .terminal-shell {
            flex: 1;
            padding: 0.5rem;
        }
        #xterm-shell { width: 100%; height: 100%; }
    </style>
</head>
<body>
    @php
        $sessionCookieName = config('session.cookie');
        $sessionCookieRaw = $_COOKIE[$sessionCookieName] ?? '';
    @endphp
    <div class="toolbar"
         id="ssh-terminal-card"
         data-cleanup-url="{{ route('servers.terminal.sessions.destroy', [$server, '__SESSION__']) }}"
         data-ws-port="{{ env('WS_PORT', 7001) }}"
         data-ws-url="{{ config('app.websocket_url') ?? env('WS_FRONT_URL') }}"
         data-session-id="{{ session()->getId() }}"
         data-session-cookie="{{ e($sessionCookieRaw) }}">
        <div>
            <div class="status" id="ssh-terminal-status">Listo para generar clave efimera.</div>
            <div class="status" style="font-size:0.85rem;">Fingerprint: <span id="ssh-terminal-fingerprint">-</span></div>
        </div>
        <div style="display:flex; gap:0.5rem;">
            <button type="button" class="btn btn-primary" id="ssh-terminal-connect"
                    data-session-url="{{ route('servers.terminal.sessions.store', $server) }}">Conectar</button>
            <button type="button" class="btn btn-light" id="ssh-terminal-close">Cerrar</button>
        </div>
    </div>
    <div class="terminal-shell">
        <div id="xterm-shell"></div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/xterm@5.3.0/lib/xterm.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/xterm-addon-fit@0.8.0/lib/xterm-addon-fit.min.js"></script>
    <script>
        (() => {
            const connectBtn = document.getElementById('ssh-terminal-connect');
            const closeBtn = document.getElementById('ssh-terminal-close');
            const shellEl = document.getElementById('xterm-shell');
            const statusEl = document.getElementById('ssh-terminal-status');
            const fingerprintEl = document.getElementById('ssh-terminal-fingerprint');
            const card = document.getElementById('ssh-terminal-card');
            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            const cleanupTemplate = card?.dataset.cleanupUrl;
            const sessionUrl = connectBtn.dataset.sessionUrl;
            const serverId = {{ $server->id }};
            const wsUrlOverride = card?.dataset.wsUrl || '';
            const wsPort = card?.dataset.wsPort || '7001';
            const sessionCookieFromServer = card?.dataset.sessionCookie || '';

            let term = null;
            let sessionId = null;
            let privateKeyPem = null;
            let ws = null;
            let encryptedSession = '';
            let fitAddon = null;

            function setStatus(text) { if (statusEl) statusEl.textContent = text; }

            function toPem(buffer, label) {
                const base64 = btoa(String.fromCharCode(...new Uint8Array(buffer)));
                const lines = base64.match(/.{1,64}/g)?.join("\n") || base64;
                return `-----BEGIN ${label}-----\n${lines}\n-----END ${label}-----`;
            }

            async function sha256Hex(text) {
                const encoder = new TextEncoder();
                const digest = await crypto.subtle.digest('SHA-256', encoder.encode(text));
                return Array.from(new Uint8Array(digest)).map((b) => b.toString(16).padStart(2, '0')).join('');
            }

            async function generateEphemeralKey() {
                const pair = await crypto.subtle.generateKey(
                    { name: 'RSASSA-PKCS1-v1_5', modulusLength: 2048, publicExponent: new Uint8Array([1, 0, 1]), hash: 'SHA-256' },
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

            function ensureTerminal() {
                if (term) return term;
                term = new window.Terminal({
                    convertEol: true,
                    cursorBlink: true,
                    fontSize: 14,
                    theme: { background: '#0b1727', foreground: '#f8fafc', cursor: '#60a5fa' },
                });
                fitAddon = new window.FitAddon.FitAddon();
                term.loadAddon(fitAddon);
                term.open(shellEl);
                term.onData((data) => {
                    if (ws && ws.readyState === WebSocket.OPEN) {
                        ws.send(JSON.stringify({ type: 'stdin', data }));
                    }
                });
                term.onResize(({ cols, rows }) => sendResize(cols, rows));
                fitAddon.fit();
                sendResize(term.cols, term.rows);
                window.addEventListener('resize', () => {
                    fitAddon.fit();
                    sendResize(term.cols, term.rows);
                });
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
                    : `${proto}://${window.location.hostname}:${wsPort}`;

                if (!encryptedSession) {
                    encryptedSession = sessionCookieFromServer || getCookie('laravel_session') || getCookie('laravel-session') || '';
                }

                if (encryptedSession) {
                    const delimiter = baseUrl.includes('?') ? '&' : '?';
                    baseUrl = `${baseUrl}${delimiter}laravel_session=${encodeURIComponent(encryptedSession)}`;
                }

                ws = new WebSocket(baseUrl);

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
                            if (term) sendResize(term.cols, term.rows);
                            return;
                        }
                        if (msg.type === 'stdout') {
                            term?.write(msg.data ?? '');
                            return;
                        }
                        if (msg.type === 'error') {
                            setStatus(msg.message || 'Error en WebSocket');
                            return;
                        }
                    } catch (_) {}
                };

                ws.onclose = () => setStatus('Sesion WS cerrada.');
                ws.onerror = () => setStatus('Error en la conexion WS.');
            }

            function sendResize(cols, rows) {
                if (ws && ws.readyState === WebSocket.OPEN) {
                    ws.send(JSON.stringify({ type: 'resize', cols, rows }));
                }
            }

            async function registerSession(publicKey, fingerprint) {
                const response = await fetch(sessionUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
                    body: JSON.stringify({ public_key: publicKey, fingerprint }),
                });
                if (!response.ok) {
                    const payload = await response.json().catch(() => ({}));
                    const message = payload.message || 'No se pudo registrar la sesion SSH.';
                    throw new Error(message);
                }
                return response.json();
            }

            async function openTerminal() {
                try {
                    setStatus('Generando clave efimera...');
                    const { publicKeyPem, privateKeyPem: privPem, fingerprint } = await generateEphemeralKey();
                    privateKeyPem = privPem;
                    fingerprintEl.textContent = fingerprint;

                    setStatus('Registrando clave publica...');
                    const payload = await registerSession(publicKeyPem, fingerprint);
                    sessionId = payload.session?.id ?? null;

                    ensureTerminal();
                    term.reset();
                    term.writeln('Sesion preparada: ' + (sessionId || 'sin id'));
                    if (payload.session?.target) {
                        const t = payload.session.target;
                        term.writeln(`Destino: ${t.username}@${t.host}:${t.port}`);
                    }
                    setStatus('Sesion lista. Conectando WS...');
                    connectWebSocket();
                } catch (error) {
                    setStatus(error.message || 'Error al preparar la terminal.');
                }
            }

            async function closeSession() {
                if (ws) {
                    try { ws.close(); } catch (_) {}
                    ws = null;
                }
                if (term) {
                    term.reset();
                }
                fingerprintEl.textContent = '-';
                privateKeyPem = null;
                if (sessionId && cleanupTemplate) {
                    const url = cleanupTemplate.replace('__SESSION__', sessionId);
                    try {
                        await fetch(url, { method: 'DELETE', headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf } });
                    } catch (_) {}
                }
                sessionId = null;
                setStatus('Sesion cerrada.');
            }

            connectBtn.addEventListener('click', openTerminal);
            closeBtn.addEventListener('click', closeSession);
        })();
    </script>
</body>
</html>
