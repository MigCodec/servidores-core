<?php

namespace App\Http\Controllers;

use App\Models\Server;
use App\Models\Service;
use App\Models\ServicePasswordLog;
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

        $service = $server->services()->create($data);

        if (! empty($data['password'] ?? null)) {
            $this->logServicePassword($service, $data['password'], $request->user());
        }

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

        $newPassword = $data['password'] ?? null;
        if (! isset($data['password']) || $data['password'] === null || $data['password'] === '') {
            unset($data['password']);
        }

        $service->update($data);

        if (! empty($newPassword)) {
            $this->logServicePassword($service, $newPassword, $request->user());
        }

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
