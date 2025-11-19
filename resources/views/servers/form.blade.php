@php
    $physicalServers = $physicalServers ?? collect();
    $groups = $groups ?? collect();
@endphp

<div class="form-grid">
    <div>
        <label for="name">Nombre</label>
        <input type="text" id="name" name="name" value="{{ old('name', $server->name) }}" required>
        @error('name')
            <div class="muted">{{ $message }}</div>
        @enderror
    </div>

    <div>
        <label for="ip_address">IP</label>
        <input type="text" id="ip_address" name="ip_address" value="{{ old('ip_address', $server->ip_address) }}" required>
        @error('ip_address')
            <div class="muted">{{ $message }}</div>
        @enderror
    </div>

    <div>
        <label for="ram_gb">RAM (GB)</label>
        <input type="number" id="ram_gb" name="ram_gb" min="1" value="{{ old('ram_gb', $server->ram_gb) }}" required>
        @error('ram_gb')
            <div class="muted">{{ $message }}</div>
        @enderror
    </div>

    <div>
        <label for="storage_gb">Almacenamiento (GB)</label>
        <input type="number" id="storage_gb" name="storage_gb" min="1" value="{{ old('storage_gb', $server->storage_gb) }}" required>
        @error('storage_gb')
            <div class="muted">{{ $message }}</div>
        @enderror
    </div>

    <div>
        <label for="is_physical">Tipo</label>
        <select name="is_physical" id="is_physical" required>
            <option value="1" {{ old('is_physical', $server->is_physical ?? true) ? 'selected' : '' }}>Fisico</option>
            <option value="0" {{ old('is_physical', $server->is_physical ?? true) ? '' : 'selected' }}>Virtual</option>
        </select>
        @error('is_physical')
            <div class="muted">{{ $message }}</div>
        @enderror
    </div>

    <div>
        <label for="parent_id">Servidor anfitrion</label>
        <select name="parent_id" id="parent_id">
            <option value="">Selecciona...</option>
            @foreach ($physicalServers as $physical)
                <option value="{{ $physical->id }}" @selected(old('parent_id', $server->parent_id) == $physical->id)>
                    {{ $physical->name }} ({{ $physical->ip_address }})
                </option>
            @endforeach
        </select>
        <div class="muted">Solo aplica si el servidor es virtual.</div>
        @error('parent_id')
            <div class="muted">{{ $message }}</div>
        @enderror
    </div>
</div>

@if (auth()->user()->isAdmin())
    <div style="margin-top: 1.5rem;">
        <label for="group_ids">Grupos de ayudantes con acceso</label>
        <select name="group_ids[]" id="group_ids" multiple size="5">
            @foreach ($groups as $group)
                <option value="{{ $group->id }}" @selected(in_array($group->id, old('group_ids', $server->groups?->pluck('id')->all() ?? [])))>
                    {{ $group->name }}
                </option>
            @endforeach
        </select>
        <div class="muted">Mantener sin seleccionar equivale a no asignar ayudantes.</div>
        @error('group_ids')
            <div class="muted">{{ $message }}</div>
        @enderror
    </div>
@endif

