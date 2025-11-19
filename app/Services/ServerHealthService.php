<?php

namespace App\Services;

use App\Models\Server;
use App\Models\ServerHealthLog;
use Illuminate\Support\Collection;
use phpseclib3\Net\SSH2;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class ServerHealthService
{
    public function refresh(Collection $servers): array
    {
        $records = [];

        foreach ($servers as $server) {
            $records[$server->id] = $this->inspect($server);
        }

        return [
            'generated_at' => now()->toIso8601String(),
            'records' => $records,
        ];
    }

    public function inspect(Server $server): array
    {
        if ($server->in_maintenance) {
            $result = [
                'status' => 'maintenance',
                'latency_ms' => null,
                'ssh' => ['connected' => false, 'error' => 'En mantenimiento'],
            ];

            $this->storeLog($server, $result);

            return $result;
        }

        $ping = $this->ping($server->ip_address);

        $ssh = null;
        if ($server->ssh_username) {
            $ssh = $this->fetchSshMetrics($server);
        }

        $result = [
            'status' => $ping['status'],
            'latency_ms' => $ping['latency'],
            'ssh' => $ssh,
        ];

        $this->storeLog($server, $result);

        return $result;
    }

    protected function ping(string $ip): array
    {
        $command = PHP_OS_FAMILY === 'Windows'
            ? ['ping', '-n', '1', '-w', '1000', $ip]
            : ['ping', '-c', '1', '-W', '1', $ip];

        try {
            $process = new Process($command);
            $process->setTimeout(3);
            $process->run();
        } catch (ProcessFailedException $e) {
            return [
                'status' => 'down',
                'latency' => null,
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 'down',
                'latency' => null,
            ];
        }

        if (! $process->isSuccessful()) {
            return [
                'status' => 'down',
                'latency' => null,
            ];
        }

        $latency = null;
        if (preg_match('/time[=<]([\d\.]+)/i', $process->getOutput(), $matches)) {
            $latency = (float) $matches[1];
        }

        return [
            'status' => 'up',
            'latency' => $latency,
        ];
    }

    protected function fetchSshMetrics(Server $server): array
    {
        $host = $server->ssh_host ?: $server->ip_address;
        $port = $server->ssh_port ?: 22;

        $result = [
            'connected' => false,
            'error' => null,
            'ram' => null,
            'cpu' => null,
        ];

        if (! $host || ! $server->ssh_username) {
            return $result;
        }

        try {
            $ssh = new SSH2($host, $port, 5);

            $password = $server->ssh_password;

            if (! $password) {
                $result['error'] = 'Sin contraseña SSH configurada.';

                return $result;
            }

            if (! $ssh->login($server->ssh_username, $password)) {
                $result['error'] = 'No se pudo autenticar vía SSH.';

                return $result;
            }

            $result['connected'] = true;
            $result['ram'] = $this->parseRamUsage($ssh->exec("LANG=C free -m | awk 'NR==2 {print \$2 \" \" \$3}'"));
            $result['cpu'] = $this->parseCpuLoad($ssh->exec('LANG=C uptime'));
            $result['services'] = $this->checkCriticalServices($server, $ssh);
        } catch (\Throwable $e) {
            $result['error'] = $e->getMessage();
        }

        return $result;
    }

    protected function parseRamUsage(?string $output): ?array
    {
        if (! $output) {
            return null;
        }

        $parts = preg_split('/\s+/', trim($output));

        if (count($parts) < 2) {
            return null;
        }

        $total = (int) $parts[0];
        $used = (int) $parts[1];

        if ($total <= 0) {
            return null;
        }

        $percentage = round(($used / $total) * 100, 1);

        return [
            'total_mb' => $total,
            'used_mb' => $used,
            'usage_percent' => $percentage,
        ];
    }

    protected function parseCpuLoad(?string $output): ?array
    {
        if (! $output) {
            return null;
        }

        if (! preg_match('/load average[s]?:\s*([\d\.]+),\s*([\d\.]+),\s*([\d\.]+)/i', $output, $matches)) {
            return null;
        }

        return [
            'load1' => (float) $matches[1],
            'load5' => (float) $matches[2],
            'load15' => (float) $matches[3],
        ];
    }

    protected function checkCriticalServices(Server $server, SSH2 $ssh): ?array
    {
        $services = $server->critical_services ?? [];

        if ($services === [] || ! is_array($services)) {
            return null;
        }

        $statuses = [];

        foreach ($services as $service) {
            $command = sprintf('LANG=C systemctl is-active %s 2>/dev/null || echo unknown', escapeshellarg($service));
            $status = trim($ssh->exec($command));
            $statuses[$service] = $status ?: 'unknown';
        }

        return $statuses;
    }

    protected function storeLog(Server $server, array $result): void
    {
        try {
            ServerHealthLog::create([
                'server_id' => $server->id,
                'status' => $result['status'],
                'latency_ms' => $result['latency_ms'],
                'ssh_connected' => $result['ssh']['connected'] ?? false,
                'ram_usage_percent' => $result['ssh']['ram']['usage_percent'] ?? null,
                'cpu_load1' => $result['ssh']['cpu']['load1'] ?? null,
                'services_status' => $result['ssh']['services'] ?? null,
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
