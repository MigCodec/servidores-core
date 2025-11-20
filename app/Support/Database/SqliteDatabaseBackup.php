<?php

namespace App\Support\Database;

use App\Support\GoogleDrive\DriveClientFactory;
use Google\Service\Drive\DriveFile;
use Illuminate\Support\Facades\File;
use RuntimeException;

class SqliteDatabaseBackup
{
    /**
     * Creates a fresh copy of the SQLite database and uploads it to Google Drive.
     *
     * @return array{file_id: string, file_name: string}
     */
    public function backupToDrive(): array
    {
        $sqlitePath = $this->databasePath();
        $backupPath = $this->createSnapshot($sqlitePath);

        try {
            $fileName = basename($backupPath);
            $fileId = $this->uploadToDrive($backupPath, $fileName);
        } finally {
            File::delete($backupPath);
        }

        return [
            'file_id' => $fileId,
            'file_name' => $fileName,
        ];
    }

    public function listBackups(int $limit = 20): array
    {
        $drive = DriveClientFactory::make('SQLite Backup List');

        $query = "mimeType = 'application/x-sqlite3' and trashed = false";

        if ($folderId = config('services.google_drive.folder_id')) {
            $query .= sprintf(" and '%s' in parents", $folderId);
        }

        $files = $drive->files->listFiles([
            'q' => $query,
            'orderBy' => 'modifiedTime desc',
            'pageSize' => $limit,
            'fields' => 'files(id, name, modifiedTime, createdTime, size)',
            'supportsAllDrives' => true,
            'includeItemsFromAllDrives' => true,
        ]);

        return array_map(function (DriveFile $file) {
            return [
                'id' => $file->getId(),
                'name' => $file->getName(),
                'modifiedTime' => $file->getModifiedTime(),
                'createdTime' => $file->getCreatedTime(),
                'size' => $file->getSize(),
            ];
        }, $files->getFiles() ?? []);
    }

    public function restoreFromDrive(string $fileId): array
    {
        $drive = DriveClientFactory::make('SQLite Restore');
        $metadata = $drive->files->get($fileId, [
            'fields' => 'id, name',
            'supportsAllDrives' => true,
        ]);

        $tempPath = storage_path('app/backups/restore-'.$fileId.'.sqlite');
        File::ensureDirectoryExists(dirname($tempPath));

        $previousBackup = null;

        try {
            $response = $drive->files->get($fileId, [
                'alt' => 'media',
                'supportsAllDrives' => true,
            ]);

            File::put($tempPath, $response->getBody()->getContents());

            $databasePath = $this->databasePath();
            $previousBackup = $this->createSnapshot($databasePath);

            if (! File::copy($tempPath, $databasePath)) {
                throw new RuntimeException('No se pudo sobrescribir la base de datos local.');
            }
        } finally {
            File::delete($tempPath);
        }

        return [
            'file_id' => $metadata->getId(),
            'file_name' => $metadata->getName(),
            'local_backup' => $previousBackup ? basename($previousBackup) : null,
        ];
    }

    protected function databasePath(): string
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

    protected function createSnapshot(string $sqlitePath): string
    {
        $backupDirectory = storage_path('app/backups');
        File::ensureDirectoryExists($backupDirectory);

        $fileName = sprintf(
            'sqlite-backup-%s.sqlite',
            now()->setTimezone('UTC')->format('Ymd_His')
        );

        $destination = $backupDirectory.DIRECTORY_SEPARATOR.$fileName;

        if (! File::copy($sqlitePath, $destination)) {
            throw new RuntimeException('Unable to copy the SQLite database to a temporary backup file.');
        }

        return $destination;
    }

    protected function uploadToDrive(string $localPath, string $fileName): string
    {
        $drive = DriveClientFactory::make('SQLite Backup');

        $metadata = new DriveFile([
            'name' => $fileName,
        ]);

        if ($folderId = config('services.google_drive.folder_id')) {
            $metadata->setParents([$folderId]);
        }

        $file = $drive->files->create(
            $metadata,
            [
                'data' => File::get($localPath),
                'mimeType' => 'application/x-sqlite3',
                'uploadType' => 'multipart',
                'fields' => 'id, name',
                'supportsAllDrives' => true,
            ]
        );

        return $file->id;
    }
}
