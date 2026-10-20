@php
    use App\Enums\RealEstateFeature;
    use App\Enums\ValuationApproach;
    use App\Reports\RealEstateReport;
    use App\Support\Format;

    $labels = [
        'script' => __('Alphabet of the report'), 'report_date' => __('Report date'), 'letter_number' => __('Cover letter number'),
        'value_type' => __('Type of value'), 'currency_text' => __('Currency of the valuation'), 'use_period' => __('Period of use of the results'),
        'report_format' => __('Report format'), 'intended_users' => __('Intended users'), 'deadline' => __('Time to complete the work'),
        'initial_info' => __('Initial information provided by the customer'), 'special_assumptions' => __('Important and special assumptions'),
        'limiting_conditions' => __('Limiting conditions'), 'use_restrictions' => __('Restrictions on the use of the report'),
        'function' => __('Functional purpose'), 'ownership_form' => __('Form of ownership'), 'rights' => __('Rights being valued'),
        'rights_basis' => __('Document establishing the right'), 'land_rights' => __('Rights to the land plot'),
        'encumbrances' => __('Encumbrances and restrictions'), 'ecology' => __('Environmental risks'),
        'development' => __('Development plans (construction, reconstruction, repair)'),
        'land_category' => __('Permitted use of the land'), 'geology' => __('Engineering and geological description'),
        'hydrogeology' => __('Hydrogeological description'), 'topography' => __('Topographic description'),
        'land_improvements' => __('Landscaping elements of the plot'), 'land_hbu' => __('Land use option the value corresponds to'),
        'building_volume' => __('Building volume, m³'), 'completion' => __('Construction completed, %'), 'foundation' => __('Foundation'),
        'walls' => __('Walls'), 'roof_cover' => __('Roof covering'), 'roof_slab' => __('Roof slab'), 'floor_type' => __('Floor covering'),
        'windows_doors' => __('Windows and doors'), 'repair' => __('Repair condition'), 'architecture' => __('Architectural style'),
        'landscaping' => __('Landscaping'), 'surroundings' => __('Surrounding buildings'), 'convertibility' => __('Possibility of changing the use'),
        'reason_comparative' => __('Comparative approach: applied or not, and why'), 'reason_cost' => __('Cost approach: applied or not, and why'),
        'reason_income' => __('Income approach: applied or not, and why'), 'hbu_conclusion' => __('Conclusion on the highest and best use'),
    ];
    $stepInfo = [
        'documents' => [__('Documents'), __('Passport and cadastral extract — the data is read from them'), 'scan-search'],
        'object' => [__('Object and rights'), __('Rights, encumbrances, land plot'), 'building'],
        'inspection' => [__('Inspection'), __('Date, construction, utilities, photos'), 'camera'],
        'analogs' => [__('Market and analogues'), __('Comparable offers and adjustments'), 'scale'],
        'approaches' => [__('Approaches and value'), __('Reasons, weights and the final value'), 'calculator'],
        'report' => [__('Report'), __('Alphabet, numbers, appendices, readiness'), 'scroll-text'],
    ];
    $presets = [
        'panel' => __('Panel apartment block'), 'brick' => __('Brick apartment block'), 'monolith' => __('New monolithic building'),
        'house' => __('Private house'), 'office' => __('Office or commercial building'), 'warehouse' => __('Warehouse or industrial building'),
    ];
    $criteria = [
        __('Reliability and sufficiency of the data used'), __('Ability to take into account the price factors of the object'),
        __('Ability to reflect the real intentions of buyers and sellers'), __('Correspondence to the type of value'),
    ];
    $applied = $item->approachList();
    $done = count(array_filter($steps));
    $guessDocument = fn ($file) => (bool) preg_match('/pasport|passport|паспорт|kadastr|кадастр|guvohnoma|гувоҳнома|ko.?chirma|кўчирма|hujjat|document|scan|скан|shartnoma|id.?card/iu', $file->name) || $file->mime === 'application/pdf';
    $field = function (string $name, string $type, $value, int $span = 4) use ($labels) {
        return compact('name', 'type', 'value', 'span') + ['label' => $labels[$name]];
    };
