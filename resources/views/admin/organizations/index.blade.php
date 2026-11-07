<x-layouts.admin :title="__('Organisations')" nav="organizations" icon="building-2">
    <x-slot:actions>
        <a class="btn btn-primary" href="{{ route('admin.organizations.create') }}"><x-icon name="plus" :size="16" /> {{ __('Add organisation') }}</a>
    </x-slot:actions>
    <section class="card">
        @if ($organizations->isEmpty())
            <x-empty icon="building-2" :title="__('No organisations yet')" />
        @else
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>{{ __('Organisation') }}</th>
                            <th>{{ __('Legal name') }}</th>
                            <th>{{ __('TIN') }}</th>
                            <th>{{ __('Director') }}</th>
                            <th>{{ __('Phone') }}</th>
                            <th>{{ __('Users') }}</th>
                            <th class="t-actions"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($organizations as $organization)
                            <tr>
                                <td><a class="cell-title" href="{{ route('admin.organizations.edit', $organization) }}">{{ $organization->name }}</a></td>
                                <td>{{ $organization->settings['legal_name'] ?? '—' }}</td>
                                <td>{{ $organization->settings['inn'] ?? '—' }}</td>
                                <td>{{ $organization->settings['director'] ?? '—' }}</td>
                                <td class="nowrap">{{ isset($organization->settings['phone']) ? \App\Support\Format::phone($organization->settings['phone']) : '—' }}</td>
                                <td><a href="{{ route('admin.users.index') }}">{{ $organization->users_count }}</a></td>
                                <td class="t-actions">
                                    <div class="row-actions">
                                        <a class="btn btn-sm btn-icon" href="{{ route('admin.organizations.edit', $organization) }}" aria-label="{{ __('Edit') }}" title="{{ __('Edit') }}"><x-icon name="pencil" :size="16" /></a>
                                        @if ($organization->users_count === 0)
                                            <form method="post" action="{{ route('admin.organizations.destroy', $organization) }}" data-confirm="{{ __('Delete this organisation?') }}">
                                                @csrf @method('delete')
                                                <button class="btn btn-sm btn-icon btn-danger" type="submit" aria-label="{{ __('Delete') }}" title="{{ __('Delete') }}"><x-icon name="trash-2" :size="16" /></button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</x-layouts.admin>
