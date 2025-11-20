<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\Server;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class GroupController extends Controller
{
    public function index()
    {
        $this->authorize('viewAny', Group::class);

        $groups = Group::withCount(['users', 'servers'])
            ->orderBy('is_admin', 'desc')
            ->orderBy('name')
            ->get();

        return view('groups.index', [
            'groups' => $groups,
        ]);
    }

    public function create()
    {
        $this->authorize('create', Group::class);

        return view('groups.create', [
            'group' => new Group(),
            'users' => User::orderBy('name')->get(),
            'servers' => Server::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', Group::class);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:groups,slug'],
            'is_admin' => ['nullable', 'boolean'],
            'user_ids' => ['nullable', 'array'],
            'user_ids.*' => ['integer', Rule::exists('users', 'id')],
            'server_ids' => ['nullable', 'array'],
            'server_ids.*' => ['integer', Rule::exists('servers', 'id')],
        ]);

        $group = new Group();
        $group->name = $data['name'];
        $group->slug = $data['slug'] ?? Str::slug($data['name']);
        $group->is_admin = $request->boolean('is_admin');
        $group->save();

        $userIds = collect($data['user_ids'] ?? [])->map(fn ($id) => (int) $id)->filter()->all();
        if ($group->is_admin && ! in_array($request->user()->id, $userIds)) {
            $userIds[] = $request->user()->id;
        }

        $group->users()->sync($userIds);

        if ($group->is_admin) {
            $group->servers()->sync([]);
        } else {
            $group->servers()->sync($data['server_ids'] ?? []);
        }

        return redirect()
            ->route('groups.index')
            ->with('status', 'Grupo creado correctamente.');
    }

    public function edit(Group $group)
    {
        $this->authorize('view', $group);

        $isProtected = $group->slug === config('services.groups.default_guest_slug', 'invitados');

        return view('groups.edit', [
            'group' => $group->load(['users', 'servers']),
            'users' => User::orderBy('name')->get(),
            'servers' => Server::orderBy('name')->get(),
            'isProtected' => $isProtected,
        ]);
    }

    public function update(Request $request, Group $group)
    {
        $this->authorize('update', $group);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('groups', 'slug')->ignore($group->id),
            ],
            'is_admin' => ['nullable', 'boolean'],
            'user_ids' => ['nullable', 'array'],
            'user_ids.*' => ['integer', Rule::exists('users', 'id')],
            'server_ids' => ['nullable', 'array'],
            'server_ids.*' => ['integer', Rule::exists('servers', 'id')],
        ]);

        $group->name = $data['name'];
        $group->slug = $data['slug'] ?? $group->slug ?? Str::slug($data['name']);

        if ($group->is_admin) {
            $group->is_admin = true;
        } else {
            $group->is_admin = $request->boolean('is_admin');
        }

        $group->save();

        $userIds = collect($data['user_ids'] ?? [])->map(fn ($id) => (int) $id)->filter()->all();

        if ($group->is_admin) {
            $currentUser = $request->user();
            $hasOtherAdminGroup = $currentUser->groups()
                ->where('is_admin', true)
                ->where('groups.id', '!=', $group->id)
                ->exists();

            if (! in_array($currentUser->id, $userIds) && ! $hasOtherAdminGroup) {
                return back()
                    ->withErrors(['user_ids' => 'Debes permanecer en al menos un grupo de administradores.'])
                    ->withInput();
            }
        }

        $group->users()->sync($userIds);

        if ($group->is_admin) {
            $group->servers()->sync([]);
        } else {
            $group->servers()->sync($data['server_ids'] ?? []);
        }

        return redirect()
            ->route('groups.index')
            ->with('status', 'Grupo actualizado.');
    }
}
