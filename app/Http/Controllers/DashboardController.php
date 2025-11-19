<?php

namespace App\Http\Controllers;

use App\Models\Server;
use App\Models\ServerHealthLog;
use App\Services\ServerHealthService;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class DashboardController extends Controller
{
    public function __construct(protected ServerHealthService $health)
    {
    }

    public function index(Request $request)
    {
        $servers = $this->visibleServers($request->user())->with('groups')->orderBy('name')->get();

        $data = Cache::get('server-health');

        if (! $data || $this->isStale($data, $servers)) {
            $data = $this->health->refresh($servers);
            Cache::put('server-health', $data, now()->addMinutes(5));
        }

        $records = $data['records'] ?? [];
        $generatedAt = isset($data['generated_at']) ? \Illuminate\Support\Carbon::parse($data['generated_at']) : null;

        return view('dashboard.index', [
            'servers' => $servers,
            'health' => $records,
            'generatedAt' => $generatedAt,
            'summary' => $this->summarize($servers, $records),
            'history' => $this->recentHistory($servers->pluck('id')),
        ]);
    }

    public function refresh(Request $request)
    {
        $servers = $this->visibleServers($request->user())->orderBy('name')->get();
        $data = $this->health->refresh($servers);

        Cache::put('server-health', $data, now()->addMinutes(5));

        return redirect()
            ->route('dashboard.index')
            ->with('status', 'Dashboard actualizado.');
    }

    protected function visibleServers(Authenticatable $user)
    {
        $query = Server::query()->with(['parent']);

        if (method_exists($user, 'isAdmin') && ! $user->isAdmin()) {
            $allowedIds = $user->manageableServerIds();

            if ($allowedIds === []) {
                $query->whereRaw('1 = 0');
            } else {
                $query->whereIn('id', $allowedIds);
            }
        }

        return $query;
    }

    protected function summarize(Collection $servers, array $records): array
    {
        $summary = [
            'physical' => ['up' => 0, 'down' => 0],
            'virtual' => ['up' => 0, 'down' => 0],
        ];

        foreach ($servers as $server) {
            $status = $records[$server->id]['status'] ?? 'unknown';
            $isUp = $status === 'up';
            $category = $server->is_physical ? 'physical' : 'virtual';

            if ($isUp) {
                $summary[$category]['up']++;
            } else {
                $summary[$category]['down']++;
            }
        }

        return $summary;
    }

    protected function isStale(?array $data, Collection $servers): bool
    {
        if (! $data || ! isset($data['records'])) {
            return true;
        }

        $recordIds = array_keys($data['records']);

        return $servers->pluck('id')->sort()->values()->toArray() !== collect($recordIds)->sort()->values()->toArray();
    }

    protected function recentHistory(Collection $serverIds)
    {
        if ($serverIds->isEmpty()) {
            return collect();
        }

        return ServerHealthLog::query()
            ->whereIn('server_id', $serverIds)
            ->latest()
            ->limit(200)
            ->get()
            ->groupBy('server_id');
    }
}
