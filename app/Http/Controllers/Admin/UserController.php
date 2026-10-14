<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * Employees who can sign in to the admin panel.
 */
class UserController extends Controller
{
    public function index(): View
    {
        return view('admin.users.index', ['users' => User::orderByDesc('is_active')->orderBy('name')->get()]);
    }

    public function create(): View
    {
        return view('admin.users.form', ['user' => new User(['role' => UserRole::Appraiser, 'is_active' => true])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = User::create($this->validated($request));
        Activity::record('created', $user, $this->logged($user), $user->name);

        return redirect()->route('admin.users.index')->with('status', __('Employee added.'));
    }

    public function edit(User $user): View
    {
        return view('admin.users.form', ['user' => $user]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $this->validated($request, $user);

        // An administrator cannot lock themselves out.
        if ($user->is($request->user())) {
            $data['role'] = UserRole::Admin->value;
            $data['is_active'] = true;
        }

        $user->update($data);
        if ($user->wasChanged()) {
            Activity::record('updated', $user, $this->logged($user), $user->name);
        }

        return redirect()->route('admin.users.index')->with('status', __('Changes saved.'));
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            return back()->with('error', __('You cannot delete your own account.'));
        }

        // Keep the history: employees are deactivated, not deleted.
        $user->update(['is_active' => false]);
        Activity::record('updated', $user, ['is_active' => false], $user->name);

        return redirect()->route('admin.users.index')->with('status', __('The employee has been deactivated.'));
    }

    private function validated(Request $request, ?User $user = null): array
    {
        $request->merge(['email' => mb_strtolower(trim((string) $request->input('email')))]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user)],
            'phone' => ['nullable', 'string', 'max:30'],
            'role' => ['required', Rule::enum(UserRole::class)],
            'position' => ['nullable', 'string', 'max:255'],
            'certificate_number' => ['nullable', 'string', 'max:60'],
            'is_active' => ['boolean'],
            'password' => [$user ? 'nullable' : 'required', 'confirmed', Password::defaults()],
        ]);

        $data['is_active'] = $request->boolean('is_active');
        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }

        return $data;
    }

    /** Changed fields for the activity log; the password itself is never written there. */
    private function logged(User $user): array
    {
        $changes = $user->wasRecentlyCreated ? $user->getAttributes() : $user->getChanges();
        $changes = array_intersect_key($changes, array_flip(['name', 'email', 'role', 'position', 'is_active', 'password']));
        if (array_key_exists('password', $changes)) {
            $changes['password'] = '••••••';
        }

        return $changes;
    }
}
