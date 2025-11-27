<?php

namespace Tests\Feature;

use App\Console\Commands\TerminalWebSocket;
use App\Models\Server;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use ReflectionMethod;
use ReflectionProperty;
use Tests\TestCase;

class TerminalWebSocketTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.ws_fake_ssh' => true]);
    }

    /** @test */
    public function it_authenticates_via_session_token_in_init_message(): void
    {
        $user = User::factory()->create();
        $server = Server::create([
            'name' => 'Srv',
            'ip_address' => '192.168.0.10',
            'ram_gb' => 4,
            'storage_gb' => 20,
            'is_physical' => true,
        ]);

        $sessionId = 'test-session-id';
        $guardKey = auth()->guard()->getName();
        $store = app('session')->driver();
        $store->setId($sessionId);
        $store->start();
        $store->put($guardKey, $user->getAuthIdentifier());
        $store->save();

        $terminalSessionId = 'abc-123';
        Cache::put("terminal-session:{$server->id}:{$terminalSessionId}", [
            'id' => $terminalSessionId,
            'server_id' => $server->id,
            'created_by' => $user->id,
            'target' => [
                'host' => $server->ip_address,
                'port' => 22,
                'username' => 'root',
            ],
        ], now()->addMinutes(5));

        $conn = new FakeConnection();

        $command = app(TerminalWebSocket::class);
        $this->setLogFile($command);

        $message = json_encode([
            'type' => 'init',
            'server_id' => $server->id,
            'session_id' => $terminalSessionId,
            'private_key' => file_get_contents(base_path('tests/stubs/private_rsa.pem')),
            'session_token' => $sessionId,
        ]);

        $this->invokeHandleMessage($command, $conn, $message);

        $this->assertSame($user->id, $conn->userId, 'El usuario no se asigno en la conexion.');
        $this->assertFalse($conn->closed, 'La conexion no debio cerrarse.');
        $this->assertTrue($conn->sentHasType('ready'), 'El WS debio enviar mensaje ready.');
    }

    /** @test */
    public function it_closes_when_init_without_session_token_or_cookie(): void
    {
        $user = User::factory()->create();
        $server = Server::create([
            'name' => 'Srv',
            'ip_address' => '192.168.0.10',
            'ram_gb' => 4,
            'storage_gb' => 20,
            'is_physical' => true,
        ]);

        $terminalSessionId = 'abc-123';
        Cache::put("terminal-session:{$server->id}:{$terminalSessionId}", [
            'id' => $terminalSessionId,
            'server_id' => $server->id,
            'created_by' => $user->id,
            'target' => [
                'host' => $server->ip_address,
                'port' => 22,
                'username' => 'root',
            ],
        ], now()->addMinutes(5));

        $conn = new FakeConnection();

        $command = app(TerminalWebSocket::class);
        $this->setLogFile($command);

        $message = json_encode([
            'type' => 'init',
            'server_id' => $server->id,
            'session_id' => $terminalSessionId,
            'private_key' => file_get_contents(base_path('tests/stubs/private_rsa.pem')),
        ]);

        $this->invokeHandleMessage($command, $conn, $message);

        $this->assertTrue($conn->closed, 'La conexion debio cerrarse al no autenticar.');
        $this->assertTrue($conn->sentHasType('error'), 'Debio enviarse un error de autenticacion.');
    }

    protected function invokeHandleMessage(TerminalWebSocket $command, $conn, string $message): void
    {
        $method = new ReflectionMethod(TerminalWebSocket::class, 'handleMessage');
        $method->setAccessible(true);
        $method->invoke($command, $conn, $message);
    }

    protected function setLogFile(TerminalWebSocket $command): void
    {
        $prop = new ReflectionProperty(TerminalWebSocket::class, 'logFile');
        $prop->setAccessible(true);
        $prop->setValue($command, storage_path('logs/terminal-ws-test.log'));
    }
}

class FakeConnection
{
    public int $id = 1;
    public ?int $userId = null;
    public $user = null;
    public bool $closed = false;
    public array $sent = [];
    public $httpRequest;

    public function __construct()
    {
        $this->httpRequest = new FakeHttpRequest();
    }

    public function send($msg): void
    {
        $this->sent[] = $msg;
    }

    public function close(): void
    {
        $this->closed = true;
    }

    public function sentHasType(string $type): bool
    {
        foreach ($this->sent as $msg) {
            $decoded = json_decode($msg, true);
            if (($decoded['type'] ?? null) === $type) {
                return true;
            }
        }

        return false;
    }
}

class FakeHttpRequest
{
    protected array $headers = [];
    protected array $params = [];

    public function header(string $key, $default = null)
    {
        return $this->headers[$key] ?? $default;
    }

    public function get(string $key = null, $default = null)
    {
        if ($key === null) {
            return $this->params;
        }

        return $this->params[$key] ?? $default;
    }
}
