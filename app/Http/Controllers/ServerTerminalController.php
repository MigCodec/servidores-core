<?php

namespace App\Http\Controllers;

use App\Models\Server;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Contracts\Encryption\DecryptException;
use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Net\SSH2;

class ServerTerminalController extends Controller
{
    public function store(Request $request, Server $server)
    {
        $this->authorize('view', $server);
        $this->authorize('viewCredentials', $server);

        $sshService = $server->sshService;
        if (! $sshService || empty($sshService->username)) {
            return response()->json([
                'message' => 'Este servidor no tiene un servicio SSH configurado con usuario.',
            ], 422);
        }

        $data = $request->validate([
            'public_key' => ['required', 'string', 'max:8192'],
            'algorithm' => ['nullable', 'string', 'max:50'],
            'fingerprint' => ['nullable', 'string', 'max:255'],
        ]);

        $sessionId = (string) Str::uuid();
        $expiresAt = now()->addMinutes(10);
        $comment = "core-session-{$sessionId}";

        $target = [
            'host' => $sshService->host ?? $server->ip_address,
            'port' => $sshService->port ?? 22,
            'username' => $sshService->username,
        ];

        try {
            $openSshKey = $this->toOpenSshKey($data['public_key'], $comment);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'No se pudo procesar la clave publica: '.$e->getMessage(),
            ], 422);
        }

        try {
            $password = null;
            try {
                $password = $sshService->password;
            } catch (DecryptException $e) {
                // Si la clave es invalida no podemos subir la publica
            }
            $this->addAuthorizedKey($target, $password, $openSshKey, $comment);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'No se pudo registrar la clave publica en el servidor: '.$e->getMessage(),
            ], 422);
        }

        $session = [
            'id' => $sessionId,
            'server_id' => $server->id,
            'public_key' => $data['public_key'],
            'fingerprint' => $data['fingerprint'] ?? hash('sha256', $data['public_key']),
            'algorithm' => $data['algorithm'] ?? 'rsa',
            'created_by' => $request->user()->id,
            'target' => $target,
            'authorized_comment' => $comment,
        ];

        Cache::put($this->cacheKey($server, $sessionId), $session, $expiresAt);

        return response()->json([
            'session' => array_merge($session, [
                'expires_at' => $expiresAt->toIso8601String(),
            ]),
            'message' => 'Sesion preparada. Falta abrir el canal WebSocket y registrar la clave en authorized_keys.',
        ]);
    }

    public function destroy(Request $request, Server $server, string $sessionId)
    {
        $this->authorize('view', $server);
        $this->authorize('viewCredentials', $server);

        $session = Cache::pull($this->cacheKey($server, $sessionId));
        $comment = $session['authorized_comment'] ?? "core-session-{$sessionId}";

        $sshService = $server->sshService;
        if ($sshService) {
            $target = [
                'host' => $sshService->host ?? $server->ip_address,
                'port' => $sshService->port ?? 22,
                'username' => $sshService->username,
            ];

            try {
                $password = null;
                try {
                    $password = $sshService->password;
                } catch (DecryptException $e) {
                    // noop
                }
                $this->removeAuthorizedKey($target, $password, $comment);
            } catch (\Throwable $e) {
                // Si no se puede limpiar, ignoramos para no bloquear la UI
            }
        }

        return response()->noContent();
    }

    public function view(Server $server)
    {
        $this->authorize('view', $server);
        $this->authorize('viewCredentials', $server);

        return view('servers.terminal', [
            'server' => $server->load('services'),
        ]);
    }

    protected function cacheKey(Server $server, string $sessionId): string
    {
        return "terminal-session:{$server->id}:{$sessionId}";
    }

    protected function toOpenSshKey(string $publicKeyPem, string $comment): string
    {
        $loaded = PublicKeyLoader::load($publicKeyPem);

        return trim($loaded->toString('OpenSSH', [
            'comment' => $comment,
        ]));
    }

    protected function addAuthorizedKey(array $target, ?string $password, string $openSshKey, string $comment): void
    {
        if (blank($password)) {
            throw new \RuntimeException('No hay contrasena SSH para subir la clave publica.');
        }

        $ssh = new SSH2($target['host'], (int) $target['port']);
        if (! $ssh->login($target['username'], $password)) {
            throw new \RuntimeException('No se pudo autenticar al servidor SSH con las credenciales almacenadas.');
        }

        $escapedKey = escapeshellarg($openSshKey);
        $escapedComment = escapeshellarg($comment);
        $command = <<<BASH
            set -e
            mkdir -p ~/.ssh
            chmod 700 ~/.ssh
            touch ~/.ssh/authorized_keys
            chmod 600 ~/.ssh/authorized_keys
            # Evita duplicar la entrada
            if ! grep -q $escapedComment ~/.ssh/authorized_keys; then
              echo $escapedKey >> ~/.ssh/authorized_keys
            fi
        BASH;

        $ssh->exec($command);
    }

    protected function removeAuthorizedKey(array $target, ?string $password, string $comment): void
    {
        if (blank($password)) {
            return;
        }

        $ssh = new SSH2($target['host'], (int) $target['port']);
        if (! $ssh->login($target['username'], $password)) {
            return;
        }

        $escapedComment = escapeshellarg($comment);
        $command = <<<BASH
            set -e
            if [ -f ~/.ssh/authorized_keys ]; then
              tmpFile="\$(mktemp)"
              grep -v $escapedComment ~/.ssh/authorized_keys > "\$tmpFile" || true
              mv "\$tmpFile" ~/.ssh/authorized_keys
              chmod 600 ~/.ssh/authorized_keys
            fi
        BASH;

        $ssh->exec($command);
    }
}
