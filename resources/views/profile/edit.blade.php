@extends('layouts.app')

@section('title', 'Perfil')

@section('content')
    <div class="card">
        <h2 class="section-title">Cambiar contraseña</h2>
        <p class="muted">Actualiza tu contraseña para ingresar con email y contraseña.</p>

        <form method="POST" action="{{ route('profile.update') }}">
            @csrf
            @method('PUT')

            @if (! empty($user->password))
                <div style="margin-bottom: 1rem;">
                    <label for="current_password">Contraseña actual</label>
                    <input type="password" id="current_password" name="current_password" required>
                    @error('current_password')
                        <div class="muted">{{ $message }}</div>
                    @enderror
                </div>
            @endif

            <div class="form-grid">
                <div>
                    <label for="password">Nueva contraseña</label>
                    <input type="password" id="password" name="password" required minlength="8">
                    @error('password')
                        <div class="muted">{{ $message }}</div>
                    @enderror
                </div>
                <div>
                    <label for="password_confirmation">Confirmar contraseña</label>
                    <input type="password" id="password_confirmation" name="password_confirmation" required minlength="8">
                </div>
            </div>

            <div class="actions" style="margin-top: 1.5rem;">
                <button class="btn btn-primary" type="submit">Guardar cambios</button>
            </div>
        </form>
    </div>
@endsection
