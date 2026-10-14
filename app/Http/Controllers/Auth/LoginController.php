<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function show(): View
    {
        return view('auth.login', ['hint' => $this->firstSignInHint()]);
    }

    public function login(Request $request): RedirectResponse
    {
        $request->merge(['email' => mb_strtolower(trim((string) $request->input('email')))]);
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        // Successful and failed attempts are written to the audit log by the auth event listeners.
        if (! Auth::attempt([...$credentials, 'is_active' => true], $request->boolean('remember'))) {
            throw ValidationException::withMessages(['email' => __('Incorrect e-mail or password.')]);
        }

        $request->session()->regenerate();
        $request->user()->forceFill(['last_login_at' => now()])->saveQuietly();
        $request->session()->put('initial_password', $this->usesInitialPassword($request->user()));

        return redirect()->intended(route('admin.dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    /** Whether the user still signs in with ADMIN_PASSWORD from .env. */
    private function usesInitialPassword(User $user): bool
    {
        $password = (string) config('fairvalue.admin.password');

        return $password !== '' && Hash::check($password, $user->password);
    }

    /**
     * On a trial copy (DEMO_MODE=true) the sign-in page shows the first
     * administrator's login from .env, until that password is changed.
     *
     * @return array{email:string, password:string}|null
     */
    private function firstSignInHint(): ?array
    {
        $email = (string) config('fairvalue.admin.email');
        $password = (string) config('fairvalue.admin.password');

        if (! config('fairvalue.demo') || app()->isProduction() || $email === '' || $password === '') {
            return null;
        }

        $admin = User::where('email', $email)->where('is_active', true)->first();

        return $admin && $this->usesInitialPassword($admin) ? ['email' => $email, 'password' => $password] : null;
    }
}
