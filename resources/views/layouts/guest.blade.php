<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', config('app.name', 'Servidores Core'))</title>
    <style>
        body {
            margin: 0;
            font-family: "Segoe UI", Arial, sans-serif;
            background: #0f172a;
            color: #fff;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .login-card {
            width: min(420px, 92vw);
            background: #1e293b;
            border-radius: 16px;
            padding: 2.25rem;
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.35);
        }

        h1 {
            margin-top: 0;
            margin-bottom: 1.25rem;
            font-size: 1.5rem;
        }

        label {
            display: block;
            font-weight: 600;
            margin-bottom: 0.35rem;
        }

        input[type="email"],
        input[type="password"] {
            width: 100%;
            border: none;
            border-radius: 10px;
            padding: 0.65rem 0.85rem;
            margin-bottom: 1rem;
            font-size: 1rem;
        }

        .btn {
            width: 100%;
            border: none;
            border-radius: 10px;
            padding: 0.75rem 1rem;
            font-size: 1rem;
            cursor: pointer;
            margin-top: 0.5rem;
        }

        .btn-primary {
            background: #3b82f6;
            color: #fff;
        }

        .btn-google {
            background: #fff;
            color: #1e293b;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .checkbox-row {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-top: -0.5rem;
            margin-bottom: 1rem;
        }

        .alert {
            background: rgba(239, 68, 68, 0.15);
            border: 1px solid rgba(248, 113, 113, 0.4);
            color: #fecaca;
            padding: 0.85rem;
            border-radius: 10px;
            margin-bottom: 1.25rem;
        }

        .small {
            font-size: 0.85rem;
            color: #cbd5f5;
            margin-top: 1rem;
        }
    </style>
    @stack('styles')
</head>
<body>
    <div class="login-card">
        @yield('content')
    </div>
    @stack('scripts')
</body>
</html>
