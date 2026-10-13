<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('admin.profile', ['user' => $request->user()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user)],
            'phone' => ['nullable', 'string', 'max:30'],
            // The login and the password are changed only with the current password.
            'current_password' => [
                'nullable', 'required_with:password',
                Rule::requiredIf(fn () => mb_strtolower((string) $request->input('email')) !== mb_strtolower($user->email)),
                'current_password',
            ],
            'password' => ['nullable', 'confirmed', Password::defaults()],
        ]);

        unset($data['current_password']);
        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        } else {
            $request->session()->forget('initial_password');
        }

        $user->update($data);

        return back()->with('status', __('Profile updated.'));
    }
}
