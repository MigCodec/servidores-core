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
            'private_key' => $this->fakePrivateKey(),
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
            'private_key' => $this->fakePrivateKey(),
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

    protected function fakePrivateKey(): string
    {
        return <<<PEM
-----BEGIN PRIVATE KEY-----
MIIEvQIBADANBgkqhkiG9w0BAQEFAASCBKcwggSjAgEAAoIBAQC0FhGk6mH6S/A+
rWJpXpr84fxcqnMJlJo1mCx24WEYmP28WQFOyHRiPvzLXzRLb5ykUWb3PG7wyyZh
Grx2cQ2ZOVzDN2B4mgFO2L68TpgfCTnM25kZ4P6nmR4YtMdEecyHJre7iSf02qV/
OjGmf9HwodPNP9Yc5CWVVRMGGlAb3wKuj3aFh8/lqmP4pZ2FEE3GSKYt9u3Pe25B
GbgYORsv9Oma3A7jkaLa2TLlB0pFpnDXk7yBZnWQwQeQVHNYSkNc9aC/7Qfo+D3q
aP9gpVFY2cPtVzGOmd5H3Kfl0H9W/FfNulctdDv9eg0zVkD95pRKmuGB4AB+VzzG
zPsaETQpAgMBAAECggEAEzWnkOpswgmmYFaw3Pdz3E5CbCZEkua9MQVAx53oPwsx
1hY4DvpW0L3lrfLLk8J1kCpVx8fawQM7JWvkK7uuaDkzjNqnksz0Pmg0CVvAptwo
oPGFIB2BMsMmztGxG2rNlrq/v3jVl96vavUr+b2YTY5sAro41JAfL+BP++0guMHJ
DR6WzxngFKzopkI3NTATcyGXKtJRjtR9LI3jmWRnxpXzQpvlNXSV6gx+efXFXoJH
DzAnpaX6HxsNAqms1lJwNX61NlR4dvOMnuyrTE+lLyPkHPg9DqNwhJjRhu/gu8e6
1e9T42f0qo9L81QmL1mK2OVGgpLloLgP9EqN1ABswQKBgQDjT2wGnux4CG7AvX8A
JQv4xGyJh8HUXsC2zNV3gD+kmkACJpFu5lwOK5J9s3xhqGCBYvgilAto6jD34kNL
r3D7YpF/N/QUTt7EHeqom1N1tUJKNObHQs/iuORbufGAbU7oO4HzCibm8c9e6nSK
7zeDcOs6exm5WZb9XZQNQtLPmwKBgQDQqBiZf+9ABwLx6fM30dnnY/LI/OqiJ2c7
eUMw0+4fWcDjo1cJ1D6PXgdKqr9pzcVZ5h/A1q6vBmvQ/93s+9XoMh/mbHgAiiZJ
eCDK7aG4t+XjB6XmtN5LF7Mm3FQH46qOjodj79K37Vl9YLFhnf1CR7OeYpMuu16g
irgbgo4q/wKBgFnAtkaLai3T1+B2ptC8oSKNcuYyQDcAJEK70OnflPexNFRWAidq
0NAJhw3Vr7jyMoivzSiuNrLn9BQw5NPt75MvWCVO7mKbM5M0Q1OxThsv3DDulORY
Zs4de1tHkU5yIF5/kvHdq0OQWbZtURjiOt+IZRRIl3u6keDbmkQzijvFAoGBAJ/x
8UMKPSl8CBP3sC9Z3GIBtKqlX5XSXukrbCpUB9XoBZEkIxNavQJRLqZ08b8YOgDs
HhrlkI+RgoyFBoPAc2z/wPtuTcfBfM5UeXwbEs6SQZKOkRW+21G9l729YUXpX8E6
fpYKF1sp81Qwc98BOHHaOgOi0z6ThTGefTdPaAZ1AoGAJJeJJhAjYZAEAScYXZH4
Zm9hH2WBgpD0YqxhssOVBJ4Q0W3dn9UM1J1j6ZJ/2Bm8WeZ+6V4HF4jCblM9j3vy
UqPCRK3kBFfJtqglUTGuLtChfHTm0cIzyNQOJbK/sYqGb/9p1fAp65T6JwbHSE+S
GqLI4OxgsS+dYizLRQJRwvQ=
-----END PRIVATE KEY-----
PEM;
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
