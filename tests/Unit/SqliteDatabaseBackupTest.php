<?php

namespace Tests\Unit;

use App\Support\Database\SqliteDatabaseBackup;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Mockery;
use Tests\TestCase;

class SqliteDatabaseBackupTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_it_creates_snapshot_and_uploads_to_drive(): void
    {
        Carbon::setTestNow(
            Carbon::create(2025, 1, 2, 3, 4, 5, 'UTC')
        );

        $databasePath = storage_path('framework/testing/database.sqlite');
        File::ensureDirectoryExists(dirname($databasePath));
        File::put($databasePath, 'sqlite-content');

        config()->set('database.connections.sqlite.database', $databasePath);

        $backupDirectory = storage_path('app/backups');
        if (File::exists($backupDirectory)) {
            File::deleteDirectory($backupDirectory);
        }

        $expectedFileName = 'sqlite-backup-20250102_030405.sqlite';

        $testCase = $this;
        $uploadedPath = null;

        $backup = Mockery::mock(SqliteDatabaseBackup::class)->makePartial();
        $backup->shouldAllowMockingProtectedMethods();

        $backup->shouldReceive('uploadToDrive')
            ->once()
            ->andReturnUsing(function (string $path, string $fileName) use ($expectedFileName, $testCase, &$uploadedPath) {
                $testCase->assertSame($expectedFileName, $fileName);
                $testCase->assertFileExists($path);
                $uploadedPath = $path;

                return 'drive-file-id';
            });

        $result = $backup->backupToDrive();

        $this->assertSame('drive-file-id', $result['file_id']);
        $this->assertSame($expectedFileName, $result['file_name']);

        $this->assertNotNull($uploadedPath);
        $this->assertFileDoesNotExist($uploadedPath);
    }
}
