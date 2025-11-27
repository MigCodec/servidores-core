<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class TerminalSupervisorConfig extends Command
{
    protected $signature = 'terminal:ws:supervisor
        {--port=7001 : Puerto del WebSocket}
        {--user=www-data : Usuario del proceso}
        {--program=terminal-ws : Nombre del programa en Supervisor}
        {--dir= : Directorio base del proyecto (por defecto base_path())}
        {--log-dir= : Directorio de logs (por defecto storage/logs)}
        {--output= : Ruta donde guardar el archivo generado (por defecto storage/app/<program>.conf)}';

    protected $description = 'Genera un archivo de configuracion para Supervisor que levanta terminal:ws';

    public function handle(): int
    {
        $port = (int) $this->option('port');
        $user = $this->option('user') ?: 'www-data';
        $program = $this->option('program') ?: 'terminal-ws';
        $dir = rtrim($this->option('dir') ?: base_path(), DIRECTORY_SEPARATOR);
        $logDir = rtrim($this->option('log-dir') ?: storage_path('logs'), DIRECTORY_SEPARATOR);
        $output = $this->option('output') ?: storage_path("app/{$program}.conf");

        $config = <<<CONF
[program:{$program}]
command=/usr/bin/php {$dir}/artisan terminal:ws --port={$port}
directory={$dir}
autostart=true
autorestart=true
user={$user}
stopasgroup=true
killasgroup=true
stdout_logfile={$logDir}/{$program}.stdout.log
stderr_logfile={$logDir}/{$program}.stderr.log
CONF;

        File::ensureDirectoryExists(dirname($output));
        File::put($output, $config);

        $this->info("Archivo generado: {$output}");
        $this->line("Copia este archivo a /etc/supervisor/conf.d/ y luego ejecuta: supervisorctl reread && supervisorctl update && supervisorctl start {$program}");

        return self::SUCCESS;
    }
}
