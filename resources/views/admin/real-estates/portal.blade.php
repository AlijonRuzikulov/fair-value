{{--
    Adding and valuing real estate in the classic look, laid out as on the valuation portal the appraisers know:
    the four steps, the card "enter the real estate data" with the owner, the approaches, the object and the map,
    the folding sections (main data, documents, comparative and income approaches, reconciliation), the files,
    the buildings of the cost approach, "Holat" and "Tarixi", and Cancel / Save. The same page adds a new object.
--}}
@php
    use App\Enums\ValuationApproach;
    use App\Enums\ValuationStatus;
    use App\Reports\RealEstateReport;
    use App\Support\Format;
    use Illuminate\Support\Facades\Gate;

    $exists = $item->exists;
    $kind = $item->kind ?? \App\Enums\RealEstateKind::NonResidential;
    $canEdit = ! $exists || Gate::allows('edit-valuation', $item);
    $applied = $exists ? $item->approachList() : [];
    $reportValues = RealEstateReport::values($item);
    $reconciliation = $exists ? RealEstateReport::reconciliation($item, $reportValues['scores']) : ['weights' => [], 'reconciled' => 0.0];
    $analogType = array_search($item::class, \App\Services\Analogs\AnalogFinder::OBJECTS, true);
    $back = route($module->routeName('index'), $module->routeParams($item));
    $mainKeys = ['region_id', 'district_id', 'landmark', 'address', 'cadastral_value', 'value_type', 'land_area', 'built_up_area', 'useful_area',
        'total_area', 'wall_material', 'floors_total', 'floor', 'year_built', 'ownership_form', 'land_rights', 'market_index'];
    // Steps with a reason, at the bottom as on the portal.
    $status = $item->status;
    $reasons = $exists && auth()->user()->can('finalize-valuations') ? array_filter([
        'cancelled' => ! in_array($status, [ValuationStatus::Completed, ValuationStatus::Cancelled], true) ? [__('Cancel'), 'circle-x', 'btn-fill-danger'] : null,
        'returned' => $status === ValuationStatus::Review && auth()->user()->can('approve-valuations') ? [__('Return for correction'), 'undo-2', 'btn-info'] : null,
        'reopened' => $status === ValuationStatus::Completed && auth()->user()->can('approve-valuations') ? [__('Revaluate'), 'refresh-cw', 'btn-warning'] : null,
    ]) : [];
