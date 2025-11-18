<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\Server;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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
            'services' => fn ($query) => $query->orderBy('name'),
            'groups',
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
            'ip_address' => ['required', 'ipv4', $uniqueIpRule],
            'ram_gb' => ['required', 'integer', 'min:1', 'max:65535'],
            'storage_gb' => ['required', 'integer', 'min:1', 'max:1048576'],
            'is_physical' => ['required', 'boolean'],
            'parent_id' => $parentRules,
            'group_ids' => ['nullable', 'array'],
            'group_ids.*' => [
                'integer',
                Rule::exists('groups', 'id')->where('is_admin', false),
            ],
        ]);

        $data['is_physical'] = (bool) $data['is_physical'];
        $data['parent_id'] = $data['is_physical'] ? null : ($data['parent_id'] ?? null);

        return $data;
    }

    protected function syncGroups(Server $server, Request $request): void
    {
        if (! $request->user()->isAdmin()) {
            return;
        }

        $groupIds = collect($request->input('group_ids', []))
            ->filter()
            ->all();

        $server->groups()->sync($groupIds);
    }
}
