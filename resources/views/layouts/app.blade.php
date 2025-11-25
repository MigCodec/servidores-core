<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name', 'Servidores Core'))</title>
    <style>
        :root {
            color-scheme: light;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: "Segoe UI", Arial, sans-serif;
            background-color: #f3f4f6;
            color: #111827;
        }

        a {
            color: inherit;
        }

        header {
            background: #111827;
            color: #fff;
        }

        .nav-wrapper {
            max-width: 1100px;
            margin: 0 auto;
            padding: 1rem 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
        }

        .brand {
            font-weight: 600;
            font-size: 1.1rem;
        }

        .nav-links {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            align-items: center;
        }

        .nav-links a {
            color: #e5e7eb;
            text-decoration: none;
            font-size: 0.95rem;
        }

        .nav-links a.active {
            color: #60a5fa;
        }

        .user-menu {
            display: flex;
            gap: 0.75rem;
            align-items: center;
            color: #d1d5db;
            font-size: 0.9rem;
        }

        .user-menu form {
            margin: 0;
        }

        .user-menu button {
            background: transparent;
            border: 1px solid rgba(255, 255, 255, 0.5);
            color: #fff;
            padding: 0.4rem 0.75rem;
            border-radius: 6px;
            cursor: pointer;
        }

        main {
            max-width: 1100px;
            margin: 2rem auto;
            padding: 0 1.5rem 3rem;
        }

        .card {
            background: #fff;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
            margin-bottom: 1.5rem;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
        }

        .table-wrapper {
            overflow-x: auto;
        }

        .table th,
        .table td {
            border-bottom: 1px solid #e5e7eb;
            padding: 0.75rem 0.5rem;
            text-align: left;
            vertical-align: top;
        }

        .table th {
            font-size: 0.8rem;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: #6b7280;
        }

        .table tbody tr:hover {
            background-color: #f9fafb;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.35rem;
            border-radius: 8px;
            border: none;
            padding: 0.55rem 1rem;
            font-size: 0.95rem;
            cursor: pointer;
            text-decoration: none;
            transition: background 0.2s ease;
        }

        .btn-primary {
            background-color: #2563eb;
            color: #fff;
        }

        .btn-secondary {
            background-color: #4b5563;
            color: #fff;
        }

        .btn-light {
            background-color: #e5e7eb;
            color: #111827;
        }

        .btn-danger {
            background-color: #dc2626;
            color: #fff;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1rem;
        }

        .checkbox-row {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        label {
            font-weight: 600;
            display: block;
            margin-bottom: 0.35rem;
            font-size: 0.9rem;
        }

        input[type="text"],
        input[type="number"],
        input[type="password"],
        input[type="email"],
        select,
        textarea {
            width: 100%;
            border-radius: 8px;
            border: 1px solid #d1d5db;
            padding: 0.55rem 0.75rem;
            font-size: 1rem;
        }

        textarea {
            min-height: 120px;
        }

        .badge {
            display: inline-flex;
            padding: 0.15rem 0.6rem;
            border-radius: 999px;
            font-size: 0.8rem;
            background-color: #e5e7eb;
            color: #111827;
        }

        .badge-success {
            background-color: #bbf7d0;
            color: #166534;
        }

        .badge-warning {
            background-color: #fef3c7;
            color: #92400e;
        }

        .alert {
            padding: 0.85rem 1rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
        }

        .alert-success {
            background-color: #dcfce7;
            color: #166534;
        }

        .alert-error {
            background-color: #fee2e2;
            color: #991b1b;
        }

        .actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .muted {
            color: #6b7280;
            font-size: 0.9rem;
        }

        .section-title {
            margin-top: 0;
            margin-bottom: 1rem;
        }

        .site-footer {
            background: #0f172a;
            color: #e5e7eb;
            padding: 1rem 1.5rem;
        }

        .site-footer .footer-content {
            max-width: 1100px;
            margin: 0 auto;
            text-align: center;
            font-size: 0.95rem;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 0.35rem;
        }

        .site-footer a {
            color: #60a5fa;
            text-decoration: none;
            font-weight: 600;
        }

        .site-footer a:hover {
            text-decoration: underline;
        }

        @media (max-width: 640px) {
            .nav-wrapper {
                flex-direction: column;
                align-items: flex-start;
            }

            .user-menu {
                width: 100%;
                justify-content: space-between;
            }
        }

        .toast-container {
            position: fixed;
            top: 1.5rem;
            right: 1.5rem;
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            z-index: 9999;
        }

        .toast {
            min-width: 240px;
            max-width: 320px;
            padding: 0.85rem 1rem;
            border-radius: 10px;
            color: #111827;
            box-shadow: 0 10px 15px rgba(0, 0, 0, 0.1);
            animation: slideIn 0.3s ease, fadeOut 0.3s ease 4.5s forwards;
            font-size: 0.95rem;
        }

        .toast-success {
            background: #dcfce7;
            border-left: 4px solid #16a34a;
        }

        .toast-error {
            background: #fee2e2;
            border-left: 4px solid #b91c1c;
        }

        .toast-info {
            background: #dbeafe;
            border-left: 4px solid #2563eb;
        }

        /* Pagination */
        .pagination {
            list-style: none;
            display: inline-flex;
            gap: 0.4rem;
            padding: 0;
            margin: 0;
        }

        .pagination li a,
        .pagination li span {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 38px;
            padding: 0.45rem 0.75rem;
            border-radius: 8px;
            border: 1px solid #e5e7eb;
            background: #fff;
            color: #111827;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.95rem;
        }

        .pagination li a:hover {
            background: #f3f4f6;
        }

        .pagination .active span {
            background: #2563eb;
            border-color: #2563eb;
            color: #fff;
        }

        .pagination .disabled span {
            background: #f3f4f6;
            color: #9ca3af;
        }

        .pagination-container {
            display: flex;
            justify-content: flex-end;
            margin-top: 1rem;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateX(40px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        @keyframes fadeOut {
            to {
                opacity: 0;
                transform: translateX(40px);
            }
        }
    </style>
    @stack('styles')
</head>
<body>
    <div class="toast-container" id="toast-container"></div>
    <header>
        <div class="nav-wrapper">
            <div class="brand">{{ config('app.name', 'Servidores Core') }}</div>
            <nav class="nav-links">
                <a href="{{ route('dashboard.index') }}" class="{{ request()->routeIs('dashboard.*') ? 'active' : '' }}">Dashboard</a>
                <a href="{{ route('servers.index') }}" class="{{ request()->routeIs('servers.*') ? 'active' : '' }}">Servidores</a>
                @can('viewAny', App\Models\Group::class)
                    <a href="{{ route('groups.index') }}" class="{{ request()->routeIs('groups.*') ? 'active' : '' }}">Grupos</a>
                @endcan
                @if (auth()->user()->isAdmin())
                    <a href="{{ route('admin.google-drive.index') }}" class="{{ request()->routeIs('admin.google-drive.*') ? 'active' : '' }}">Google Drive</a>
                @endif
            </nav>
            <div class="user-menu">
                <span>{{ auth()->user()->name }}</span>
                <a href="{{ route('profile.edit') }}" style="color:#d1d5db;text-decoration:none;font-size:0.85rem;">Perfil</a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit">Salir</button>
                </form>
            </div>
        </div>
    </header>
    <main>
        @yield('content')
    </main>
    <footer class="site-footer">
        <div class="footer-content">
            <span>Powered by</span>
            <a href="https://www.linkedin.com/in/miguel-angel-araya-cort%C3%A9s-a56815203/" target="_blank" rel="noopener">Miguel Araya</a>
        </div>
    </footer>
    @stack('scripts')
    <script>
        (function () {
            const container = document.getElementById('toast-container');

            function showToast(message, type = 'info') {
                if (!message) return;
                const toast = document.createElement('div');
                toast.className = `toast toast-${type}`;
                toast.textContent = message;
                container.appendChild(toast);
                setTimeout(() => toast.remove(), 5000);
            }

            window.showToast = showToast;

            const messages = [];
            @if (session('status'))
                messages.push({ text: "{{ addslashes(session('status')) }}", type: 'success' });
            @endif
            @if (session('error'))
                messages.push({ text: "{{ addslashes(session('error')) }}", type: 'error' });
            @endif
            @if ($errors->any())
                messages.push({ text: "{{ addslashes($errors->first()) }}", type: 'error' });
            @endif

            messages.forEach(({ text, type }) => showToast(text, type));
        })();
    </script>
</body>
</html>
