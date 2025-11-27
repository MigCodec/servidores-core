<?php

namespace App\Console\Commands;

use App\Models\Server;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Net\SSH2;
use Workerman\Timer;
use Workerman\Worker;

class TerminalWebSocket extends Command
{
    protected $signature = 'terminal:ws {--port=7001}';
    protected $description = 'WebSocket para la terminal SSH efimera';

    protected array $clients = [];
    protected string $logFile;

    public function handle(): int
    {
        $this->logFile = storage_path('logs/terminal-ws.log');
        $port = (int) $this->option('port');

        $ws = new Worker("websocket://127.0.0.1:{$port}");
        $ws->count = 1;

        $ws->onMessage = function ($conn, $data) {
            $this->handleMessage($conn, $data);
        };

        $ws->onClose = function ($conn) {
            $this->handleClose($conn);
        };

        $ws->onWebSocketConnect = function ($conn) {
            $this->logLine('Conexion entrante', [
                'conn' => $conn->id ?? null,
                'ip' => method_exists($conn, 'getRemoteIp') ? $conn->getRemoteIp() : null,
            ]);
            $this->attachUserFromSession($conn);
        };

        $ws->onWorkerStart = function () {
            Timer::add(0.005, function () {
                foreach ($this->clients as $client) {
                    if (empty($client['ssh'])) {
                        continue;
                    }
                    try {
                        $chunk = $client['ssh']->read();
                        if ($chunk !== false && $chunk !== '') {
                            $client['conn']->send(json_encode(['type' => 'stdout', 'data' => $chunk]));
                        }
                    } catch (\Throwable $e) {
                        $this->logLine('Error leyendo canal SSH', ['conn' => $client['conn']->id ?? null, 'error' => $e->getMessage()]);
                        $client['conn']->send(json_encode(['type' => 'error', 'message' => 'Conexion SSH cerrada']));
                        $this->handleClose($client['conn']);
                    }
                }
            });
        };

        $this->info("Terminal WS escuchando en puerto {$port}");
        $this->logLine('WS iniciado', ['port' => $port]);
        Worker::runAll();

        return 0;
    }

    protected function handleMessage($conn, $data): void
    {
        if (! property_exists($conn, 'userId')) {
            $conn->userId = null;
        }
        if (! property_exists($conn, 'user')) {
            $conn->user = null;
        }

        $msg = json_decode($data, true);
        if (! is_array($msg) || empty($msg['type'])) {
            return;
        }

        if (empty($conn->userId) && ! empty($msg['session_token'])) {
            $this->attachUserFromSession($conn, $msg['session_token']);
        }

        if ($msg['type'] === 'init') {
            $this->handleInit($conn, $msg);
            return;
        }

        if (empty($conn->userId)) {
            $conn->send(json_encode(['type' => 'error', 'message' => 'Sesion no autenticada']));
            $conn->close();
            return;
        }

        if ($msg['type'] === 'stdin') {
            $this->handleStdin($conn, $msg['data'] ?? '');
            return;
        }

        if ($msg['type'] === 'resize') {
            $this->handleResize($conn, (int) ($msg['cols'] ?? 80), (int) ($msg['rows'] ?? 24));
        }
    }

    protected function handleInit($conn, array $msg): void
    {
        $sessionKey = 'terminal-session:'.$msg['server_id'].':'.$msg['session_id'];
        $session = Cache::get($sessionKey);
        if (! $session) {
            $conn->send(json_encode(['type' => 'error', 'message' => 'Sesion no encontrada']));
            return;
        }

        $server = Server::find($session['server_id']);
        $user = $conn->user ?? User::find($conn->userId);

        if (! $server || ! $user || (! $user->isAdmin() && $session['created_by'] !== $user->id && ! $user->canAccessServerCredentials($server))) {
            $conn->send(json_encode(['type' => 'error', 'message' => 'No autorizado']));
            $conn->close();
            return;
        }

        $fakeSsh = env('WS_FAKE_SSH', config('app.ws_fake_ssh', false));
        try {
            if ($fakeSsh) {
                $ssh = new class
                {
                    public function setTimeout($t) {}
                    public function write($d) {}
                    public function read() { return ''; }
                    public function disconnect() {}
                    public function login(...$args) { return true; }
                    public function request_pty() { return true; }
                    public function shell() { return true; }
                    public function resizeWindow($c, $r) { return true; }
                };
            } else {
                $ssh = new SSH2($session['target']['host'], (int) $session['target']['port']);
                $key = PublicKeyLoader::load($msg['private_key']);
            }
        } catch (\Throwable $e) {
            $conn->send(json_encode(['type' => 'error', 'message' => 'Clave privada invalida']));
            return;
        }

        if (! $ssh->login($session['target']['username'], $fakeSsh ? null : $key)) {
            $conn->send(json_encode(['type' => 'error', 'message' => 'Login SSH fallido']));
            return;
        }

        if (method_exists($ssh, 'enablePTY')) {
            try {
                $ssh->enablePTY();
            } catch (\Throwable $e) {
                // pty opcional
            }
        }

        if (method_exists($ssh, 'resizeWindow')) {
            try {
                $ssh->resizeWindow(80, 24);
            } catch (\Throwable $e) {
                // noop
            }
        } else {
            try {
                $ssh->write("stty cols 80 rows 24\n");
            } catch (\Throwable $e) {
                // noop
            }
        }

            $ssh->setTimeout(0.005);
            $this->clients[$conn->id] = ['conn' => $conn, 'ssh' => $ssh, 'session' => $session];
            $conn->send(json_encode(['type' => 'ready']));
    }

