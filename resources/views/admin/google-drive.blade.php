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
    </div>
@endsection
