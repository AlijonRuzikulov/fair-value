@php use App\Models\ValuationStep; use App\Support\Format; @endphp
@if ($item->steps->isEmpty())
    <p class="muted">{{ __('No approval steps yet.') }}</p>
@else
    <table class="table table-compact">
        <tbody>
            @foreach ($item->steps->reverse() as $step)
                <tr>
                    <td class="nowrap">{{ Format::datetime($step->created_at) }}</td>
                    <td>{{ $step->user?->name ?? '—' }}</td>
                    <td><strong>{{ ValuationStep::label($step->action) }}</strong>@if ($step->comment)<span class="cell-sub">{{ $step->comment }}</span>@endif</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif
