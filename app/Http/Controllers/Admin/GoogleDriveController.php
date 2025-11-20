<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Database\SqliteDatabaseBackup;
use App\Support\Database\SqliteDriveSynchronizer;
use App\Support\EnvEditor;
use App\Support\GoogleDrive\DriveClientFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class GoogleDriveController extends Controller
{
    protected function authorizeAdmin(Request $request): void
    {
        if (! method_exists($request->user(), 'isAdmin') || ! $request->user()->isAdmin()) {
            abort(403);
        }
    }

    public function index(Request $request, SqliteDatabaseBackup $backup)
    {
        $this->authorizeAdmin($request);

        $backups = [];
        $backupError = null;

        try {
            $backups = collect($backup->listBackups(15))
                ->map(function ($file) {
                    return [
                        'id' => $file['id'],
                        'name' => $file['name'],
                        'modified_at' => $file['modifiedTime'] ? Carbon::parse($file['modifiedTime']) : null,
                        'created_at' => $file['createdTime'] ? Carbon::parse($file['createdTime']) : null,
                        'size' => $file['size'] ?? null,
                    ];
                })
                ->all();
        } catch (\Throwable $e) {
            $backupError = $e->getMessage();
        }

        return view('admin.google-drive', [
            'refreshConfigured' => filled(config('services.google_drive.refresh_token')),
            'backups' => $backups,
            'backupError' => $backupError,
        ]);
    }

    public function start(Request $request)
    {
        $this->authorizeAdmin($request);

        $redirectUri = route('admin.google-drive.callback');
        $client = DriveClientFactory::makeForAuthorization($redirectUri);
        $client->setPrompt('consent');

        $request->session()->put('google_drive_redirect', $redirectUri);

        return redirect()->away($client->createAuthUrl());
    }

    public function callback(Request $request)
    {
        $this->authorizeAdmin($request);

        if ($request->has('error')) {
            return redirect()
                ->route('admin.google-drive.index')
                ->with('status', 'Autorizacion cancelada: '.$request->get('error'));
        }

        $code = $request->get('code');

        if (! $code) {
            return redirect()
                ->route('admin.google-drive.index')
                ->with('status', 'No se recibio ningun codigo de Google.');
        }

        $redirectUri = $request->session()->pull('google_drive_redirect', route('admin.google-drive.callback'));
        $client = DriveClientFactory::makeForAuthorization($redirectUri);

        $token = $client->fetchAccessTokenWithAuthCode($code);

        if (isset($token['error'])) {
            return redirect()
                ->route('admin.google-drive.index')
                ->with('status', 'Error al obtener el token: '.($token['error_description'] ?? $token['error']));
        }

        if (empty($token['refresh_token'])) {
            return redirect()
                ->route('admin.google-drive.index')
                ->with('status', 'Google no devolvio refresh_token. Asegurate de conceder acceso con prompt=consent.');
        }

        EnvEditor::set('GOOGLE_DRIVE_REFRESH_TOKEN', $token['refresh_token']);

        return redirect()
            ->route('admin.google-drive.index')
            ->with('status', 'Refresh token actualizado correctamente.');
    }

    public function sync(Request $request, SqliteDriveSynchronizer $synchronizer)
    {
        $this->authorizeAdmin($request);

        try {
            $result = $synchronizer->sync(force: true);
        } catch (\Throwable $e) {
            return redirect()
                ->route('admin.google-drive.index')
                ->with('error', 'No se pudo sincronizar: '.$e->getMessage());
        }

        return redirect()
            ->route('admin.google-drive.index')
            ->with('status', 'Sincronizacion completada. Registros importados: '.$result['remote_to_local']);
    }

    public function backup(Request $request, SqliteDatabaseBackup $backup)
    {
        $this->authorizeAdmin($request);

        try {
            $result = $backup->backupToDrive();
        } catch (\Throwable $e) {
            return redirect()
                ->route('admin.google-drive.index')
                ->with('error', 'No se pudo generar el backup: '.$e->getMessage());
        }

        return redirect()
            ->route('admin.google-drive.index')
            ->with('status', sprintf('Backup creado (%s). ID: %s', $result['file_name'], $result['file_id']));
    }

    public function restore(Request $request, SqliteDatabaseBackup $backup)
    {
        $this->authorizeAdmin($request);

        $data = $request->validate([
            'file_id' => ['required', 'string'],
        ]);

        try {
            $result = $backup->restoreFromDrive($data['file_id']);
        } catch (\Throwable $e) {
            return redirect()
                ->route('admin.google-drive.index')
                ->with('error', 'No se pudo restaurar la base: '.$e->getMessage());
        }

        return redirect()
            ->route('admin.google-drive.index')
            ->with('status', sprintf(
                'Base restaurada desde %s. Respaldo local previo: %s',
                $result['file_name'],
                $result['local_backup'] ?? 'N/D'
            ));
    }
}
