<x-layouts.admin :title="$user->exists ? $user->name : __('New employee')" :subtitle="__('Employees')" nav="users" icon="user-cog" :back="route('admin.users.index')">
    <form method="post" class="form-layout" action="{{ $user->exists ? route('admin.users.update', $user) : route('admin.users.store') }}">
        @csrf
        @if ($user->exists) @method('put') @endif
        <x-card :title="__('Employee')" icon="user">
            <div class="form-grid">
                <x-field name="name" :label="__('Full name')" :value="$user->name" span="6" required />
                <x-field name="position" :label="__('Position')" :value="$user->position" span="6" />
                <x-field name="email" type="email" :label="__('E-mail (login)')" :value="$user->email" span="6" required autocomplete="off" />
                <x-field name="phone" type="tel" :label="__('Phone')" :value="$user->phone" span="6" />
                <x-select name="role" :label="__('Role')" :options="\App\Enums\UserRole::options()" :value="$user->role" span="6" required />
                <x-field name="certificate_number" :label="__('Qualification certificate')" :value="$user->certificate_number" span="6" :hint="__('Appraiser qualification certificate number; printed on certificates.')" />
                <div class="field span-12">
                    <input type="hidden" name="is_active" value="0">
                    <label class="check" style="max-width: 320px">
                        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $user->is_active))>
                        <span class="check-box"><x-icon name="check" :size="14" /></span>
                        <span>{{ __('Can sign in') }}</span>
                    </label>
                </div>
            </div>
        </x-card>
        <x-card :title="__('Password')" icon="key-round">
            <div class="form-grid">
                <x-field name="password" type="password" :label="$user->exists ? __('New password') : __('Password')" span="6" :required="! $user->exists" autocomplete="new-password" :hint="$user->exists ? __('Leave empty to keep the current password.') : __('At least 10 characters, with letters and numbers.')" />
                <x-field name="password_confirmation" type="password" :label="__('Repeat password')" span="6" autocomplete="new-password" />
            </div>
        </x-card>
        <div class="card form-actions">
            <a class="btn btn-ghost" href="{{ route('admin.users.index') }}">{{ __('Cancel') }}</a>
            <button class="btn btn-primary" type="submit"><x-icon name="check" :size="16" /> {{ __('Save') }}</button>
        </div>
    </form>
</x-layouts.admin>
