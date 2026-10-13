<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Activity;
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
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt([...$credentials, 'is_active' => true], $request->boolean('remember'))) {
            throw ValidationException::withMessages(['email' => __('Incorrect e-mail or password.')]);
        }

        $request->session()->regenerate();
        $request->user()->forceFill(['last_login_at' => now()])->saveQuietly();
        Activity::record('login', $request->user(), label: $request->user()->name);

        return redirect()->intended(route('admin.dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
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

        if (! config('fairvalue.demo') || $email === '' || $password === '') {
            return null;
        }

        $admin = User::where('email', $email)->where('is_active', true)->first();

        return $admin && Hash::check($password, $admin->password) ? ['email' => $email, 'password' => $password] : null;
    }
}
