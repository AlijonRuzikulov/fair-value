<x-layouts.admin :title="__('My profile')" nav="profile" icon="user">
    <form method="post" class="form-layout" action="{{ route('admin.profile.update') }}">
        @csrf @method('put')
        <x-card :title="__('Personal details')" icon="user">
            <div class="form-grid">
                <x-field name="name" :label="__('Full name')" :value="$user->name" span="4" required />
                <x-field name="email" type="email" :label="__('E-mail (login)')" :value="$user->email" span="4" required />
                <x-field name="phone" type="tel" :label="__('Phone')" :value="$user->phone" span="4" />
            </div>
        </x-card>
        <x-card :title="__('Change password')" icon="key-round">
            <div class="form-grid">
                <x-field name="current_password" type="password" :label="__('Current password')" span="4" autocomplete="current-password" />
                <x-field name="password" type="password" :label="__('New password')" span="4" autocomplete="new-password" :hint="__('At least 10 characters, with letters and numbers.')" />
                <x-field name="password_confirmation" type="password" :label="__('Repeat password')" span="4" autocomplete="new-password" />
            </div>
        </x-card>
        <div class="card form-actions">
            <button class="btn btn-primary" type="submit"><x-icon name="check" :size="16" /> {{ __('Save') }}</button>
        </div>
    </form>
</x-layouts.admin>
