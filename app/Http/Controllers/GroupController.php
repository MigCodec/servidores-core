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

    public function edit(Group $group)
    {
        $this->authorize('view', $group);

        return view('groups.edit', [
            'group' => $group->load(['users', 'servers']),
            'users' => User::orderBy('name')->get(),
            'servers' => Server::orderBy('name')->get(),
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

        $group->users()->sync($data['user_ids'] ?? []);

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
