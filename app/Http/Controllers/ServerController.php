<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\Server;
use App\Models\ServerPasswordLog;
use App\Models\Service;
use App\Models\ServicePasswordLog;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Rules\IpOrHostname;

class ServerController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Server::class);

        $query = Server::query()
            ->with(['parent', 'groups'])
            ->withCount('services');

        if (! $request->user()->isAdmin()) {
            $allowedIds = $request->user()->manageableServerIds();

            if ($allowedIds === []) {
                $query->whereRaw('1 = 0');
            } else {
                $query->whereIn('id', $allowedIds);
            }
        }

        if ($search = $request->input('search')) {
            $query->where(function ($builder) use ($search) {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('ip_address', 'like', "%{$search}%");
            });
        }

        if ($type = $request->input('type')) {
            if ($type === 'physical') {
                $query->where('is_physical', true);
            } elseif ($type === 'virtual') {
                $query->where('is_physical', false);
            }
        }

        $servers = $query->orderBy('name')->paginate(12)->withQueryString();

        return view('servers.index', [
            'servers' => $servers,
            'filters' => [
                'search' => $request->input('search', ''),
                'type' => $request->input('type', ''),
            ],
        ]);
    }

    public function create()
    {
        $this->authorize('create', Server::class);

        return view('servers.create', [
            'server' => new Server(),
            'physicalServers' => Server::query()
                ->where('is_physical', true)
                ->orderBy('name')
                ->get(),
            'groups' => Group::query()
                ->where('is_admin', false)
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', Server::class);

        $data = $this->validateServer($request);
        $server = Server::create($data);

        $this->syncGroups($server, $request);

        return redirect()
            ->route('servers.show', $server)
            ->with('status', 'Servidor creado correctamente.');
    }

    public function show(Server $server)
    {
        $this->authorize('view', $server);

        $server->load([
            'parent',
            'virtualMachines',
            'services' => fn ($query) => $query->with([
                'passwordLogs' => fn ($logQuery) => $logQuery->with('recordedBy')->latest()->limit(5),
            ])->orderBy('name'),
            'groups',
            'healthLogs' => fn ($query) => $query->latest()->limit(20),
            'passwordLogs' => fn ($query) => $query->with('recordedBy')->latest()->limit(20),
        ]);

        $groups = $server->groups()->where('is_admin', false)->get();

        return view('servers.show', [
            'server' => $server,
            'groups' => $groups,
        ]);
    }

    public function edit(Server $server)
    {
        $this->authorize('update', $server);

        return view('servers.edit', [
            'server' => $server->load('groups'),
            'physicalServers' => Server::query()
                ->where('is_physical', true)
                ->where('id', '!=', $server->id)
                ->orderBy('name')
                ->get(),
            'groups' => Group::query()
                ->where('is_admin', false)
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function update(Request $request, Server $server)
    {
        $this->authorize('update', $server);

        $data = $this->validateServer($request, $server);
        $server->update($data);

        $this->syncGroups($server, $request);

        return redirect()
            ->route('servers.show', $server)
            ->with('status', 'Servidor actualizado correctamente.');
    }

    public function destroy(Server $server)
    {
        $this->authorize('delete', $server);

        $server->delete();

        return redirect()
            ->route('servers.index')
            ->with('status', 'Servidor eliminado.');
    }

    public function storeVaultEntry(Request $request, Server $server)
    {
        $this->authorize('update', $server);

        $data = $request->validate([
            'entry_type' => ['required', 'in:ssh,service'],
            'service_id' => ['nullable', 'integer', 'exists:services,id'],
            'name' => [
                'nullable',
                'string',
                'max:255',
                Rule::requiredIf(fn () => $request->input('entry_type') === 'service'
                    && ! $request->filled('service_id')),
            ],
            'port' => [
                'nullable',
                'integer',
                'between:1,65535',
                Rule::requiredIf(fn () => $request->input('entry_type') === 'service'
                    && ! $request->filled('service_id')),
            ],
            'username' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
            'url' => ['nullable', 'url', 'max:255'],
            'host' => ['nullable', 'string', 'max:255', 'required_if:entry_type,ssh'],
        ], [
            'name.required' => 'El nombre del servicio es obligatorio.',
            'port.required' => 'El puerto es obligatorio.',
        ]);

        if ($data['entry_type'] === 'ssh') {
            $this->syncSshService($server, [
                'ssh_host' => $data['host'] ?? $server->ip_address,
                'ssh_port' => $data['port'],
                'ssh_username' => $data['username'],
                'ssh_password' => $data['password'],
            ], $request->user());
        } else {
            $service = null;
            if (! empty($data['service_id'])) {
                $service = $server->services()->whereKey($data['service_id'])->first();
                if (! $service) {
                    return back()
                        ->withErrors(['service_id' => 'El servicio seleccionado no pertenece a este servidor.'])
                        ->withInput();
                }
            }

            if (! $service) {
                $service = $server->services()->create([
                    'name' => $data['name'],
                    'url' => $data['url'] ?? null,
                    'port' => $data['port'] ?? 0,
                    'username' => $data['username'],
                    'password' => $data['password'],
                ]);
            } else {
                $service->update([
                    'name' => $data['name'] ?? $service->name,
                    'url' => $data['url'] ?? $service->url,
                    'port' => $data['port'] ?? $service->port,
                    'username' => $data['username'],
                    'password' => $data['password'],
                ]);
            }

            $this->logServicePassword($service, $data['password'], $request->user());
        }

        return redirect()
            ->route('servers.show', $server)
            ->with('status', 'Credencial registrada en el vault.');
    }

    protected function validateServer(Request $request, ?Server $server = null): array
    {
        $uniqueIpRule = Rule::unique('servers', 'ip_address');

        if ($server) {
            $uniqueIpRule = $uniqueIpRule->ignore($server->id);
        }

        $parentRules = [
            Rule::requiredIf(fn () => ! $request->boolean('is_physical')),
            'nullable',
            'integer',
            Rule::exists('servers', 'id')->where('is_physical', true),
        ];

        if ($server) {
            $parentRules[] = Rule::notIn([$server->id]);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'ip_address' => ['required', 'string', 'max:255', new IpOrHostname(), $uniqueIpRule],
            'ram_gb' => ['required', 'integer', 'min:1', 'max:65535'],
            'storage_gb' => ['required', 'integer', 'min:1', 'max:1048576'],
            'is_physical' => ['required', 'boolean'],
            'parent_id' => $parentRules,
            'group_ids' => ['nullable', 'array'],
            'group_ids.*' => [
                'integer',
                Rule::exists('groups', 'id')->where('is_admin', false),
            ],
            'os_name' => ['nullable', 'string', 'max:255'],
            'os_version' => ['nullable', 'string', 'max:255'],
            'kernel_version' => ['nullable', 'string', 'max:255'],
            'cpu_cores' => ['nullable', 'integer', 'between:1,128'],
            'owner' => ['nullable', 'string', 'max:255'],
            'environment' => ['nullable', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'critical_services' => ['nullable', 'string'],
            'in_maintenance' => ['nullable', 'boolean'],
        ]);

        $data['is_physical'] = (bool) $data['is_physical'];
        $data['parent_id'] = $data['is_physical'] ? null : ($data['parent_id'] ?? null);
        $data['critical_services'] = $this->normalizeCriticalServices($data['critical_services'] ?? null);
        $data['in_maintenance'] = $request->boolean('in_maintenance');

        return $data;
    }

    protected function syncGroups(Server $server, Request $request): void
    {
        if (! $request->user()->isAdmin()) {
            return;
        }

        $groupIds = collect($request->input('group_ids', []))
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->values()
            ->all();

        $credentialIds = collect($request->input('credential_group_ids', []))
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->values()
            ->all();
        $credentialIds = array_values(array_intersect($credentialIds, $groupIds));

        $syncData = [];
        foreach ($groupIds as $groupId) {
            $syncData[$groupId] = [
                'can_view_credentials' => in_array($groupId, $credentialIds, true),
            ];
        }

        $server->groups()->sync($syncData);
    }

    protected function normalizeCriticalServices(?string $value): ?array
    {
        if (! $value) {
            return null;
        }

        $list = collect(preg_split("/\r?\n/", $value))
            ->map(fn ($item) => trim($item))
            ->filter()
            ->values()
            ->all();

        return $list === [] ? null : $list;
    }

    protected function syncSshService(Server $server, array $sshData, $user = null): void
    {
        $host = $sshData['ssh_host'] ?? null;
        $port = $sshData['ssh_port'] ?? null;
        $username = $sshData['ssh_username'] ?? null;
        $password = $sshData['ssh_password'] ?? null;

        $service = $server->services()->where('is_ssh', true)->first();

        if (! $service && blank($username) && blank($password)) {
            return;
        }

        if (! $service) {
            if (blank($username) || blank($password)) {
                return;
            }

            $service = $server->services()->create([
                'name' => 'SSH',
                'host' => $host ?: $server->ip_address,
                'port' => $port ?: 22,
                'username' => $username,
                'password' => $password,
                'is_ssh' => true,
            ]);

            $this->logServicePassword($service, $password, $user);
            $this->logServerPassword($server, $password, $user);

            return;
        }

        $update = ['name' => 'SSH'];
        if ($host !== null && $host !== '') {
            $update['host'] = $host;
        }
        if ($port !== null) {
            $update['port'] = $port;
        }
        if ($username !== null && $username !== '') {
            $update['username'] = $username;
        }
        if ($password !== null && $password !== '') {
            $update['password'] = $password;
        }

        $service->update($update);

        if (! empty($password)) {
            $this->logServicePassword($service, $password, $user);
            $this->logServerPassword($server, $password, $user);
        }
    }

    protected function logServerPassword(Server $server, string $password, $user = null): void
    {
        if ($password === '') {
            return;
        }

        ServerPasswordLog::create([
            'server_id' => $server->id,
            'password' => $password,
            'recorded_by' => $user?->id,
        ]);
    }

    protected function logServicePassword(Service $service, string $password, $user = null): void
    {
        if ($password === '') {
            return;
        }

        ServicePasswordLog::create([
            'service_id' => $service->id,
            'password' => $password,
            'recorded_by' => $user?->id,
        ]);
    }
}

