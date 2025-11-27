<?php

namespace App\Console\Commands;

use App\Models\Server;
use Illuminate\Console\Command;
use Illuminate\Contracts\Encryption\DecryptException;
use phpseclib3\Net\SSH2;

class TerminalCleanupKeys extends Command
{
    protected $signature = 'terminal:ws:cleanup {serverId? : ID del servidor (opcional, limpia todos si se omite)}';
    protected $description = 'Elimina entradas core-session de authorized_keys en los servidores con servicio SSH';

    public function handle(): int
    {
        $serverId = $this->argument('serverId');
        $query = Server::query()->whereHas('services', fn ($q) => $q->where('is_ssh', true));

        if ($serverId) {
            $query->where('id', $serverId);
        }

        $servers = $query->get();

        if ($servers->isEmpty()) {
            $this->warn('No hay servidores con servicio SSH.');
            return self::SUCCESS;
        }

        $cleaned = 0;
        foreach ($servers as $server) {
            $sshService = $server->sshService;
            if (! $sshService || empty($sshService->username)) {
                $this->line("Omitido {$server->id} ({$server->name}): sin servicio SSH.");
                continue;
            }

            $password = null;
            try {
                $password = $sshService->password;
            } catch (DecryptException $e) {
                $this->line("Omitido {$server->id} ({$server->name}): no se pudo desencriptar la contrasena.");
                continue;
            }

            $target = [
                'host' => $sshService->host ?? $server->ip_address,
                'port' => $sshService->port ?? 22,
                'username' => $sshService->username,
            ];

            try {
                $ssh = new SSH2($target['host'], (int) $target['port']);
                if (! $ssh->login($target['username'], $password)) {
                    $this->line("Omitido {$server->id} ({$server->name}): login SSH fallido.");
                    continue;
                }

                $cmd = <<<BASH
set -e
if [ -f ~/.ssh/authorized_keys ]; then
  tmpFile="\$(mktemp)"
  grep -v 'core-session-' ~/.ssh/authorized_keys > "\$tmpFile" || true
  mv "\$tmpFile" ~/.ssh/authorized_keys
  chmod 600 ~/.ssh/authorized_keys
fi
BASH;
                $ssh->exec($cmd);
                $cleaned++;
                $this->line("Limpio {$server->id} ({$server->name})");
            } catch (\Throwable $e) {
                $this->line("Error en {$server->id} ({$server->name}): {$e->getMessage()}");
            }
        }

        $this->info("Limpieza finalizada. Servidores procesados: {$cleaned}");

        return self::SUCCESS;
    }
}
