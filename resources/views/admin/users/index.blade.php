@php use App\Support\Format; @endphp
<x-layouts.admin :title="__('Employees')" nav="users" icon="user-cog">
    <x-slot:actions>
        <a class="btn btn-primary" href="{{ route('admin.users.create') }}"><x-icon name="user-plus" :size="16" /> {{ __('Add employee') }}</a>
    </x-slot:actions>
    <section class="card">
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>{{ __('Employee') }}</th>
                        <th>{{ __('Role') }}</th>
                        <th>{{ __('Phone') }}</th>
                        <th>{{ __('Qualification certificate') }}</th>
                        <th>{{ __('Last sign-in') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th class="t-actions"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($users as $user)
                        <tr>
                            <td>
                                <div class="row-flex" style="flex-wrap: nowrap; gap: 12px">
                                    <span class="avatar">{{ $user->initials() }}</span>
                                    <div><span class="cell-title">{{ $user->name }}</span><span class="cell-sub">{{ $user->email }}@if ($user->position) · {{ $user->position }}@endif</span></div>
                                </div>
                            </td>
                            <td><span class="badge badge-brand">{{ $user->role->label() }}</span></td>
                            <td class="nowrap">{{ $user->phone ? Format::phone($user->phone) : '—' }}</td>
                            <td>{{ $user->certificate_number ?? '—' }}</td>
                            <td class="nowrap">{{ $user->last_login_at ? Format::datetime($user->last_login_at) : '—' }}</td>
                            <td>
                                @if ($user->is_active)<span class="badge badge-success"><span class="badge-dot"></span>{{ __('Active') }}</span>
                                @else<span class="badge badge-muted"><span class="badge-dot"></span>{{ __('Inactive') }}</span>@endif
                            </td>
                            <td class="t-actions">
                                <div class="row-actions">
                                    <a class="btn btn-sm btn-icon" href="{{ route('admin.users.edit', $user) }}" aria-label="{{ __('Edit') }}" title="{{ __('Edit') }}"><x-icon name="pencil" :size="16" /></a>
                                    @if ($user->is_active && ! $user->is(auth()->user()))
                                        <form method="post" action="{{ route('admin.users.destroy', $user) }}" data-confirm="{{ __('Deactivate this employee? They will no longer be able to sign in.') }}">
                                            @csrf @method('delete')
                                            <button class="btn btn-sm btn-icon btn-danger" type="submit" aria-label="{{ __('Deactivate') }}" title="{{ __('Deactivate') }}"><x-icon name="lock" :size="16" /></button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
    <x-card :title="__('Roles')" icon="info">
        <dl class="details cols-3">
            <x-detail :label="__('Administrator')" :value="__('Full access, including employees, settings, directories and the activity log.')" />
            <x-detail :label="__('Appraiser')" :value="__('Works with clients, objects and price catalogues; can delete records.')" />
            <x-detail :label="__('Operator')" :value="__('Registers requests, clients and objects; cannot delete or change catalogues.')" />
        </dl>
    </x-card>
</x-layouts.admin>
