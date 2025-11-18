<?php

namespace App\Http\Controllers;

use App\Models\Server;
use App\Models\Service;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    public function create(Server $server)
    {
        $this->authorize('create', [Service::class, $server]);

        return view('services.create', [
            'server' => $server,
        ]);
    }

    public function store(Request $request, Server $server)
    {
        $this->authorize('create', [Service::class, $server]);

        $data = $this->validateService($request);

        $server->services()->create($data);

        return redirect()
            ->route('servers.show', $server)
            ->with('status', 'Servicio agregado.');
    }

    public function edit(Service $service)
    {
        $this->authorize('update', $service);

        $service->load('server');

        return view('services.edit', [
            'service' => $service,
        ]);
    }

    public function update(Request $request, Service $service)
    {
        $this->authorize('update', $service);

        $data = $this->validateService($request, false);

        if (! isset($data['password']) || $data['password'] === null || $data['password'] === '') {
            unset($data['password']);
        }

        $service->update($data);

        return redirect()
            ->route('servers.show', $service->server)
            ->with('status', 'Servicio actualizado.');
    }

    public function destroy(Service $service)
    {
        $this->authorize('delete', $service);

        $service->delete();

        return redirect()
            ->route('servers.show', $service->server)
            ->with('status', 'Servicio eliminado.');
    }

    protected function validateService(Request $request, bool $passwordRequired = true): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'url' => ['nullable', 'url', 'max:255'],
            'port' => ['required', 'integer', 'min:1', 'max:65535'],
            'username' => ['required', 'string', 'max:255'],
            'password' => [$passwordRequired ? 'required' : 'nullable', 'string', 'max:255'],
        ];

        return $request->validate($rules);
    }
}
