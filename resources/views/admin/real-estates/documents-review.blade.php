@php
    $person = $result['person'];
    $property = $result['property'];
    $kinds = [
        'id_card' => __('ID card'), 'passport' => __('Passport'), 'cadastral_extract' => __('Cadastral extract'),
        'technical_passport' => __('Technical passport'), 'contract' => __('Contract'), 'photo' => __('Photo'), 'other' => __('Other'),
    ];
    $personLabels = [
        'last_name' => __('Last name'), 'first_name' => __('First name'), 'middle_name' => __('Middle name'), 'birth_date' => __('Date of birth'),
        'pinfl' => __('PINFL'), 'passport_series' => __('Passport series'), 'passport_number' => __('Passport number'),
        'passport_issued_by' => __('Issued by'), 'passport_issued_at' => __('Date of issue'), 'address' => __('Address'),
    ];
    $objectLabels = [
        'cadastral_number' => __('Cadastral number'), 'total_area' => __('Total area'), 'living_area' => __('Living area'),
        'useful_area' => __('Useful area'), 'land_area' => __('Land area'), 'floors_total' => __('Number of floors'), 'floor' => __('Floor'),
        'rooms' => __('Rooms'), 'year_built' => __('Year built'), 'address' => __('Address'),
    ];
    $show = fn ($value) => $value instanceof \Carbon\CarbonInterface ? $value->format('d.m.Y') : (filled($value) ? (string) $value : null);
    $dateOf = fn ($value) => $value && strtotime((string) $value) ? date('d.m.Y', strtotime((string) $value)) : $value;
    $foundPlace = collect([$place['region']?->name, $place['district']?->name])->filter()->implode(', ');
    $currentPlace = collect([$item->region?->name, $item->district?->name])->filter()->implode(', ');
@endphp
<x-layouts.admin :title="__('Data from the documents')" :subtitle="$item->title" nav="real-estates" icon="scan-search" :back="route('admin.real-estates.report.edit', $item->id)">
    <form method="post" action="{{ route('admin.real-estates.documents.apply', $item->id) }}" class="form-layout">
        @csrf

        <div class="card report-help">
            <x-icon name="info" :size="18" />
            <div>
                <p>{{ __('Compare every value with the document. Only the ticked values are saved; values that are already the same are not changed.') }}</p>
                @if ($result['notes'])<p class="muted">{{ __('Note from the reading') }}: {{ $result['notes'] }}</p>@endif
            </div>
        </div>

        <x-card :title="__('Documents read')" icon="file-search">
            <div class="doc-read-files">
                @foreach ($files as $i => $file)
                    @php $kind = collect($result['documents'])->firstWhere('file', $i)['kind'] ?? null; @endphp
                    <a class="doc-read-file" href="{{ route('admin.attachments.show', $file) }}" target="_blank">
                        @if ($file->isImage())
                            <img src="{{ route('admin.attachments.show', $file) }}" alt="{{ $file->name }}" loading="lazy">
                        @else
                            <span class="doc-read-pdf"><x-icon name="file-text" :size="28" /></span>
                        @endif
                        <small>{{ $file->name }}</small>
                        @if ($kind)<span class="badge badge-brand">{{ $kinds[$kind] }}</span>@endif
                    </a>
                @endforeach
            </div>
        </x-card>

        <x-card :title="__('Owner')" icon="user">
            <p class="field-hint" style="margin-bottom: 12px">
                @if ($ownerMode === 'linked')
                    {{ __('The owner linked to the object is updated: :name.', ['name' => $owner->title]) }}
                @elseif ($ownerMode === 'existing')
                    {{ __('A client with the same PINFL or passport was found and is linked as the owner: :name.', ['name' => $owner->title]) }}
                @else
                    {{ __('A new client is created and linked as the owner.') }}
                @endif
            </p>
            @include('admin.real-estates._document-rows', ['group' => 'person', 'labels' => $personLabels, 'found' => $person, 'current' => fn ($key) => $owner ? $show($owner->{$key}) : null])
            @if ($property['owner_name'] && $person['last_name'] && ! str_contains(mb_strtolower($property['owner_name']), mb_strtolower($person['last_name'])))
                <p class="field-error" style="margin-top: 10px">{{ __('The right holder in the extract (:name) differs from the person in the ID document — check it.', ['name' => $property['owner_name']]) }}</p>
            @endif
        </x-card>

        <x-card :title="__('Object')" icon="building">
            @include('admin.real-estates._document-rows', ['group' => 'property', 'labels' => $objectLabels, 'found' => $property, 'current' => fn ($key) => $show($item->{$key})])
            <table class="table doc-rows" style="margin-top: 0">
                <tbody>
                    <tr>
                        <td class="doc-label">{{ __('Region and district') }}</td>
                        <td class="faint">{{ $currentPlace ?: '—' }}</td>
                        <td><strong>{{ $foundPlace ?: '—' }}</strong>@if ($property['region'] || $property['district'])<span class="cell-sub">{{ collect([$property['region'], $property['district']])->filter()->implode(', ') }}</span>@endif</td>
                        <td class="doc-pick">
                            @if ($foundPlace && $foundPlace !== $currentPlace)
                                <label class="check check-sm"><input type="checkbox" name="property[place]" value="1" checked><span class="check-box"><x-icon name="check" :size="12" /></span></label>
                            @endif
                        </td>
                    </tr>
                </tbody>
            </table>
        </x-card>

        <x-card :title="__('Rights (for the report)')" icon="scale">
            @include('admin.real-estates._document-rows', [
                'group' => 'report',
                'labels' => ['rights' => __('Rights being valued'), 'rights_basis' => __('Document establishing the right'), 'encumbrances' => __('Encumbrances and restrictions')],
                'found' => [
                    'rights' => $property['rights_type'] ? $property['rights_type'].($property['share'] ? ' ('.$property['share'].')' : '') : null,
                    'rights_basis' => $property['rights_basis'],
                    'encumbrances' => $property['encumbrances'],
                ],
                'current' => fn ($key) => ((array) $item->report)[$key] ?? null,
            ])
            @if ($property['registration_date'])<p class="field-hint" style="margin-top: 10px">{{ __('Registered on') }}: {{ $dateOf($property['registration_date']) }}</p>@endif
        </x-card>

        <div class="card form-actions">
            <a class="btn btn-ghost" href="{{ route('admin.real-estates.report.edit', $item->id) }}">{{ __('Cancel') }}</a>
            <button class="btn btn-primary" type="submit"><x-icon name="check" :size="16" /> {{ __('Save the chosen values') }}</button>
        </div>
    </form>
</x-layouts.admin>