@endphp
<x-layouts.admin :title="__('Preparing the report')" :subtitle="$item->title" nav="real-estates" icon="scroll-text" :back="route('admin.real-estates.show', $item->id)">
    <x-slot:actions>
        <a class="btn" href="{{ route('admin.real-estates.report', $item->id) }}" target="_blank"><x-icon name="eye" :size="16" /> {{ __('Open the report') }}</a>
    </x-slot:actions>

    <div class="prep">
        <aside class="prep-steps" aria-label="{{ __('Steps') }}">
            <div class="prep-progress">
                <span>{{ __(':done of :total steps done', ['done' => $done, 'total' => count($steps)]) }}</span>
                <i style="--p: {{ round($done / count($steps) * 100) }}%"></i>
            </div>
            <ol>
                @foreach ($steps as $key => $ok)
                    <li @class(['is-done' => $ok])>
                        <a href="#step-{{ $key }}" data-step-link="{{ $key }}">
                            <span class="prep-num">@if ($ok)<x-icon name="check" :size="14" />@else{{ $loop->iteration }}@endif</span>
                            <span><strong>{{ $stepInfo[$key][0] }}</strong><small>{{ $stepInfo[$key][1] }}</small></span>
                        </a>
                    </li>
                @endforeach
            </ol>
        </aside>

        <div class="prep-main">
            {{-- ============ 1. Documents ============ --}}
            <x-card :title="'1 · '.$stepInfo['documents'][0]" :icon="$stepInfo['documents'][2]" id="step-documents" class="prep-step" data-step="documents">
                <x-slot:actions>@include('admin.real-estates._step-badge', ['ok' => $steps['documents']])</x-slot:actions>
                <div class="prep-two">
                    <form method="post" action="{{ route('admin.attachments.store') }}" enctype="multipart/form-data" data-upload>
                        @csrf
                        <input type="hidden" name="type" value="{{ $item->getMorphClass() }}">
                        <input type="hidden" name="id" value="{{ $item->getKey() }}">
                        <label class="dropzone" data-dropzone>
                            <x-icon name="upload" :size="22" />
                            <span><strong>{{ __('Upload scans or photos') }}</strong> {{ __('or drag them here') }}</span>
                            <span class="faint">{{ __('ID card (both sides), cadastral extract, technical passport, photos of the object') }}</span>
                            <input type="file" name="files[]" multiple accept=".pdf,.jpg,.jpeg,.png,.webp" data-upload-input>
                        </label>
                        @error('files')<p class="field-error">{{ $message }}</p>@enderror
                        @error('files.*')<p class="field-error">{{ $message }}</p>@enderror
                    </form>

                    <form method="post" action="{{ route('admin.real-estates.documents.read', $item->id) }}" class="doc-ai">
                        @csrf
                        <p class="analog-tool-title"><x-icon name="sparkles" :size="18" /> {{ __('Fill in from the documents') }}</p>
                        @if ($scans->isEmpty())
                            <p class="field-hint">{{ __('Upload the passport and the cadastral extract first.') }}</p>
                        @else
                            <div class="doc-ai-files">
                                @foreach ($scans as $file)
                                    <label class="doc-ai-file">
                                        <input type="checkbox" name="files[]" value="{{ $file->id }}" @checked($guessDocument($file))>
                                        @if ($file->isImage())
                                            <img src="{{ $file->isEditableImage() ? route('admin.attachments.show', [$file, 'thumb' => 1, 'v' => $file->updated_at?->timestamp]) : route('admin.attachments.show', $file) }}" alt="" loading="lazy">
                                        @else
                                            <span class="doc-read-pdf"><x-icon name="file-text" :size="22" /></span>
                                        @endif
                                        <small title="{{ $file->name }}">{{ $file->name }}</small>
                                    </label>
                                @endforeach
                            </div>
                        @endif
                        <p class="field-hint">
                            @if ($aiAvailable)
                                {{ __('AI reads the owner (name, PINFL, passport) and the object (cadastral number, areas, address, rights) from the chosen files. You check every value before it is saved. The files are sent to Claude (Anthropic) for reading.') }}
                            @else
                                {{ __('Reading documents needs an AI key (ANTHROPIC_API_KEY in .env).') }}
                            @endif
                        </p>
                        <button class="btn btn-primary" type="submit" @disabled(! $aiAvailable || $scans->isEmpty()) data-busy="{{ __('Reading the documents…') }}"><x-icon name="scan-search" :size="16" /> {{ __('Read the documents') }}</button>
                    </form>
                </div>
                <div class="prep-facts">
                    @php $owner = $item->owner(); @endphp
                    <div><span>{{ __('Owner') }}</span><strong>{{ $owner?->title ?? '—' }}</strong>@if ($owner?->pinfl)<small>{{ __('PINFL') }} {{ $owner->pinfl }}</small>@endif</div>
                    <div><span>{{ __('Cadastral number') }}</span><strong>{{ $item->cadastral_number ?: '—' }}</strong></div>
                    <div><span>{{ __('Total area') }}</span><strong>{{ $item->total_area ? Format::number($item->total_area, 2).' '.__('m²') : '—' }}</strong></div>
                    <div><span>{{ __('Location') }}</span><strong>{{ $item->locationLine() ?: '—' }}</strong></div>
                    <a class="btn btn-sm" href="{{ route('admin.real-estates.edit', $item->id) }}"><x-icon name="pencil" :size="14" /> {{ __('Edit the object') }}</a>
                </div>
            </x-card>

            <form method="post" action="{{ route('admin.real-estates.report.update', $item->id) }}" id="report-form" class="form-layout" data-report-form>
                @csrf @method('put')
                <input type="hidden" name="step" value="" data-current-step>

                {{-- ============ 2. Object and rights ============ --}}
                <x-card :title="'2 · '.$stepInfo['object'][0]" :icon="$stepInfo['object'][2]" id="step-object" class="prep-step" data-step="object">
                    <x-slot:actions>@include('admin.real-estates._step-badge', ['ok' => $steps['object']])</x-slot:actions>
                    <div class="form-grid">
                        @foreach (['function', 'ownership_form', 'rights', 'rights_basis', 'land_rights', 'encumbrances', 'ecology', 'development'] as $name)
                            @include('admin.real-estates._report-field', $field($name, 'text', $values[$name] ?? null, in_array($name, ['rights_basis', 'development'], true) ? 8 : 4))
                        @endforeach
                    </div>
                    <details class="prep-more">
                        <summary>{{ __('Land plot') }}</summary>
                        <div class="form-grid">
                            @foreach (['land_category', 'geology', 'hydrogeology', 'topography', 'land_improvements', 'land_hbu'] as $name)
                                @include('admin.real-estates._report-field', $field($name, 'text', $values[$name] ?? null, 4))
                            @endforeach
                        </div>
                    </details>
                </x-card>

                {{-- ============ 3. Inspection ============ --}}
                <x-card :title="'3 · '.$stepInfo['inspection'][0]" :icon="$stepInfo['inspection'][2]" id="step-inspection" class="prep-step" data-step="inspection">
                    <x-slot:actions>@include('admin.real-estates._step-badge', ['ok' => $steps['inspection']])</x-slot:actions>
                    <div class="preset-bar">
                        <x-field name="inspection_date" type="date" :label="__('Inspection date')" :value="$item->inspection_date" span="12" />
                        <div class="field">
                            <label for="preset">{{ __('Fill in by building type') }}</label>
                            <div class="preset-pick">
                                <select class="input" id="preset" data-presets='@json(RealEstateReport::PRESETS)'>
                                    <option value="">{{ __('Choose…') }}</option>
                                    @foreach ($presets as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach
                                </select>
                                <button class="btn" type="button" data-preset-apply><x-icon name="sparkles" :size="16" /> {{ __('Fill in') }}</button>
                            </div>
                            <p class="field-hint">{{ __('Fills the construction fields with typical values; correct what differs.') }}</p>
                        </div>
                    </div>
                    <div class="form-grid">
                        @foreach (['foundation', 'walls', 'roof_slab', 'roof_cover', 'floor_type', 'windows_doors', 'repair', 'architecture', 'landscaping', 'surroundings'] as $name)
                            @include('admin.real-estates._report-field', $field($name, 'text', $values[$name] ?? null, 4))
                        @endforeach
                        <div class="field span-4">
                            <label for="f-building_volume">{{ $labels['building_volume'] }}</label>
                            <div class="volume-pick">
                                <input class="input" id="f-building_volume" name="building_volume" value="{{ old('building_volume', $values['building_volume']) }}" inputmode="decimal">
                                <input class="input" type="number" step="0.1" min="2" max="20" value="3" aria-label="{{ __('Ceiling height, m') }}" title="{{ __('Ceiling height, m') }}" data-volume-height>
                                <button class="btn btn-icon" type="button" data-volume data-area="{{ (float) $item->total_area }}" title="{{ __('Area × ceiling height') }}" aria-label="{{ __('Area × ceiling height') }}"><x-icon name="calculator" :size="16" /></button>
                            </div>
                            <p class="field-hint">{{ __('Area × ceiling height (m)') }}</p>
                        </div>
                        @include('admin.real-estates._report-field', $field('completion', 'number', $values['completion'] ?? null, 2))
                        @include('admin.real-estates._report-field', $field('convertibility', 'text', $values['convertibility'] ?? null, 6))
                    </div>

                    <h3 class="sub-title">{{ __('Utilities and amenities') }}</h3>
                    <input type="hidden" name="features_shown" value="1">
                    <div class="check-grid">
                        @foreach (RealEstateFeature::cases() as $feature)
                            <label class="check">
                                <input type="checkbox" name="features[]" value="{{ $feature->value }}" @checked(in_array($feature->value, (array) old('features', $item->features ?? []), true))>
                                <span class="check-box"><x-icon name="check" :size="14" /></span>
                                <span>{{ $feature->label() }}</span>
                            </label>
                        @endforeach
                    </div>

                    <h3 class="sub-title">{{ __('Photos and scans in the report') }}</h3>
                    @if ($images->isEmpty())
                        <p class="field-hint">{{ __('Upload photos of the object in step 1 — they are added to the report.') }}</p>
                    @else
                        <p class="field-hint" style="margin-bottom: 12px">{{ __('Photos are placed four to a page, documents one to a page.') }}</p>
                        <div class="report-images">
                            @foreach ($images as $file)
                                <div class="report-image">
                                    <img src="{{ $file->isEditableImage() ? route('admin.attachments.show', [$file, 'thumb' => 1, 'v' => $file->updated_at?->timestamp]) : route('admin.attachments.show', $file) }}" alt="{{ $file->name }}" loading="lazy">
                                    <small title="{{ $file->name }}">{{ $file->name }}</small>
                                    <select class="input" name="attachments[{{ $file->id }}]" aria-label="{{ $file->name }}">
                                        <option value="photo" @selected($file->report_role === 'photo')>{{ __('Photo of the object') }}</option>
                                        <option value="document" @selected($file->report_role === 'document')>{{ __('Document') }}</option>
                                        <option value="skip" @selected($file->report_role === 'skip')>{{ __('Not in the report') }}</option>
                                    </select>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </x-card>

                {{-- ============ 4. Market and analogues ============ --}}
                <x-card :title="'4 · '.$stepInfo['analogs'][0]" :icon="$stepInfo['analogs'][2]" id="step-analogs" class="prep-step" data-step="analogs">
                    <x-slot:actions>@include('admin.real-estates._step-badge', ['ok' => $steps['analogs']])</x-slot:actions>
                    @if (! in_array(ValuationApproach::Comparative, $applied, true))
                        <p class="field-hint">{{ __('The comparative approach is not chosen for this object.') }}</p>
                    @else
                        <div class="analog-summary">
                            <div class="result-box"><span>{{ __('Analogues used') }}</span><strong>{{ $comparative['count'] }}</strong></div>
                            <div class="result-box"><span>{{ __('Weighted price per m²') }}</span><strong>{{ $comparative['price_m2'] ? Format::money(round($comparative['price_m2'])) : '—' }}</strong></div>
                            <div @class(['result-box', 'is-warning' => ($comparative['variation'] ?? 0) > 33])><span>{{ __('Spread of analogues') }}</span><strong>{{ $comparative['variation'] !== null ? Format::number($comparative['variation'], 1).' %' : '—' }}</strong></div>
                            <div class="result-box is-main"><span>{{ __('Value by the comparative approach') }}</span><strong>{{ $comparative['value'] !== null ? Format::money($comparative['value']) : '—' }}</strong></div>
                        </div>
                        <p class="field-hint" style="margin-top: 12px">{{ __('Analogues are found, read and adjusted on the object page. At least three are needed.') }}</p>
                    @endif
                    <a class="btn" href="{{ route('admin.real-estates.show', $item->id) }}#analogs" style="margin-top: 12px"><x-icon name="scale" :size="16" /> {{ __('Go to the analogues') }}</a>
                </x-card>

                {{-- ============ 5. Approaches and value ============ --}}
                <x-card :title="'5 · '.$stepInfo['approaches'][0]" :icon="$stepInfo['approaches'][2]" id="step-approaches" class="prep-step" data-step="approaches">
                    <x-slot:actions>@include('admin.real-estates._step-badge', ['ok' => $steps['approaches']])</x-slot:actions>
                    <div class="form-grid">
                        @foreach (['reason_comparative', 'reason_cost', 'reason_income', 'hbu_conclusion'] as $name)
                            @include('admin.real-estates._report-field', $field($name, 'textarea', $values[$name] ?? null, 12))
                        @endforeach
                    </div>

                    <h3 class="sub-title">{{ __('Reconciliation of the approaches') }}</h3>
                    <p class="field-hint">{{ __('Score each applied approach by each criterion (0–10). The weights of the approaches are the shares of their scores.') }}</p>
                    <div class="table-wrap">
                        <table class="report-scores" data-scores>
                            <thead>
                                <tr>
                                    <th>{{ __('Criterion') }}</th>
                                    @foreach (ValuationApproach::cases() as $approach)<th class="num">{{ $approach->short() }}</th>@endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($criteria as $i => $criterion)
                                    <tr>
                                        <td>{{ $criterion }}</td>
                                        @foreach (ValuationApproach::cases() as $approach)
                                            <td class="num">
                                                @if (in_array($approach, $applied, true))
                                                    <input class="input" name="scores[{{ $i }}][{{ $approach->value }}]" value="{{ old("scores.$i.{$approach->value}", $values['scores'][$i][$approach->value] ?? 0) }}" inputmode="numeric" data-score="{{ $approach->value }}" aria-label="{{ $criterion }} — {{ $approach->label() }}">
                                                @else
                                                    <span class="faint" title="{{ __('Not applied') }}">—</span>
                                                @endif
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr><td>{{ __('Value by the approach') }}</td>
                                    @foreach (ValuationApproach::cases() as $approach)
                                        <td class="num" data-value="{{ $approach->value }}" data-amount="{{ in_array($approach, $applied, true) ? (float) $item->{$approach->column()} : 0 }}">{{ in_array($approach, $applied, true) && $item->{$approach->column()} !== null ? Format::number($item->{$approach->column()}) : '—' }}</td>
                                    @endforeach
                                </tr>
                                <tr><td>{{ __('Weight') }}</td>
                                    @foreach (ValuationApproach::cases() as $approach)<td class="num strong" data-weight="{{ $approach->value }}">—</td>@endforeach
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    <div class="final-pick">
                        <x-field name="market_value" :label="__('Final market value')" :value="$item->market_value" money :suffix="__('sum')" span="12" />
                        <div class="final-hint">
                            <span>{{ __('Reconciled value') }}: <strong data-reconciled>—</strong></span>
                            <button class="btn btn-sm" type="button" data-use-reconciled><x-icon name="check" :size="14" /> {{ __('Use it (rounded to thousands)') }}</button>
                        </div>
                    </div>
                </x-card>

                {{-- ============ 6. Report ============ --}}
                <x-card :title="'6 · '.$stepInfo['report'][0]" :icon="$stepInfo['report'][2]" id="step-report" class="prep-step" data-step="report">
                    <x-slot:actions>@include('admin.real-estates._step-badge', ['ok' => $steps['report']])</x-slot:actions>
                    <div class="form-grid">
                        <x-select name="script" :label="$labels['script']" :options="['latn' => 'O‘zbekcha (lotin)', 'cyrl' => 'Ўзбекча (кирилл)']" :value="$values['script']" span="4" />
                        <x-field name="report_date" type="date" :label="$labels['report_date']" :value="$values['report_date']" span="4" />
                        @foreach (['letter_number', 'value_type', 'currency_text', 'use_period', 'report_format', 'intended_users', 'deadline'] as $name)
                            @include('admin.real-estates._report-field', $field($name, 'text', $values[$name] ?? null, 4))
                        @endforeach
                    </div>
                    <details class="prep-more">
                        <summary>{{ __('Assumptions and restrictions') }}</summary>
                        <div class="form-grid">
                            @foreach (['initial_info', 'special_assumptions', 'limiting_conditions', 'use_restrictions'] as $name)
                                @include('admin.real-estates._report-field', $field($name, 'textarea', $values[$name] ?? null, 12))
                            @endforeach
                        </div>
                    </details>

                    <h3 class="sub-title">{{ __('Appendices') }}</h3>
                    <div class="check-grid">
                        @foreach (['company_documents' => __('Company documents (:count)', ['count' => count($companyDocuments)]), 'include_macro' => __('Macroeconomic section'), 'include_methodology' => __('Description of the approaches and methods')] as $flag => $label)
                            <label class="check">
                                <input type="checkbox" name="{{ $flag }}" value="1" @checked(old($flag, $values[$flag]))>
                                <span class="check-box"><x-icon name="check" :size="14" /></span>
                                <span>{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>

                    <h3 class="sub-title">{{ __('Readiness') }}</h3>
                    <ul class="check-list-sys report-checklist">
                        @foreach ($checklist as $label => $ok)
                            <li @class(['sys-ok' => $ok, 'sys-warn' => ! $ok])>
                                <x-icon :name="$ok ? 'circle-check' : 'triangle-alert'" :size="16" />
                                <span class="grow">{{ $label }}</span>
                            </li>
                        @endforeach
                    </ul>
                    @can('admin')
                        <p class="field-hint" style="margin-top: 10px">{!! __('Company documents and the standard texts are set in :settings and :texts.', [
                            'settings' => '<a href="'.e(route('admin.settings.edit')).'">'.e(__('Settings')).'</a>',
                            'texts' => '<a href="'.e(route('admin.report-texts.edit')).'">'.e(__('Report texts')).'</a>',
                        ]) !!}</p>
                    @endcan
                </x-card>

                <div class="card form-actions">
                    <a class="btn btn-ghost" href="{{ route('admin.real-estates.show', $item->id) }}">{{ __('Cancel') }}</a>
                    <button class="btn" type="submit"><x-icon name="check" :size="16" /> {{ __('Save') }}</button>
                    <button class="btn btn-primary" type="submit" name="then" value="open"><x-icon name="scroll-text" :size="16" /> {{ __('Save and open the report') }}</button>
                </div>
            </form>
        </div>
    </div>

    @foreach (RealEstateReport::OPTIONS as $name => $options)
        <datalist id="opt-{{ $name }}">@foreach ($options as $option)<option value="{{ $option }}">@endforeach</datalist>
    @endforeach
</x-layouts.admin>
