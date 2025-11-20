@extends('layouts.app')

@section('title', 'Integración Google Drive')

@section('content')
    <div class="card">
        <h2 class="section-title">Integración Google Drive</h2>
        <p class="muted">
            Usa este asistente para generar o renovar el refresh token utilizado para subir respaldos y sincronizar la base.
            Asegúrate de que la URL <code>{{ route('admin.google-drive.callback') }}</code> esté registrada como redirect URI en Google Cloud.
        </p>
        <ul>
            <li>Al presionar el botón se abrirá Google para que elijas la cuenta autorizada.</li>
            <li>Una vez aceptado, volverás a esta pantalla y el refresh token se guardará automáticamente en tu <code>.env</code>.</li>
        </ul>

        <p>
            Estado actual:
            <strong>{{ filled(config('services.google_drive.refresh_token')) ? 'Token configurado' : 'Sin token' }}</strong>
        </p>

        <form method="POST" action="{{ route('admin.google-drive.start') }}">
            @csrf
            <button class="btn btn-primary" type="submit">Generar / renovar refresh token</button>
        </form>

        <hr style="margin: 2rem 0;">

        <h3>Sincronizar base maestra</h3>
        <p class="muted">
            Ejecuta la misma acción que el comando <code>php artisan db:sync-drive</code>: descargará el archivo maestro,
            importará los registros faltantes al SQLite local y volverá a subirlo a Drive.
        </p>
        <form method="POST" action="{{ route('admin.google-drive.sync') }}">
            @csrf
            <button class="btn btn-secondary" type="submit">Sincronizar ahora</button>
        </form>
    </div>
@endsection
