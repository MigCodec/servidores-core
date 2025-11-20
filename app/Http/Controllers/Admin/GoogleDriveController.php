<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Database\SqliteDriveSynchronizer;
use App\Support\EnvEditor;
use App\Support\GoogleDrive\DriveClientFactory;
use Illuminate\Http\Request;

class GoogleDriveController extends Controller
{
    protected function authorizeAdmin(Request $request): void
    {
        if (! method_exists($request->user(), 'isAdmin') || ! $request->user()->isAdmin()) {
            abort(403);
        }
    }

    public function index(Request $request)
    {
        $this->authorizeAdmin($request);

        return view('admin.google-drive', [
            'refreshConfigured' => filled(config('services.google_drive.refresh_token')),
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
                ->with('status', 'Autorización cancelada: '.$request->get('error'));
        }

        $code = $request->get('code');

        if (! $code) {
            return redirect()
                ->route('admin.google-drive.index')
                ->with('status', 'No se recibió ningún código de Google.');
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
                ->with('status', 'Google no devolvió refresh_token. Asegúrate de conceder acceso con prompt=consent.');
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
            ->with('status', 'Sincronización completada. Registros importados: '.$result['remote_to_local']);
    }
}
