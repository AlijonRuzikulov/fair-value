<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Activity;
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
        $request->merge(['email' => mb_strtolower(trim((string) $request->input('email')))]);

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

        $user->fill($data);
        $changed = array_diff(array_keys($user->getDirty()), ['password', 'updated_at']);
        $passwordChanged = $user->isDirty('password');
        $before = array_intersect_key($user->getOriginal(), array_flip($changed));
        $user->save();

        if ($changed) {
            Activity::record('updated', $user, collect($changed)->mapWithKeys(fn ($key) => [$key => ['old' => $before[$key] ?? null, 'new' => $user->{$key}]])->all(), $user->name);
        }
        if ($passwordChanged) {
            Activity::record('password_changed', $user, null, $user->name);
        }

        return back()->with('status', __('Profile updated.'));
    }
}
