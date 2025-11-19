<?php

use App\Support\Database\SqliteDatabaseBackup;
use App\Support\Database\SqliteDriveSynchronizer;
use App\Support\EnvEditor;
use App\Support\GoogleDrive\DriveClientFactory;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('db:backup-drive', function () {
    /** @var \App\Support\Database\SqliteDatabaseBackup $backup */
    $backup = app(SqliteDatabaseBackup::class);
    $result = $backup->backupToDrive();

    $this->info(sprintf(
        'Backup %s uploaded to Google Drive (file id: %s).',
        $result['file_name'],
        $result['file_id']
    ));
})->purpose('Create a copy of the SQLite database and upload it to Google Drive');

Artisan::command('db:sync-drive', function () {
    /** @var \App\Support\Database\SqliteDriveSynchronizer $synchronizer */
    $synchronizer = app(SqliteDriveSynchronizer::class);
    $result = $synchronizer->sync(force: true);

    $this->info(sprintf(
        'Sincronización completada. Registros importados desde Drive: %d',
        $result['remote_to_local']
    ));
})->purpose('Sincroniza el SQLite local con la copia maestra de Google Drive');

Artisan::command('db:init-drive-master', function () {
    $localPath = database_path('database.sqlite');

    if (! File::exists($localPath)) {
        $this->error('No se encontró database/database.sqlite. Ejecuta las migraciones antes de crear el maestro.');

        return self::FAILURE;
    }

    $drive = DriveClientFactory::make('SQLite Master Init');

    $metadata = new \Google\Service\Drive\DriveFile([
        'name' => 'database.sqlite',
    ]);

    if ($folderId = config('services.google_drive.folder_id')) {
        $metadata->setParents([$folderId]);
    }

    $file = $drive->files->create(
        $metadata,
        [
            'data' => File::get($localPath),
            'mimeType' => 'application/x-sqlite3',
            'uploadType' => 'media',
            'fields' => 'id, name',
            'supportsAllDrives' => true,
        ]
    );

    EnvEditor::set('GOOGLE_DRIVE_MASTER_FILE_ID', $file->id);

    $this->info(sprintf(
        'Archivo maestro subido correctamente (ID: %s). El valor también se escribió en tu .env.',
        $file->id
    ));

    return self::SUCCESS;
})->purpose('Sube la base SQLite actual como archivo maestro y guarda el ID en el .env');

Artisan::command('drive:oauth:init', function () {
    $client = DriveClientFactory::makeForAuthorization();
    $redirectUri = $client->getRedirectUri();

    if (! $redirectUri) {
        $redirectUri = $this->ask(
            'No hay redirect URIs configuradas en el JSON. Ingresa la redirect URI autorizada (por ejemplo http://localhost)',
            'http://localhost'
        );

        $client = DriveClientFactory::makeForAuthorization($redirectUri);
    }

    $client->setPrompt('consent');

    $url = $client->createAuthUrl();

    $this->info('Abre esta URL en tu navegador, autoriza el acceso y pega el código que Google te dé:');
    $this->line($url);

    $code = $this->ask('Código de autorización');

    if (blank($code)) {
        $this->error('No se recibió ningún código. Intenta nuevamente.');

        return self::FAILURE;
    }

    $token = $client->fetchAccessTokenWithAuthCode($code);

    if (isset($token['error'])) {
        $this->error(sprintf(
            'No se pudo intercambiar el código: %s',
            $token['error_description'] ?? $token['error']
        ));

        return self::FAILURE;
    }

    if (empty($token['refresh_token'])) {
        $this->warn('Google no devolvió refresh_token. Asegúrate de marcar access_type=offline y prompt=consent y vuelve a intentarlo.');

        return self::FAILURE;
    }

    $this->info('Refresh token obtenido correctamente:');
    $this->line($token['refresh_token']);

    $this->newLine();
    $this->info('Copia ese valor en la variable GOOGLE_DRIVE_REFRESH_TOKEN de tu .env');

    return self::SUCCESS;
})->purpose('Genera un refresh token OAuth para Google Drive');
