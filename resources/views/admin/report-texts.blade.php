<x-layouts.admin :title="__('Report texts')" :subtitle="__('Standard sections of every valuation report')" nav="settings" icon="scroll-text" :back="route('admin.settings.edit')">
    <form method="post" class="form-layout" action="{{ route('admin.report-texts.update') }}">
        @csrf @method('put')

        <div class="card report-help">
            <x-icon name="info" :size="18" />
            <div>
                <p>{{ __('These sections are the same in every report. Write them in Uzbek (Latin) — the Cyrillic report is made automatically.') }}</p>
                <p class="muted">{{ __('A blank line starts a new paragraph; "- " a list item; "## " a heading; "> " a centred formula; lines with " | " make a table (the first line is the header); **bold**.') }}</p>
                <p class="muted">{{ __('A line "[diagramma: row; row]" draws a chart of these table rows over the columns; "[diagramma: column]" draws the rows of that column as bars; "= Title" after the names sets the title.') }}</p>
            </div>
        </div>

        @foreach ($texts as $key => $text)
            <x-card :title="$text['title']" icon="file-text">
                <x-slot:actions>
                    @if ($text['custom'])
                        <label class="check check-sm">
                            <input type="checkbox" name="reset[]" value="{{ $key }}">
                            <span class="check-box"><x-icon name="check" :size="12" /></span>
                            <span>{{ __('Restore the default text') }}</span>
                        </label>
                    @else
                        <span class="badge">{{ __('Default text') }}</span>
                    @endif
                </x-slot:actions>
                @if ($key === 'contract')
                    <p class="field-hint" style="margin-bottom: 8px">{{ __('Printed in the service contract after the parties. These words in braces are replaced with the data of the valuation:') }} @foreach (\App\Reports\ReportText::CONTRACT_PLACEHOLDERS as $placeholder)<code>{{ '{'.$placeholder.'}' }}</code>@if (! $loop->last), @endif @endforeach</p>
                @endif
                <textarea class="input report-text" name="{{ $key }}" rows="{{ min(24, max(6, substr_count($text['value'], "\n") + 2)) }}" spellcheck="false">{{ old($key, $text['value']) }}</textarea>
                @error($key)<p class="field-error">{{ $message }}</p>@enderror
            </x-card>
        @endforeach

        <div class="card form-actions">
            <a class="btn btn-ghost" href="{{ route('admin.settings.edit') }}">{{ __('Cancel') }}</a>
            <button class="btn btn-primary" type="submit"><x-icon name="check" :size="16" /> {{ __('Save') }}</button>
        </div>
    </form>
</x-layouts.admin>
