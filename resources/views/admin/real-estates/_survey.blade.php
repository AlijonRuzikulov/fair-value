{{-- "So'rovnoma ma'lumotlari" of the valuation portal: what the customer states about the object,
     question by question. Shown in a window like the act, or as a page. --}}
@php
    use App\Reports\Survey;
    $inWindow = request()->ajax();
    $none = Survey::usuallyNone($item);
@endphp
<form method="post" action="{{ route('admin.real-estates.survey.update', $item->id) }}" class="act-window survey-window" data-act-window>
    @csrf @method('put')
    <input type="hidden" name="_back" value="{{ old('_back', $inWindow ? url()->previous() : route('admin.real-estates.show', $item->id)) }}">

    <header class="act-head">
        <strong>{{ __('Questionnaire data') }}</strong>
        @if ($inWindow)
            <button type="button" class="btn btn-danger btn-sm" data-act-close>{{ __('Close') }}</button>
        @else
            <a class="btn btn-danger btn-sm" href="{{ old('_back', route('admin.real-estates.show', $item->id)) }}">{{ __('Close') }}</a>
        @endif
    </header>

    {{-- The questions scroll between the fixed head and foot of the window. --}}
    <div class="act-body survey-body">
    <fieldset class="survey-fields" @disabled(! $canEdit)>
        <div class="qa-list">
            @foreach (Survey::keys($item) as $key)
                <label class="qa-row" for="sv-{{ $key }}">
                    <span class="qa-question">{{ __(Survey::QUESTIONS[$key][1]) }}</span>
                    <input class="input" id="sv-{{ $key }}" name="survey[{{ $key }}]" value="{{ old("survey.$key", $answers[$key] ?? null) }}" maxlength="1000" autocomplete="off" @isset(Survey::OPTIONS[$key]) list="sv-list-{{ $key }}" @endisset>
                    @error("survey.$key")<span class="field-error">{{ $message }}</span>@enderror
                </label>
            @endforeach
        </div>
        @foreach (Survey::OPTIONS as $key => $options)
            <datalist id="sv-list-{{ $key }}">@foreach ($options as $option)<option value="{{ $option }}">@endforeach</datalist>
        @endforeach
    </fieldset>
    </div>

    <footer class="act-foot">
        @if ($canEdit && $none !== [])
            <button type="button" class="btn btn-sm survey-none" data-fill-empty='@json(array_map(fn ($key) => "survey[$key]", $none))' data-fill-value="Mavjud emas">
                <x-icon name="check" :size="14" /> {{ __('“None” where pledge, disputes, alterations and debts are empty') }}
            </button>
        @endif
        <a class="btn" href="{{ route('admin.real-estates.contract', [$item->id, 'doc' => 'questionnaire']) }}" target="_blank"><x-icon name="printer" :size="16" /> {{ __('Print') }}</a>
        @if ($canEdit)
            <button class="btn btn-primary" type="submit"><x-icon name="check" :size="16" /> {{ __('Save') }}</button>
        @endif
    </footer>
</form>
