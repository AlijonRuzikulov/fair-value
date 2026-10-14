{{-- Rows of the document review: field, value now, value in the document, and whether to take it. --}}
<div class="table-wrap">
    <table class="table doc-rows">
        <thead>
            <tr><th>{{ __('Field') }}</th><th>{{ __('Now') }}</th><th>{{ __('In the document') }}</th><th class="doc-pick">{{ __('Take') }}</th></tr>
        </thead>
        <tbody>
            @foreach ($labels as $key => $label)
                @php
                    $now = $current($key);
                    $new = $found[$key] ?? null;
                    $newShown = is_string($new) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $new) ? date('d.m.Y', strtotime($new)) : $new;
                    $differs = filled($new) && ! (mb_strtolower(trim((string) $now)) === mb_strtolower(trim((string) $newShown)) || (is_numeric($now) && is_numeric($new) && abs((float) $now - (float) $new) < 0.005));
                @endphp
                <tr @class(['is-same' => filled($new) && ! $differs, 'is-missing' => blank($new)])>
                    <td class="doc-label">{{ $label }}</td>
                    <td class="faint">{{ filled($now) ? $now : '—' }}</td>
                    <td>@if (filled($new))<strong>{{ $newShown }}</strong>@else<span class="faint">{{ __('not found') }}</span>@endif</td>
                    <td class="doc-pick">
                        @if ($differs)
                            <label class="check check-sm"><input type="checkbox" name="{{ $group }}[{{ $key }}]" value="1" checked aria-label="{{ $label }}"><span class="check-box"><x-icon name="check" :size="12" /></span></label>
                        @elseif (filled($new))
                            <x-icon name="circle-check" :size="16" class="sys-ok-icon" />
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
