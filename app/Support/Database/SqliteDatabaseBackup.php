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
