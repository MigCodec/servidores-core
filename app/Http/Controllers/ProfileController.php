<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class ProfileController extends Controller
{
    public function edit(Request $request)
    {
        return view('profile.edit', [
            'user' => $request->user(),
        ]);
    }

    public function update(Request $request)
    {
        $user = $request->user();

        $rules = [
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];

        if (! empty($user->password)) {
            $rules['current_password'] = ['required'];
        }

        $validated = $request->validate($rules);

        if (! empty($user->password)) {
            if (! Hash::check($validated['current_password'], $user->password)) {
                return back()
                    ->withErrors(['current_password' => 'La contraseña actual no coincide.'])
                    ->withInput();
            }
        }

        $user->password = Hash::make($validated['password']);
        $user->save();

        return redirect()
            ->route('profile.edit')
            ->with('status', 'Contraseña actualizada correctamente.');
    }
}
