{{-- "Baholovchi" and "Direktor" of the portal's list of vehicles: confirmed, or the button that confirms. --}}
@php
    use App\Enums\ValuationStatus;
    $user = auth()->user();
    $byAppraiser = $item->currentStep('submitted');
    $byDirector = $item->currentStep('approved') ?? ($item->status === ValuationStatus::Completed ? true : null);
    $route = route($module->routeName('workflow'), $item->id);
@endphp
<td class="row-sign">
    @if ($byAppraiser || $byDirector)
        <b>{{ __('Confirmed by the appraiser') }}</b>
    @elseif ($user->can('finalize-valuations') && in_array($item->status, [ValuationStatus::New, ValuationStatus::InProgress], true))
        <form method="post" action="{{ $route }}">
            @csrf <input type="hidden" name="action" value="submitted">
            <button class="btn btn-sm btn-success" type="submit">{{ __('Confirm') }}</button>
        </form>
    @endif
</td>
<td class="row-sign">
    @if ($byDirector)
        <b>{{ __('Confirmed by the director') }}</b>
    @elseif ($item->status === ValuationStatus::Review && $user->can('approve-valuations'))
        <form method="post" action="{{ $route }}">
            @csrf <input type="hidden" name="action" value="approved">
            <button class="btn btn-sm btn-success" type="submit">{{ __('Confirm') }}</button>
        </form>
    @endif
</td>
