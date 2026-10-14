@php
    use App\Enums\ValuationApproach;
    use App\Reports\RealEstateReport;

    $labels = [
        'script' => __('Alphabet of the report'), 'report_date' => __('Report date'), 'letter_number' => __('Cover letter number'),
        'value_type' => __('Type of value'), 'currency_text' => __('Currency of the valuation'), 'use_period' => __('Period of use of the results'),
        'report_format' => __('Report format'), 'intended_users' => __('Intended users'), 'deadline' => __('Time to complete the work'),
        'initial_info' => __('Initial information provided by the customer'), 'special_assumptions' => __('Important and special assumptions'),
        'limiting_conditions' => __('Limiting conditions'), 'use_restrictions' => __('Restrictions on the use of the report'),
        'function' => __('Functional purpose'), 'ownership_form' => __('Form of ownership'), 'rights' => __('Rights being valued'),
        'land_rights' => __('Rights to the land plot'), 'encumbrances' => __('Encumbrances and restrictions'), 'ecology' => __('Environmental risks'),
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
    $groups = [
        'Report' => [__('Report'), 'file-text'], 'Object and rights' => [__('Object and rights'), 'scale'], 'Land plot' => [__('Land plot'), 'map'],
        'Building' => [__('Building'), 'building'], 'Approaches' => [__('Approaches'), 'list-checks'],
    ];
    $criteria = [
        __('Reliability and sufficiency of the data used'), __('Ability to take into account the price factors of the object'),
        __('Ability to reflect the real intentions of buyers and sellers'), __('Correspondence to the type of value'),
    ];
    $applied = $item->approachList();
    $done = count(array_filter($checklist));
@endphp
<x-layouts.admin :title="__('Valuation report')" :subtitle="$item->title" nav="real-estates" icon="scroll-text" :back="route('admin.real-estates.show', $item->id)">
    <x-slot:actions>
        <a class="btn" href="{{ route('admin.real-estates.report', $item->id) }}" target="_blank"><x-icon name="eye" :size="16" /> {{ __('Open the report') }}</a>
    </x-slot:actions>

    <form method="post" class="form-layout" action="{{ route('admin.real-estates.report.update', $item->id) }}">
        @csrf @method('put')

        <x-card :title="__('Readiness')" icon="clipboard-check">
            <x-slot:actions><span @class(['badge', 'badge-success' => $done === count($checklist), 'badge-warning' => $done < count($checklist)])>{{ $done }} / {{ count($checklist) }}</span></x-slot:actions>
            <ul class="check-list-sys report-checklist">
                @foreach ($checklist as $label => $ok)
                    <li @class(['sys-ok' => $ok, 'sys-warn' => ! $ok])>
                        <x-icon :name="$ok ? 'circle-check' : 'triangle-alert'" :size="16" />
                        <span class="grow">{{ $label }}</span>
                    </li>
                @endforeach
            </ul>
            <p class="field-hint" style="margin-top: 10px">{{ __('The report can be opened at any time; missing data is shown with a dash.') }}</p>
        </x-card>

        @foreach (RealEstateReport::FORM as $group => $fields)
            <x-card :title="$groups[$group][0]" :icon="$groups[$group][1]">
                <div class="form-grid">
                    @foreach ($fields as $field => $type)
                        @php $value = $values[$field] ?? null; @endphp
                        @if ($field === 'script')
                            <x-select name="script" :label="$labels[$field]" :options="['cyrl' => 'Ўзбекча (кирилл)', 'latn' => 'O‘zbekcha (lotin)']" :value="$value" span="4" />
                        @elseif ($type === 'textarea')
                            <x-textarea :name="$field" :label="$labels[$field]" :value="$value" rows="2" />
                        @elseif ($type === 'date')
                            <x-field :name="$field" type="date" :label="$labels[$field]" :value="$value" span="4" />
                        @elseif ($type === 'number')
                            <x-field :name="$field" :label="$labels[$field]" :value="$value" money span="3" />
                        @else
                            <x-field :name="$field" :label="$labels[$field]" :value="$value" :span="mb_strlen((string) $value) > 40 ? 8 : 4" />
                        @endif
                    @endforeach
                </div>
                @if ($group === 'Approaches')
                    <h3 class="sub-title">{{ __('Reconciliation of the approaches') }}</h3>
                    <p class="field-hint">{{ __('Score each applied approach by each criterion (0–10). The weights of the approaches are the shares of their scores.') }}</p>
                    <div class="table-wrap">
                        <table class="report-scores">
                            <thead>
                                <tr>
                                    <th>{{ __('Criterion') }}</th>
                                    @foreach (ValuationApproach::cases() as $approach)
                                        <th class="num">{{ $approach->short() }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($criteria as $i => $criterion)
                                    <tr>
                                        <td>{{ $criterion }}</td>
                                        @foreach (ValuationApproach::cases() as $approach)
                                            <td class="num">
                                                @if (in_array($approach, $applied, true))
                                                    <input class="input" name="scores[{{ $i }}][{{ $approach->value }}]" value="{{ old("scores.$i.{$approach->value}", $values['scores'][$i][$approach->value] ?? 0) }}" inputmode="numeric" aria-label="{{ $criterion }} — {{ $approach->label() }}">
                                                @else
                                                    <span class="faint" title="{{ __('Not applied') }}">—</span>
                                                @endif
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-card>
        @endforeach

        <x-card :title="__('Appendices')" icon="paperclip">
            @if ($images->isEmpty())
                <p class="field-hint">{{ __('Upload photos of the object and scans of documents (passport, cadastral extract) as images on the object page — they are added to the report.') }}</p>
            @else
                <p class="field-hint" style="margin-bottom: 12px">{{ __('Photos are placed four to a page, documents one to a page.') }}</p>
                <div class="report-images">
                    @foreach ($images as $file)
                        <div class="report-image">
                            <img src="{{ route('admin.attachments.show', $file) }}" alt="{{ $file->name }}" loading="lazy">
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
            <div class="check-grid" style="margin-top: 16px">
                @foreach (['company_documents' => __('Company documents (:count)', ['count' => count($companyDocuments)]), 'include_macro' => __('Macroeconomic section'), 'include_methodology' => __('Description of the approaches and methods')] as $flag => $label)
                    <label class="check">
                        <input type="checkbox" name="{{ $flag }}" value="1" @checked(old($flag, $values[$flag]))>
                        <span class="check-box"><x-icon name="check" :size="14" /></span>
                        <span>{{ $label }}</span>
                    </label>
                @endforeach
            </div>
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
</x-layouts.admin>
