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
                            @if ($applied === [])
                                <p class="muted">{{ __('Approaches are not specified') }}</p>
                            @else
                                <div class="table-wrap">
                                    <table class="table table-compact portal-table">
                                        <thead><tr><th>{{ __('Approach') }}</th><th class="num">{{ __('Value by the approach') }}</th><th class="num">{{ __('Weight') }}</th></tr></thead>
                                        <tbody>
                                            @foreach ($applied as $approach)
                                                @php $value = $item->{$approach->column()}; @endphp
                                                <tr>
                                                    <td>{{ $approach->label() }}</td>
                                                    <td class="num">{{ $value !== null ? Format::money($value) : '—' }}</td>
                                                    <td class="num">{{ Format::number($reconciliation['weights'][$approach->value] * 100, 1) }} %</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                        <tfoot>
                                            <tr><td>{{ __('Reconciled value') }}</td><td class="num">{{ $reconciliation['reconciled'] > 0 ? Format::money(round($reconciliation['reconciled'], -3)) : '—' }}</td><td class="num">100 %</td></tr>
                                        </tfoot>
                                    </table>
                                </div>
                            @endif
                            <form method="post" action="{{ route('admin.real-estates.value', $item->id) }}" class="portal-value">
                                @csrf @method('put')
                                <fieldset class="form-grid" @disabled(! $canEdit)>
                                    <x-field name="market_value" :label="__('Market value (final)')" :value="$item->market_value" money :suffix="__('sum')" span="4"
                                             :hint="($usdValue ?? null) ? '≈ '.Format::number($usdValue).' USD · '.__('at the Central Bank rate') : null" />
                                    <div class="field span-8 portal-value-actions">
                                        @if ($canEdit)
                                            <button class="btn btn-success" type="submit"><x-icon name="check" :size="16" /> {{ __('Save') }}</button>
                                            @if ($reconciliation['reconciled'] > 0)
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
                        <a class="btn" href="{{ route('admin.real-estates.report.pdf', $item->id) }}" data-pdf><x-icon name="qr-code" :size="14" /> {{ __('Report PDF with QR code') }}</a>
                        <a class="btn" href="{{ route('admin.real-estates.report.pdf', [$item->id, 'qr' => 0]) }}" data-pdf><x-icon name="file-text" :size="14" /> {{ __('Report PDF') }}</a>
                        <a class="btn" href="{{ route($module->routeName('contract.pdf'), [$item->id, 'qr' => 1]) }}" data-pdf><x-icon name="qr-code" :size="14" /> {{ __('Contract PDF with QR codes') }}</a>
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
                        {{ __('The report is confirmed by its QR code and verification code on the public page.') }} <span class="code">{{ $item->verify_code }}</span></p>
                @endif
            </div>
        </section>
    </div>

    @push('scripts')
        <script src="{{ asset('js/vendor/qrcode.js') }}"></script>
    @endpush
</x-layouts.admin>