    protected function handleStdin($conn, string $data): void
    {
        $client = $this->clients[$conn->id] ?? null;
        if (! $client || empty($client['ssh']) || (method_exists($client['ssh'], 'isConnected') && ! $client['ssh']->isConnected())) {
            return;
        }
        try {
            $client['ssh']->write($data);
        } catch (\Throwable $e) {
            $conn->send(json_encode(['type' => 'error', 'message' => 'Conexion SSH cerrada']));
            $this->handleClose($conn);
        }
    }

    protected function handleResize($conn, int $cols, int $rows): void
    {
        $client = $this->clients[$conn->id] ?? null;
        if (! $client || empty($client['ssh']) || (method_exists($client['ssh'], 'isConnected') && ! $client['ssh']->isConnected())) {
            return;
        }
        try {
            if (method_exists($client['ssh'], 'resizeWindow')) {
                $client['ssh']->resizeWindow($cols, $rows);
            } else {
                $client['ssh']->write("stty cols {$cols} rows {$rows}\n");
            }
        } catch (\Throwable $e) {
            $conn->send(json_encode(['type' => 'error', 'message' => 'Conexion SSH cerrada']));
            $this->handleClose($conn);
        }
    }

    protected function handleClose($conn): void
    {
        if (! empty($this->clients[$conn->id]['ssh'])) {
            $this->clients[$conn->id]['ssh']->disconnect();
        }
        unset($this->clients[$conn->id]);
    }

    protected function attachUserFromSession($conn, ?string $explicitSessionId = null): void
    {
        $sessionIdEncrypted = null;

        if ($explicitSessionId) {
            $sessionIdEncrypted = $explicitSessionId;
        } else {
            $rawCookies = '';
            if (property_exists($conn, 'httpRequest') && $conn->httpRequest && method_exists($conn->httpRequest, 'header')) {
                $rawCookies = $conn->httpRequest->header('cookie', '');
            }
            $cookies = $this->parseCookies($rawCookies);
            $sessionIdEncrypted = $cookies['laravel_session'] ?? ($cookies['laravel-session'] ?? null);

            if (! $sessionIdEncrypted && property_exists($conn, 'httpRequest') && $conn->httpRequest && method_exists($conn->httpRequest, 'get')) {
                $sessionIdEncrypted = $conn->httpRequest->get('laravel_session', null)
                    ?? $conn->httpRequest->get('session_id', null);
            }

            if (! $sessionIdEncrypted) {
                return;
            }
        }

        try {
            $encrypter = app('encrypter');
            $decrypted = $encrypter->decrypt($sessionIdEncrypted, $unserialize = false);
            $sessionIdPlain = $decrypted;
            if (str_contains($decrypted, '|')) {
                $parts = explode('|', $decrypted);
                $sessionIdPlain = end($parts);
            }
        } catch (\Throwable $e) {
            $conn->close();
            return;
        }

        $guardName = auth()->guard()->getName();
        $store = app('session')->driver();
        try {
            $store->setId($sessionIdPlain);
            $store->start();
        } catch (\Throwable $e) {
            $conn->close();
            return;
        }

        $userId = $store->get($guardName);
        if (! $userId) {
            $conn->close();
            return;
        }

        $conn->userId = $userId;
        $conn->user = User::find($userId);
    }

    protected function parseCookies(string $raw): array
    {
        $cookies = [];
        foreach (explode(';', $raw) as $part) {
            if (str_contains($part, '=')) {
                [$k, $v] = array_map('trim', explode('=', $part, 2));
                $cookies[$k] = urldecode($v);
            }
        }
        return $cookies;
    }

    protected function logLine(string $message, array $context = []): void
    {
        $time = date('Y-m-d H:i:s');
        $ctx = json_encode($context);
        @file_put_contents($this->logFile, "[{$time}] {$message} {$ctx}\n", FILE_APPEND);
    }
}