@endphp
<x-layouts.admin :title="__('Valuation of real estate (:kind)', ['kind' => $kind->label()])" :nav="$nav" :icon="$module->icon()">
    <div class="portal">
        @if ($errors->any())
            <div class="toast toast-error" role="alert"><x-icon name="triangle-alert" :size="18" /> {{ __('Please correct the highlighted fields.') }}</div>
        @endif

        @include('admin.real-estates._portal-steps')

        <section class="card portal-card">
            <header class="portal-card-head">
                <h2>{{ __('Enter the real estate data (:kind)', ['kind' => $kind->label()]) }}</h2>
                {{-- The page opened on a phone: photos and documents are taken with its camera. --}}
                <details class="mobile-pop">
                    <summary class="btn btn-success"><x-icon name="file-text" :size="16" /> {{ __('Get the data through the mobile application') }}</summary>
                    <div class="mobile-pop-body">
                        <div class="qr" data-qr="{{ $exists ? route('admin.real-estates.show', $item->id) : url()->full() }}" role="img" aria-label="QR"></div>
                        <p>{{ __('Scan the code with the phone: the page opens there, photos and documents are taken with its camera.') }}</p>
                    </div>
                </details>
            </header>

            <form method="post" id="object-form" class="portal-form portal-card-body" data-object-form
                  action="{{ $exists ? route('admin.real-estates.main', $item->id) : route($module->routeName('store')) }}">
                @csrf
                @if ($exists) @method('put') @else <input type="hidden" name="kind" value="{{ $kind->value }}"> @endif

                <fieldset class="portal-fields" @disabled(! $canEdit)>
                    @include('admin.real-estates._portal-top')
                </fieldset>

                <details class="portal-fold" id="main" open>
                    <summary>{{ __('Main data of the real estate') }}</summary>
                    <fieldset class="portal-fields" @disabled(! $canEdit)>
                        @include('admin.real-estates._main-fields')
                    </fieldset>
                </details>
            </form>

            <div class="portal-card-body portal-folds">
                <details class="portal-fold" id="documents-section" data-remember>
                    <summary>{{ __('Documents submitted for the valuation of the real estate') }}</summary>
                    <div class="portal-body portal-cards-flat">
                        @if ($exists)
                            @include('admin.objects._documents', ['owner' => $item])
                        @else
                            <p class="muted">{{ __('Documents are uploaded after the object is saved.') }}</p>
                        @endif
                    </div>
                </details>

                @foreach ([ValuationApproach::Comparative, ValuationApproach::Income] as $approach)
                    @php $value = $exists ? $item->{$approach->column()} : null; @endphp
                    <details class="portal-fold" id="approach-{{ $approach->value }}" data-remember>
                        <summary>
                            {{ $approach === ValuationApproach::Comparative ? __('Comparative approach') : __('Income approach') }}
                            @if ($exists && ! in_array($approach, $applied, true))<span class="portal-refused">({{ __('refused') }})</span>@endif
                            @if ($value !== null && in_array($approach, $applied, true))<span class="portal-sum">{{ Format::money($value) }}</span>@endif
                        </summary>
                        <div class="portal-body portal-cards-flat">
                            @if ($exists)
                                @include('admin.real-estates._portal-approach')
                            @else
                                <p class="muted">{{ __('The approaches are calculated after the object is saved.') }}</p>
                            @endif
                        </div>
                    </details>
                @endforeach

                <details class="portal-fold" id="reconciliation" data-remember>
                    <summary>
                        {{ __('Reconciliation step') }}
                        @if ($item->market_value !== null)<span class="portal-sum">{{ Format::money($item->market_value) }}</span>@endif
                    </summary>
                    <div class="portal-body">
                        @if (! $exists)
                            <p class="muted">{{ __('The approaches are calculated after the object is saved.') }}</p>
                        @else
                            {{-- As on the valuation portal: the scores of the approaches by the four factors, then their values, coefficients and the final amount. --}}
                            @php
                                $byApproach = $reconciliation['scores'] ?? [];
                                $first = [ValuationApproach::Cost, ValuationApproach::Comparative, ValuationApproach::Income];
                                $second = [ValuationApproach::Cost, ValuationApproach::Income, ValuationApproach::Comparative];
                                $short = ['cost' => __('Cost approach (Kxar)'), 'comparative' => __('Comparative approach (Kqiyos)'), 'income' => __('Income approach (Kdar)')];
                            @endphp
                            <div class="recon-box">
                                <h3 class="recon-title">{{ $item->name }}</h3>
                                <div class="table-wrap">
                                    <table class="table portal-table recon-table">
                                        <thead>
                                            <tr>
                                                <th>{{ __('In finding the shares the appraiser is to analyse the following main factors') }}</th>
                                                @foreach ($first as $approach)<th class="c">{{ $short[$approach->value] }}</th>@endforeach
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach (RealEstateReport::RECONCILIATION_FACTORS as $i => $factor)
                                                <tr>
                                                    <td>{{ __($factor) }}</td>
                                                    @foreach ($first as $approach)<td class="c">{{ Format::number($byApproach[$approach->value][$i] ?? 0, 2) }}</td>@endforeach
                                                </tr>
                                            @endforeach
                                            <tr>
                                                <td>{{ __('Total of the comparative measures') }}</td>
                                                @foreach ($first as $approach)<td class="c">{{ Format::number(array_sum($byApproach[$approach->value] ?? []), 2) }}</td>@endforeach
                                            </tr>
                                            <tr>
                                                <td>{{ __('Total of the comparative measures (arithmetic mean)') }}</td>
                                                @foreach ($first as $approach)<td class="c">{{ Format::number(array_sum($byApproach[$approach->value] ?? []) / max(1, count(RealEstateReport::RECONCILIATION_FACTORS)), 4) }}</td>@endforeach
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="table-wrap">
                                    <table class="table portal-table recon-table">
                                        <thead>
                                            <tr class="recon-group"><th></th><th class="c" colspan="3">{{ __('Buildings and structures') }}</th></tr>
                                            <tr>
                                                <th>{{ __('Approaches') }}</th>
                                                @foreach ($second as $approach)<th class="c">{{ $approach->label() }}</th>@endforeach
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td>{{ __('Value found') }}</td>
                                                @foreach ($second as $approach)
                                                    @php $value = in_array($approach, $applied, true) ? $item->{$approach->column()} : null; @endphp
                                                    <td class="c">{{ $value !== null ? Format::number($value, 2) : __('Not applied') }}</td>
                                                @endforeach
                                            </tr>
                                            <tr>
                                                <td>{{ __('Coefficients of the comparative measures') }}</td>
                                                @foreach ($second as $approach)<td class="c">{{ Format::number($reconciliation['weights'][$approach->value] ?? 0, 5) }}</td>@endforeach
                                            </tr>
                                            <tr>
                                                <td>{{ __('Measure values') }}</td>
                                                @foreach ($second as $approach)<td class="c">{{ Format::number(($reconciliation['weights'][$approach->value] ?? 0) * (float) $item->{$approach->column()}, 2) }}</td>@endforeach
                                            </tr>
                                            <tr>
                                                <td>{{ __('Formula of the final amount:') }}</td>
                                                <td class="c" colspan="3">{{ __('Kyak = Kxar * C1 + Kdar * C2 + Kqiyos * C3') }}</td>
                                            </tr>
                                            <tr class="recon-total">
                                                <td>{{ __('Final amount of the object of valuation:') }}</td>
                                                <td class="c" colspan="3">{{ $reconciliation['reconciled'] > 0 ? Format::number($reconciliation['reconciled'], 2) : '—' }}</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <form method="post" action="{{ route('admin.real-estates.value', $item->id) }}" class="portal-value">
                                @csrf @method('put')
                                <fieldset class="form-grid" @disabled(! $canEdit)>
                                    <x-field name="market_value" :label="__('Market value (final)')" :value="$item->market_value" money :suffix="__('sum')" span="4"
                                             :hint="($usdValue ?? null) ? '≈ '.Format::number($usdValue).' USD · '.__('at the Central Bank rate') : null" />
                                    <div class="field span-8 portal-value-actions">
                                        @if ($canEdit)
                                            <button class="btn btn-success" type="submit"><x-icon name="check" :size="16" /> {{ __('Save') }}</button>
                                            @if ($reconciliation['reconciled'] > 0 && ! empty(((array) $item->report)['final_typed']))
                                                <button class="btn btn-info" type="submit" name="reconciled" value="1"><x-icon name="calculator" :size="16" /> {{ __('Use the reconciled value') }}</button>
                                            @endif
                                            <a class="btn" href="{{ route('admin.real-estates.report.edit', $item->id) }}#step-approaches"><x-icon name="sliders-horizontal" :size="16" /> {{ __('Scores of the approaches') }}</a>
                                        @endif
                                    </div>
                                </fieldset>
                            </form>
                        @endif
                    </div>
                </details>
            </div>

            {{-- The rest of the page stays in the same card, as on the portal. --}}
            <div class="portal-card-body portal-card-end">
                @if ($exists)
                    <div class="portal-files pdf-buttons">
                        {{-- QR codes lead to the public check of reports, so only with the website. --}}
                        @if (\App\Support\PublicSite::enabled())
                            <a class="btn" href="{{ route('admin.real-estates.report.pdf', $item->id) }}" data-pdf><x-icon name="qr-code" :size="14" /> {{ __('Report PDF with QR code') }}</a>
                        @endif
                        <a class="btn" href="{{ route('admin.real-estates.report.pdf', [$item->id, 'qr' => 0]) }}" data-pdf><x-icon name="file-text" :size="14" /> {{ __('Report PDF') }}</a>
                        @if (\App\Support\PublicSite::enabled())
                            <a class="btn" href="{{ route($module->routeName('contract.pdf'), [$item->id, 'qr' => 1]) }}" data-pdf><x-icon name="qr-code" :size="14" /> {{ __('Contract PDF with QR codes') }}</a>
                        @endif
                        <a class="btn" href="{{ route($module->routeName('contract.pdf'), $item->id) }}" data-pdf><x-icon name="file-pen-line" :size="14" /> {{ __('Contract PDF') }}</a>
                        <a class="btn" href="{{ route('admin.real-estates.report', $item->id) }}" target="_blank"><x-icon name="scroll-text" :size="14" /> {{ __('Valuation report') }}</a>
                        <a class="btn" href="{{ route($module->routeName('print'), $item->id) }}" target="_blank"><x-icon name="printer" :size="14" /> {{ __('Certificate') }}</a>
                        @if ($canEdit)
                            <a class="btn" href="{{ route('admin.real-estates.report.edit', $item->id) }}"><x-icon name="list-checks" :size="14" /> {{ __('Prepare the report') }}</a>
                        @endif
                    </div>
                @endif

                {{-- The buildings of the cost approach. --}}
                <details class="portal-fold" id="approach-cost" data-remember @if ($exists && in_array(ValuationApproach::Cost, $applied, true)) open @endif>
                    <summary>
                        {{ __('Enter the buildings to be valued') }}
                        @if ($exists && ! in_array(ValuationApproach::Cost, $applied, true))<span class="portal-refused">{{ __('Cost approach') }} ({{ __('refused') }})</span>
                        @elseif ($item->value_cost !== null)<span class="portal-sum">{{ Format::money($item->value_cost) }}</span>@endif
                    </summary>
                    <div class="portal-body portal-cards-flat">
                        @if ($exists)
                            @include('admin.real-estates._portal-approach', ['approach' => ValuationApproach::Cost])
                        @else
                            <p class="muted">{{ __('The approaches are calculated after the object is saved.') }}</p>
                        @endif
                    </div>
                </details>

                @include('admin.real-estates._portal-status')

                <div class="portal-actions portal-bottom">
                    @if (! $exists)
                        <a class="btn btn-fill-danger" href="{{ $back }}">{{ __('Cancel') }}</a>
                    @endif
                    @foreach ($reasons as $action => [$label, $icon, $class])
                        <details class="portal-reason">
                            <summary class="btn {{ $class }}">{{ $label }}</summary>
                            <form method="post" action="{{ route($module->routeName('workflow'), $item->id) }}" class="approval-reason">
                                @csrf <input type="hidden" name="action" value="{{ $action }}">
                                <input class="input input-sm" name="comment" required maxlength="2000" placeholder="{{ __('Reason') }}" aria-label="{{ $label }}: {{ __('Reason') }}">
                                <button class="btn btn-sm {{ $class }}" type="submit">{{ __('Confirm') }}</button>
                            </form>
                        </details>
                    @endforeach
                    @if ($canEdit)
                        <button class="btn btn-success" type="submit" form="object-form">{{ __('Save') }}</button>
                    @endif
                    @if ($exists)
                        @can('delete-records')
                            <form method="post" action="{{ route($module->routeName('destroy'), $item->id) }}" class="portal-delete" data-confirm="{{ __('Delete this record? It can be restored by an administrator.') }}">
                                @csrf @method('delete')
                                <button class="btn btn-danger btn-icon" type="submit" title="{{ __('Delete') }}" aria-label="{{ __('Delete') }}"><x-icon name="trash-2" :size="16" /></button>
                            </form>
                        @endcan
                    @endif
                </div>

                @if ($exists)
                    {{-- The portal signs with E-IMZO; here the report is confirmed by its QR code and verification code. --}}
                    <p class="portal-notice"><x-icon name="triangle-alert" :size="16" /> <strong>{{ __('E-IMZO is not connected!') }}</strong>
                        @if (\App\Support\PublicSite::enabled())
                            {{ __('The report is confirmed by its QR code and verification code on the public page.') }} <span class="code">{{ $item->verify_code }}</span>
                        @endif
                    </p>
                @endif
            </div>
        </section>
    </div>

    @push('scripts')
        <script src="{{ asset('js/vendor/qrcode.js') }}"></script>
    @endpush
</x-layouts.admin>
