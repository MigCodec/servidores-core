@php
    $physicalServers = $physicalServers ?? collect();
    $groups = $groups ?? collect();
    $oldBelongsToServer = old('_server_id') && (int) old('_server_id') === (int) $server->id;
    $inputValue = function ($key, $default = null) use ($oldBelongsToServer) {
        return $oldBelongsToServer ? old($key, $default) : $default;
    };
    $inputArray = function ($key, $default = []) use ($oldBelongsToServer) {
        return $oldBelongsToServer ? (array) old($key, $default) : $default;
    };
    $credentialSelection = $server->relationLoaded('groups')
        ? $server->groups->filter(fn ($group) => optional($group->pivot)->can_view_credentials)->pluck('id')->all()
        : [];
    $selectedGroups = $inputArray('group_ids', $server->groups?->pluck('id')->all() ?? []);
    $selectedCredentialGroups = $inputArray('credential_group_ids', $credentialSelection);
@endphp

@once
    @push('styles')
        <style>
            .checklist-grid {
                display: grid;
                gap: 0.5rem;
                grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            }

            .checklist-item {
                display: flex;
                gap: 0.4rem;
                align-items: center;
                border: 1px solid #e5e7eb;
                border-radius: 10px;
                padding: 0.4rem 0.55rem;
                background: #fff;
            }

            .checklist-item input[type='checkbox'] {
                width: 16px;
                height: 16px;
            }
        </style>
    @endpush
@endonce

<input type="hidden" name="_server_id" value="{{ $server->id }}">

<div class="form-grid">
    <div>
        <label for="name">Nombre</label>
        <input type="text" id="name" name="name" value="{{ $inputValue('name', $server->name) }}" required>
        @error('name')
            <div class="muted">{{ $message }}</div>
        @enderror
    </div>

    <div>
        <label for="ip_address">IP</label>
        <input type="text" id="ip_address" name="ip_address" value="{{ $inputValue('ip_address', $server->ip_address) }}" required>
        @error('ip_address')
            <div class="muted">{{ $message }}</div>
        @enderror
    </div>

    <div>
        <label for="ram_gb">RAM (GB)</label>
        <input type="number" id="ram_gb" name="ram_gb" min="1" value="{{ $inputValue('ram_gb', $server->ram_gb) }}" required>
        @error('ram_gb')
            <div class="muted">{{ $message }}</div>
        @enderror
    </div>

    <div>
        <label for="storage_gb">Almacenamiento (GB)</label>
        <input type="number" id="storage_gb" name="storage_gb" min="1" value="{{ $inputValue('storage_gb', $server->storage_gb) }}" required>
        @error('storage_gb')
            <div class="muted">{{ $message }}</div>
        @enderror
    </div>

    <div>
        <label for="is_physical">Tipo</label>
        <select name="is_physical" id="is_physical" required>
            <option value="1" {{ $inputValue('is_physical', $server->is_physical ?? true) ? 'selected' : '' }}>Fisico</option>
            <option value="0" {{ $inputValue('is_physical', $server->is_physical ?? true) ? '' : 'selected' }}>Virtual</option>
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
                <option value="{{ $physical->id }}" @selected($inputValue('parent_id', $server->parent_id) == $physical->id)>
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
        <label>Grupos con acceso</label>
        @if ($groups->isEmpty())
            <p class="muted">No hay grupos configurados.</p>
        @else
            <div class="checklist-grid">
                @foreach ($groups as $group)
                    <label class="checklist-item">
                        <input type="checkbox" name="group_ids[]" value="{{ $group->id }}" {{ in_array($group->id, $selectedGroups) ? 'checked' : '' }}>
                        <span>{{ $group->name }}</span>
                    </label>
                @endforeach
            </div>
            <div class="muted">Deja todos sin seleccionar para que sólo los administradores lo gestionen.</div>
        @endif
        @error('group_ids')
            <div class="muted">{{ $message }}</div>
        @enderror

        <label style="margin-top: 1rem;">Grupos con acceso a credenciales</label>
        @if ($groups->isEmpty())
            <p class="muted">No hay grupos disponibles.</p>
        @else
            <div class="checklist-grid">
                @foreach ($groups as $group)
                    <label class="checklist-item">
                        <input type="checkbox" name="credential_group_ids[]" value="{{ $group->id }}" {{ in_array($group->id, $selectedCredentialGroups) ? 'checked' : '' }}>
                        <span>{{ $group->name }}</span>
                    </label>
                @endforeach
            </div>
            <div class="muted">Estos grupos podrán ver las contraseñas del servidor y servicios.</div>
        @endif
        @error('credential_group_ids')
            <div class="muted">{{ $message }}</div>
        @enderror
    </div>
