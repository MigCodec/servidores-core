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
