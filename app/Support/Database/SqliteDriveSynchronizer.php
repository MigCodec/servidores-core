<?php

namespace App\Support\Database;

use App\Support\GoogleDrive\DriveClientFactory;
use Google\Service\Drive\DriveFile;
use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class SqliteDriveSynchronizer
{
    protected static bool $synced = false;

    public function __construct(protected DatabaseManager $database)
    {
    }

    public function sync(bool $force = false): array
    {
        if (self::$synced && ! $force) {
            return [
                'remote_to_local' => 0,
            ];
        }

        $masterFileId = config('services.google_drive.master_file_id');

        if (blank($masterFileId)) {
            throw new RuntimeException('Configure GOOGLE_DRIVE_MASTER_FILE_ID with the Drive file id to sync against.');
        }

        $drive = DriveClientFactory::make('SQLite Sync');
        $remotePath = $this->downloadMaster($drive, $masterFileId);

        try {
            $summary = $this->mergeRemoteIntoLocal($remotePath);
            $this->uploadLocalSnapshot($drive, $masterFileId);
        } finally {
            File::delete($remotePath);
        }

        self::$synced = true;

        return $summary;
    }

    protected function downloadMaster($drive, string $fileId): string
    {
        $destination = storage_path('app/drive-sync/master.sqlite');
        File::ensureDirectoryExists(dirname($destination));

        $response = $drive->files->get($fileId, [
            'alt' => 'media',
            'supportsAllDrives' => true,
        ]);
        File::put($destination, $response->getBody()->getContents());

        return $destination;
    }

    protected function mergeRemoteIntoLocal(string $remotePath): array
    {
        $localConnectionName = $this->database->getDefaultConnection();
        $localConnection = $this->database->connection($localConnectionName);

        $remoteConnectionName = 'drive_master';
        config([
            "database.connections.{$remoteConnectionName}" => [
                'driver' => 'sqlite',
                'database' => $remotePath,
                'prefix' => '',
            ],
        ]);

        $remoteConnection = $this->database->connection($remoteConnectionName);

        $tables = $this->tablesToSync($remoteConnection, $localConnectionName);

        $imported = 0;
        foreach ($tables as $table) {
            $imported += $this->copyMissingRows($remoteConnection, $localConnection, $table, $remoteConnectionName, $localConnectionName);
        }

        DB::purge($remoteConnectionName);

        return [
            'remote_to_local' => $imported,
        ];
    }

    protected function tablesToSync(Connection $remote, string $localConnectionName): Collection
    {
        $names = collect($remote->select("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'"))
            ->pluck('name')
            ->reject(fn ($table) => in_array($table, $this->ignoredTables()))
            ->filter(fn ($table) => Schema::connection($localConnectionName)->hasTable($table));

        return $names->values();
    }

    protected function ignoredTables(): array
    {
        return [
            'migrations',
            'cache',
            'jobs',
            'failed_jobs',
            'sessions',
            'password_reset_tokens',
            'personal_access_tokens',
        ];
    }

    protected function copyMissingRows(
        Connection $source,
        Connection $target,
        string $table,
        string $sourceConnectionName,
        string $targetConnectionName
    ): int {
        if (! Schema::connection($sourceConnectionName)->hasColumn($table, 'id')) {
            return 0;
        }

        if (! Schema::connection($targetConnectionName)->hasColumn($table, 'id')) {
            return 0;
        }

        $existingIds = $target->table($table)->pluck('id')->all();

        $rowsQuery = $source->table($table);

        if (! empty($existingIds)) {
            $rowsQuery->whereNotIn('id', $existingIds);
        }

        $rows = $rowsQuery->get();

        if ($rows->isEmpty()) {
            return 0;
        }

        $target->table($table)->insert(
            $rows->map(fn ($row) => (array) $row)->all()
        );

        return $rows->count();
    }

    protected function uploadLocalSnapshot($drive, string $fileId): void
    {
        $localPath = $this->localDatabasePath();

        $drive->files->update(
            $fileId,
            new DriveFile(),
            [
                'data' => File::get($localPath),
                'mimeType' => 'application/x-sqlite3',
                'uploadType' => 'media',
                'fields' => 'id',
                'supportsAllDrives' => true,
            ]
        );
    }

    protected function localDatabasePath(): string
    {
        $path = config('database.connections.sqlite.database');

        if (! is_string($path) || $path === '') {
            throw new RuntimeException('The SQLite database path is not configured.');
        }

        if (! File::exists($path)) {
            throw new RuntimeException("SQLite database file not found at {$path}.");
        }

        return $path;
    }
}