@endif

<div style="margin-top: 1rem;">
    <div class="checkbox-row">
        <input type="checkbox" id="in_maintenance" name="in_maintenance" value="1" {{ $inputValue('in_maintenance', $server->in_maintenance) ? 'checked' : '' }}>
        <label for="in_maintenance">Modo mantenimiento</label>
    </div>
    <div class="muted">Mientras esté en mantenimiento no se revisará su estado en el dashboard.</div>
</div>

<div style="margin-top: 1.5rem;">
    <h3 style="margin: 0 0 0.75rem; font-size: 1rem;">Inventario</h3>
    <div class="form-grid">
        <div>
            <label for="os_name">Sistema operativo</label>
            <input type="text" id="os_name" name="os_name" value="{{ $inputValue('os_name', $server->os_name) }}">
            @error('os_name')
                <div class="muted">{{ $message }}</div>
            @enderror
        </div>
        <div>
            <label for="os_version">Versión SO</label>
            <input type="text" id="os_version" name="os_version" value="{{ $inputValue('os_version', $server->os_version) }}">
            @error('os_version')
                <div class="muted">{{ $message }}</div>
            @enderror
        </div>
        <div>
            <label for="kernel_version">Kernel</label>
            <input type="text" id="kernel_version" name="kernel_version" value="{{ $inputValue('kernel_version', $server->kernel_version) }}">
            @error('kernel_version')
                <div class="muted">{{ $message }}</div>
            @enderror
        </div>
        <div>
            <label for="cpu_cores">Núcleos CPU</label>
            <input type="number" id="cpu_cores" name="cpu_cores" min="1" max="128" value="{{ $inputValue('cpu_cores', $server->cpu_cores) }}">
            @error('cpu_cores')
                <div class="muted">{{ $message }}</div>
            @enderror
        </div>
        <div>
            <label for="owner">Responsable / Dueño</label>
            <input type="text" id="owner" name="owner" value="{{ $inputValue('owner', $server->owner) }}">
            @error('owner')
                <div class="muted">{{ $message }}</div>
            @enderror
        </div>
        <div>
            <label for="environment">Ambiente</label>
            <input type="text" id="environment" name="environment" value="{{ $inputValue('environment', $server->environment) }}" placeholder="Producción, QA, etc.">
            @error('environment')
                <div class="muted">{{ $message }}</div>
            @enderror
        </div>
        <div>
            <label for="location">Ubicación</label>
            <input type="text" id="location" name="location" value="{{ $inputValue('location', $server->location) }}" placeholder="Rack / DataCenter / Región">
            @error('location')
                <div class="muted">{{ $message }}</div>
            @enderror
        </div>
    </div>
</div>

<div style="margin-top: 1.5rem;">
    <h3 style="margin: 0 0 0.75rem; font-size: 1rem;">Servicios críticos</h3>
    <p class="muted" style="margin-top: 0; margin-bottom: 1rem;">Opcional: ingresa un servicio por línea, se verificará con <code>systemctl is-active</code>.</p>
    <textarea name="critical_services" id="critical_services" rows="4" placeholder="nginx&#10;mysql">{{ $inputValue('critical_services', collect($server->critical_services ?? [])->implode("\n")) }}</textarea>
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
