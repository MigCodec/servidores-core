<?php

namespace Tests\Feature;

use App\Console\Commands\TerminalWebSocket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use ReflectionProperty;
use Tests\TestCase;

class TerminalWebSocketAuthTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_attaches_user_from_session_cookie(): void
    {
        config(['session.driver' => 'array']);

        $user = User::factory()->create();
        $sessionId = 'test-session-id';
        $guardName = auth()->guard()->getName();

        // Prepara el store de sesion con el usuario
        $store = app('session')->driver();
        $store->setId($sessionId);
        $store->start();
        $store->put($guardName, $user->id);
        $store->save();

        $encryptedCookie = app('encrypter')->encrypt($sessionId, $serialize = false);
        $cookieHeader = "laravel-session={$encryptedCookie}";

        $conn = new FakeWsConnection($cookieHeader);

        $command = app(TerminalWebSocket::class);
        $this->setLogFile($command);

        $this->invokeAttachUser($command, $conn);

        $this->assertSame($user->id, $conn->userId);
        $this->assertNotNull($conn->user);
    }

    /** @test */
    public function it_closes_when_cookie_missing(): void
    {
        config(['session.driver' => 'array']);

        $conn = new FakeWsConnection('');
        $command = app(TerminalWebSocket::class);
        $this->setLogFile($command);

        $this->invokeAttachUser($command, $conn);

        $this->assertNull($conn->userId);
        $this->assertFalse($conn->closed);
    }

    protected function invokeAttachUser(TerminalWebSocket $command, $conn): void
    {
        $method = new ReflectionMethod(TerminalWebSocket::class, 'attachUserFromSession');
        $method->setAccessible(true);
        $method->invoke($command, $conn, null);
    }

    protected function setLogFile(TerminalWebSocket $command): void
    {
        $prop = new ReflectionProperty(TerminalWebSocket::class, 'logFile');
        $prop->setAccessible(true);
        $prop->setValue($command, storage_path('logs/terminal-ws-test.log'));
    }
}

class FakeWsConnection
{
    public int $id = 1;
    public ?int $userId = null;
    public $user = null;
    public bool $closed = false;
    public FakeWsRequest $httpRequest;

    public function __construct(string $cookieHeader)
    {
        $this->httpRequest = new FakeWsRequest($cookieHeader);
    }

    public function send($msg): void
    {
        // noop para pruebas
    }

    public function close(): void
    {
        $this->closed = true;
    }
}

class FakeWsRequest
{
    public function __construct(protected string $cookieHeader)
    {
    }

    public function header(string $name = null, $default = '')
    {
        if ($name === 'cookie') {
            return $this->cookieHeader;
        }

        return $default;
    }
}
