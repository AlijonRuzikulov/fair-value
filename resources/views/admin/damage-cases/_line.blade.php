<tr data-calc-row>
    <td class="col-type">
        <select class="input" name="items[{{ $i }}][type]" data-line="type" aria-label="{{ __('Type') }}">
            @foreach (\App\Enums\DamageItemType::options() as $value => $label)
                <option value="{{ $value }}" @selected(($row['type'] ?? '') === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </td>
    <td><input class="input" name="items[{{ $i }}][name]" value="{{ $row['name'] ?? '' }}" data-line="name" aria-label="{{ __('Name') }}" required></td>
    <td class="col-op">
        <select class="input" name="items[{{ $i }}][operation]" data-line="operation" aria-label="{{ __('Operation') }}">
            <option value="">—</option>
            @foreach (\App\Enums\RepairOperation::options() as $value => $label)
                <option value="{{ $value }}" @selected(($row['operation'] ?? '') === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </td>
    <td class="col-qty"><input class="input" name="items[{{ $i }}][quantity]" value="{{ $row['quantity'] ?? 1 }}" inputmode="decimal" data-line="quantity" aria-label="{{ __('Qty') }}"></td>
    <td class="col-hours"><input class="input" name="items[{{ $i }}][hours]" value="{{ $row['hours'] ?? '' }}" inputmode="decimal" data-line="hours" aria-label="{{ __('Norm-h') }}"></td>
    <td class="col-price"><input class="input" name="items[{{ $i }}][unit_price]" value="{{ is_numeric($row['unit_price'] ?? null) ? \App\Support\Format::number($row['unit_price'], fmod((float) $row['unit_price'], 1.0) ? 2 : 0) : ($row['unit_price'] ?? '') }}" inputmode="decimal" data-money data-line="unit_price" aria-label="{{ __('Price / rate') }}"></td>
    <td class="col-amount" data-line="amount">0</td>
    <td><button type="button" class="btn btn-sm btn-icon btn-danger" data-calc-remove title="{{ __('Remove') }}" aria-label="{{ __('Remove') }}"><x-icon name="x" :size="16" /></button></td>
</tr>
