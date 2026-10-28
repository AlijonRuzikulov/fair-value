{{--
    The page of real estate in the classic look, laid out as on the valuation portal the appraisers know:
    the four steps, the main data typed right here, then the documents, the approaches, the reconciliation,
    the files and the status with the history.
--}}
@php
    use App\Enums\PersonRole;
    use App\Enums\ValuationApproach;
    use App\Reports\RealEstateReport;
    use App\Support\Format;
    use Illuminate\Support\Facades\Gate;

    $canEdit = Gate::allows('edit-valuation', $item);
    $applied = $item->approachList();
    $reportValues = RealEstateReport::values($item);
    $reconciliation = RealEstateReport::reconciliation($item, $reportValues['scores']);
    $approachTitles = [
        'comparative' => __('Comparative approach'),
        'cost' => __('Cost approach'),
        'income' => __('Income approach'),
    ];
    $analogType = array_search($item::class, \App\Services\Analogs\AnalogFinder::OBJECTS, true);
@endphp
<x-layouts.admin :title="$item->title" :subtitle="$module->singular()" :nav="$nav" :icon="$module->icon()" :back="route($module->routeName('index'), $module->routeParams($item))">
    <x-slot:actions>
        @can('edit-valuation', $item)
            <a class="btn" href="{{ route($module->routeName('edit'), $item->id) }}"><x-icon name="pencil" :size="16" /> {{ __('Edit') }}</a>
        @endcan
        @can('delete-records')
            <form method="post" action="{{ route($module->routeName('destroy'), $item->id) }}" data-confirm="{{ __('Delete this record? It can be restored by an administrator.') }}">
                @csrf @method('delete')
                <button class="btn btn-danger btn-icon" type="submit" title="{{ __('Delete') }}" aria-label="{{ __('Delete') }}"><x-icon name="trash-2" :size="16" /></button>
            </form>
        @endcan
    </x-slot:actions>

    <div class="portal">
        @include('admin.real-estates._portal-head', ['headLinks' => [
            [__('Back to the list'), route($module->routeName('index'), $module->routeParams($item)), 'btn-success', 'list'],
        ]])

        @if ($errors->any())
            <div class="toast toast-error" role="alert"><x-icon name="triangle-alert" :size="18" /> {{ __('Please correct the highlighted fields.') }}</div>
        @endif

        {{-- 1. The main data, saved on this page. --}}
        <details class="portal-section" id="main" open>
            <summary>{{ __('Main data of the real estate') }}</summary>
            <form method="post" action="{{ route('admin.real-estates.main', $item->id) }}" class="portal-body portal-form" data-object-form>
                @csrf @method('put')
                <fieldset class="form-grid" @disabled(! $canEdit)>
                    @include('admin.real-estates._main-fields')
                </fieldset>

                <details class="portal-sub" open>
                    <summary>{{ __('Location on the map') }}</summary>
                    <fieldset class="form-grid" @disabled(! $canEdit)>
                        @include('admin.real-estates._map-picker', ['mapSpans' => [3, 6]])
                    </fieldset>
                </details>

                <div class="portal-actions">
                    @if ($canEdit)
                        <button class="btn btn-success" type="submit"><x-icon name="check" :size="16" /> {{ __('Save') }}</button>
                        <a class="btn btn-success" href="{{ route('admin.real-estates.report.edit', $item->id) }}#step-inspection"><x-icon name="clipboard-check" :size="16" /> {{ __('Inspection act') }}</a>
                        <a class="btn btn-info" href="{{ route('admin.real-estates.report.edit', $item->id) }}#survey"><x-icon name="list-checks" :size="16" /> {{ __('Questionnaire') }}</a>
                    @endif
                </div>
            </form>
        </details>

        {{-- 2. Persons --}}
        <details class="portal-section" id="persons" open>
            <summary>{{ __('Persons') }} <span class="portal-count">{{ $item->clients->count() }}</span></summary>
            <div class="portal-body">
                @if ($item->clients->isEmpty())
                    <p class="muted">{{ __('No persons linked') }}</p>
                @else
                    <div class="table-wrap">
                    <table class="table table-compact">
                        <tbody>
                            @foreach ($item->clients as $client)
                                <tr>
                                    <td><span class="badge badge-brand">{{ PersonRole::labelFor($client->pivot->role) }}</span></td>
                                    <td><a class="cell-title" href="{{ route('admin.clients.show', $client) }}">{{ $client->title }}</a></td>
                                    <td>{{ $client->document }}</td>
                                    <td class="nowrap">{{ $client->phone ? Format::phone($client->phone) : '' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    </div>
                @endif
                @can('edit-valuation', $item)
                    <div class="portal-actions"><a class="btn btn-sm" href="{{ route($module->routeName('edit'), $item->id) }}#persons"><x-icon name="user-plus" :size="14" /> {{ __('Add or change persons') }}</a></div>
                @endcan
            </div>
        </details>

        {{-- 3. Documents --}}
        <details class="portal-section" id="documents-section" open>
            <summary>{{ __('Documents') }}</summary>
            <div class="portal-body portal-cards-flat">
                @include('admin.objects._documents', ['owner' => $item])
            </div>
        </details>

        @if ($item->featureList() || $item->hasLocation())
            <details class="portal-section" id="amenities" open>
                <summary>{{ __('Special amenities and the map') }}</summary>
                <div class="portal-body portal-cards-flat">@include('admin.real-estates._show')</div>
            </details>
        @endif

        {{-- 4. The approaches: each applied, or refused with the reason. --}}
        @foreach ([ValuationApproach::Comparative, ValuationApproach::Income, ValuationApproach::Cost] as $approach)
            @php
                $isApplied = in_array($approach, $applied, true);
                $value = $item->{$approach->column()};
            @endphp
            <details class="portal-section" id="approach-{{ $approach->value }}" @if ($isApplied) open @endif>
                <summary>
                    {{ $approachTitles[$approach->value] }}
                    @if (! $isApplied)<span class="portal-refused">({{ __('refused') }})</span>@endif
                    @if ($isApplied && $value !== null)<span class="portal-sum">{{ Format::money($value) }}</span>@endif
                </summary>
                <div class="portal-body portal-cards-flat">
                    @if ($isApplied)
                        @if ($approach === ValuationApproach::Comparative && $analogType)
                            @include('admin.objects._analogs', ['analogType' => $analogType, 'aiAvailable' => app(\App\Services\Analogs\ListingAi::class)->available()])
                        @elseif ($approach !== ValuationApproach::Comparative)
                            @include('admin.real-estates._approaches', ['only' => $approach->value])
                        @endif
                        @if ($canEdit)
                            <form method="post" action="{{ route('admin.real-estates.approaches.toggle', [$item->id, $approach->value]) }}" class="portal-refuse">
                                @csrf @method('put')
                                <input type="hidden" name="applied" value="0">
                                <input class="input input-sm" name="reason" maxlength="2000" placeholder="{{ __('Why the approach is not applied (printed in the report)') }}" aria-label="{{ __('Reason') }}">
                                <button class="btn btn-sm btn-danger" type="submit"><x-icon name="circle-x" :size="14" /> {{ __('Refuse the approach') }}</button>
                            </form>
                        @endif
                    @else
                        <p class="portal-reason-text">{{ $reportValues['reason_'.$approach->value] }}</p>
                        @if ($canEdit)
                            <form method="post" action="{{ route('admin.real-estates.approaches.toggle', [$item->id, $approach->value]) }}">
                                @csrf @method('put')
                                <input type="hidden" name="applied" value="1">
                                <button class="btn btn-sm btn-success" type="submit"><x-icon name="check" :size="14" /> {{ __('Apply the approach') }}</button>
                            </form>
                        @endif
                        {{-- Analogues can be gathered before the approach is applied. --}}
                        @if ($approach === ValuationApproach::Comparative && $analogType)
                            @include('admin.objects._analogs', ['analogType' => $analogType, 'aiAvailable' => app(\App\Services\Analogs\ListingAi::class)->available()])
                        @endif
                    @endif
                </div>
            </details>
        @endforeach

        {{-- 5. Reconciliation --}}
        <details class="portal-section" id="reconciliation" open>
            <summary>
                {{ __('Reconciliation of the results') }}
                @if ($item->market_value !== null)<span class="portal-sum">{{ Format::money($item->market_value) }}</span>@endif
            </summary>
            <div class="portal-body">
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
                                        <td>{{ $approachTitles[$approach->value] }}</td>
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
                                 :hint="$usdValue ? '≈ '.Format::number($usdValue).' USD · '.__('at the Central Bank rate') : null" />
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
            </div>
        </details>

        {{-- 6. Files --}}
        <div class="portal-files pdf-buttons">
            <a class="btn" href="{{ route('admin.real-estates.report.pdf', $item->id) }}" data-pdf><x-icon name="qr-code" :size="14" /> {{ __('Report PDF with QR code') }}</a>
            <a class="btn" href="{{ route('admin.real-estates.report.pdf', [$item->id, 'qr' => 0]) }}" data-pdf><x-icon name="file-text" :size="14" /> {{ __('Report PDF') }}</a>
            <a class="btn" href="{{ route($module->routeName('contract.pdf'), [$item->id, 'qr' => 1]) }}" data-pdf><x-icon name="qr-code" :size="14" /> {{ __('Contract PDF with QR codes') }}</a>
            <a class="btn" href="{{ route($module->routeName('contract.pdf'), $item->id) }}" data-pdf><x-icon name="file-pen-line" :size="14" /> {{ __('Contract PDF') }}</a>
            <a class="btn" href="{{ route('admin.real-estates.report', $item->id) }}" target="_blank"><x-icon name="scroll-text" :size="14" /> {{ __('Valuation report') }}</a>
            <a class="btn" href="{{ route($module->routeName('print'), $item->id) }}" target="_blank"><x-icon name="printer" :size="14" /> {{ __('Certificate') }}</a>
            @can('edit-valuation', $item)
                <a class="btn" href="{{ route('admin.real-estates.report.edit', $item->id) }}"><x-icon name="list-checks" :size="14" /> {{ __('Prepare the report') }}</a>
            @endcan
        </div>

        {{-- 7. Status, history and the steps with a reason. --}}
        @include('admin.objects._workflow', ['portal' => true])

        <div class="portal-facts">
            <div><span>{{ __('Appraiser') }}</span><strong>{{ $item->appraiser?->name ?? '—' }}</strong></div>
            <div><span>{{ __('Service fee') }}</span><strong>{{ $item->service_fee !== null ? Format::money($item->service_fee) : '—' }}</strong></div>
            @if ($item->service_fee > 0)
                @php $debt = max(0, (float) $item->service_fee - (float) $item->paid_amount); @endphp
                <div><span>{{ __('Debt') }}</span><strong>@if ($debt > 0)<span class="badge badge-warning">{{ Format::money($debt) }}</span>@else<span class="badge badge-success">{{ __('Paid in full') }}</span>@endif</strong></div>
            @endif
            <div><span>{{ __('Verification code') }}</span><strong class="code">{{ $item->verify_code }}</strong> <a href="{{ $item->verifyUrl() }}" target="_blank">{{ __('Public page') }}</a></div>
            <div><span>{{ __('Created') }}</span><strong>{{ Format::datetime($item->created_at) }}{{ $item->creator ? ' · '.$item->creator->name : '' }}</strong></div>
        </div>

        @if ($item->activities->isNotEmpty())
            <details class="portal-section" id="changes">
                <summary>{{ __('Changes') }}</summary>
                <div class="portal-body portal-cards-flat">@include('admin.objects._activity', ['activities' => $item->activities])</div>
            </details>
        @endif

        @if ($item->notes)
            <details class="portal-section" open>
                <summary>{{ __('Notes') }}</summary>
                <div class="portal-body"><p style="white-space: pre-line">{{ $item->notes }}</p></div>
            </details>
        @endif

        {{-- The portal signs reports with E-IMZO; here they are confirmed by the QR code and the verification code. --}}
        <p class="portal-notice"><x-icon name="info" :size="16" /> {{ __('Electronic signature (E-IMZO) is not connected: the report is confirmed by its QR code and verification code on the public page.') }}</p>
    </div>
</x-layouts.admin>
