@extends('layouts.app')

@section('title', 'Integracion Google Drive')

@section('content')
    <div class="card">
        <h2 class="section-title">Integracion Google Drive</h2>
        <p class="muted">
            Usa este asistente para generar o renovar el refresh token utilizado para subir respaldos y sincronizar la base.
            Asegurate de que la URL <code>{{ route('admin.google-drive.callback') }}</code> este registrada como redirect URI en Google Cloud.
        </p>
        <ul>
            <li>Al presionar el boton se abrira Google para que elijas la cuenta autorizada.</li>
            <li>Una vez aceptado, volveras a esta pantalla y el refresh token se guardara automaticamente en tu <code>.env</code>.</li>
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
            Ejecuta la misma accion que el comando <code>php artisan db:sync-drive</code>: descargara el archivo maestro,
            importara los registros faltantes al SQLite local y volvera a subirlo a Drive.
        </p>
        <form method="POST" action="{{ route('admin.google-drive.sync') }}">
            @csrf
            <button class="btn btn-secondary" type="submit">Sincronizar ahora</button>
        </form>

        <hr style="margin: 2rem 0;">

        <h3>Backups manuales</h3>
        <p class="muted">Genera un archivo SQLite y lo sube a la carpeta configurada en Drive.</p>
        <form method="POST" action="{{ route('admin.google-drive.backup') }}">
            @csrf
            <button class="btn" type="submit">Crear backup ahora</button>
        </form>

        <hr style="margin: 2rem 0;">

        <h3>Restaurar desde Drive</h3>
        @if ($backupError)
            <div class="alert alert-error" style="margin-bottom: 1rem;">
                {{ $backupError }}
            </div>
        @endif
        <p class="muted">Selecciona un backup para sobrescribir la base local. Se crea una copia previa antes de restaurar.</p>
        @if (! empty($backups))
            <form method="POST" action="{{ route('admin.google-drive.restore') }}" style="display: flex; gap: 1rem; flex-wrap: wrap;">
                @csrf
                <select name="file_id" required>
                    @foreach ($backups as $backup)
                        <option value="{{ $backup['id'] }}">
                            {{ $backup['name'] }}
                            @if ($backup['modified_at'])
                                - {{ $backup['modified_at']->format('Y-m-d H:i') }}
                            @endif
                        </option>
                    @endforeach
                </select>
                <button class="btn btn-danger" type="submit" onclick="return confirm('Esto sobrescribira la base actual. Continuar?')">Restaurar backup</button>
            </form>
        @else
            <p class="muted">No se encontraron archivos de backup en Google Drive.</p>
        @endif
    </div>
@endsection
