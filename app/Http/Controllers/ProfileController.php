<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class ProfileController extends Controller
{
    /**
     * Show the form for editing the user's profile.
     */
    public function edit()
    {
        return Inertia::render('Profile/Edit', [
            'user' => Auth::user(),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'password' => 'nullable|string|min:8|confirmed',
        ]);

        // Remove password from validation if not provided
        if (empty($validated['password'])) {
            unset($validated['password']);
        }

        // Hash password if provided
        if (isset($validated['password'])) {
            $validated['password'] = bcrypt($validated['password']);
        }
        
        User::where('id', $user->id)->update($validated);

        return redirect()->route('profile.edit')
            ->with('success', 'Perfil atualizado com sucesso.');
    }
}
