@extends('layouts.guest')

@section('title', 'Acceder')

@section('content')
    <h1>Panel de servidores</h1>

    @if ($errors->any())
        <div class="alert">
            @foreach ($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('login.store') }}">
        @csrf
        <label for="email">Correo</label>
        <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus>

        <label for="password">Contrasena</label>
        <input id="password" type="password" name="password" required>

        <div class="checkbox-row">
            <input type="checkbox" id="remember" name="remember">
            <label for="remember">Recordarme</label>
        </div>

        <button class="btn btn-primary" type="submit">Ingresar</button>
    </form>

    <div style="margin: 1rem 0; text-align: center; color: #cbd5f5;">o</div>

    <a class="btn btn-google" href="{{ route('login.google') }}">
        <span>Continuar con Google</span>
    </a>

    <p class="small">Usa la cuenta asignada por el administrador.</p>
@endsection