<div style="margin-top: 1.5rem;">
    <h3 style="margin: 0 0 0.75rem; font-size: 1rem;">Inventario</h3>
    <div class="form-grid">
        <div>
            <label for="os_name">Sistema operativo</label>
            <input type="text" id="os_name" name="os_name" value="{{ old('os_name', $server->os_name) }}">
            @error('os_name')
                <div class="muted">{{ $message }}</div>
            @enderror
        </div>
        <div>
            <label for="os_version">Versión SO</label>
            <input type="text" id="os_version" name="os_version" value="{{ old('os_version', $server->os_version) }}">
            @error('os_version')
                <div class="muted">{{ $message }}</div>
            @enderror
        </div>
        <div>
            <label for="kernel_version">Kernel</label>
            <input type="text" id="kernel_version" name="kernel_version" value="{{ old('kernel_version', $server->kernel_version) }}">
            @error('kernel_version')
                <div class="muted">{{ $message }}</div>
            @enderror
        </div>
        <div>
            <label for="cpu_cores">Núcleos CPU</label>
            <input type="number" id="cpu_cores" name="cpu_cores" min="1" max="128" value="{{ old('cpu_cores', $server->cpu_cores) }}">
            @error('cpu_cores')
                <div class="muted">{{ $message }}</div>
            @enderror
        </div>
        <div>
            <label for="owner">Responsable / Dueño</label>
            <input type="text" id="owner" name="owner" value="{{ old('owner', $server->owner) }}">
            @error('owner')
                <div class="muted">{{ $message }}</div>
            @enderror
        </div>
        <div>
            <label for="environment">Ambiente</label>
            <input type="text" id="environment" name="environment" value="{{ old('environment', $server->environment) }}" placeholder="Producción, QA, etc.">
            @error('environment')
                <div class="muted">{{ $message }}</div>
            @enderror
        </div>
        <div>
            <label for="location">Ubicación</label>
            <input type="text" id="location" name="location" value="{{ old('location', $server->location) }}" placeholder="Rack / DataCenter / Región">
            @error('location')
                <div class="muted">{{ $message }}</div>
            @enderror
        </div>
    </div>
</div>

<div style="margin-top: 1.5rem;">
    <h3 style="margin: 0 0 0.75rem; font-size: 1rem;">Acceso SSH (opcional)</h3>
    <p class="muted" style="margin-top: 0; margin-bottom: 1rem;">Si completas estos datos, el dashboard podrá consultar métricas básicas vía SSH.</p>
    <div class="form-grid">
        <div>
            <label for="ssh_host">Host SSH</label>
            <input type="text" id="ssh_host" name="ssh_host" value="{{ old('ssh_host', $server->ssh_host) }}" placeholder="Usará la IP si se deja vacío">
            @error('ssh_host')
                <div class="muted">{{ $message }}</div>
            @enderror
        </div>

        <div>
            <label for="ssh_port">Puerto</label>
            <input type="number" id="ssh_port" name="ssh_port" min="1" max="65535" value="{{ old('ssh_port', $server->ssh_port ?? 22) }}">
            @error('ssh_port')
                <div class="muted">{{ $message }}</div>
            @enderror
        </div>

        <div>
            <label for="ssh_username">Usuario</label>
            <input type="text" id="ssh_username" name="ssh_username" value="{{ old('ssh_username', $server->ssh_username) }}">
            @error('ssh_username')
                <div class="muted">{{ $message }}</div>
            @enderror
        </div>

        <div>
            <label for="ssh_password">Contraseña</label>
            <input type="password" id="ssh_password" name="ssh_password" value="{{ old('ssh_password') }}">
            @error('ssh_password')
                <div class="muted">{{ $message }}</div>
            @enderror
        </div>
    </div>
</div>

<div style="margin-top: 1.5rem;">
    <h3 style="margin: 0 0 0.75rem; font-size: 1rem;">Servicios críticos</h3>
    <p class="muted" style="margin-top: 0; margin-bottom: 1rem;">Opcional: ingresa un servicio por línea, se verificará con <code>systemctl is-active</code>.</p>
    <textarea name="critical_services" id="critical_services" rows="4" placeholder="nginx&#10;mysql">{{ old('critical_services', collect($server->critical_services ?? [])->implode("\n")) }}</textarea>
    @error('critical_services')
        <div class="muted">{{ $message }}</div>
    @enderror
</div>

@push('scripts')
    <script>
        const typeSelect = document.getElementById('is_physical');
        const parentSelect = document.getElementById('parent_id');

        function toggleParent() {
            const isPhysical = typeSelect.value === '1';
            parentSelect.disabled = isPhysical;
            if (isPhysical) {
                parentSelect.value = '';
            }
        }

        typeSelect?.addEventListener('change', toggleParent);
        toggleParent();
    </script>
@endpush
