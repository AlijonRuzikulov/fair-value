{{--
    Adding and valuing movable property, a vehicle or a damaged car in the classic look, laid out as on the
    valuation portal: the two columns of the object, the boxes of its documents, "Holat" where the portal has it,
    and the buttons in the middle. The same page adds a new object; once saved it also holds the data the portal
    asks for in its other windows.
--}}
@php
    use App\Enums\ValuationStatus;
    use Illuminate\Support\Facades\Gate;

    $slug = $module->slugName();
    $exists = $item->exists;
    $canEdit = ! $exists || Gate::allows('edit-valuation', $item);
    $back = route($module->routeName('index'), $module->routeParams($item));
    $status = $item->status ?? ValuationStatus::New;
    $user = auth()->user();
    // Movable property has "Holat" on its page; vehicles and damage finish and reject the valuation with buttons.
    $withHolat = $slug === 'assets';
    $finish = $exists && ! $withHolat && $user->can('finalize-valuations') && in_array($status, [ValuationStatus::New, ValuationStatus::InProgress], true);
    $reasons = $exists && ! $withHolat && $user->can('finalize-valuations') ? array_filter([
        'cancelled' => ! in_array($status, [ValuationStatus::Completed, ValuationStatus::Cancelled], true) ? [__('Reject'), 'reply', 'btn-fill-danger'] : null,
        'reopened' => $status === ValuationStatus::Completed && $user->can('approve-valuations') ? [__('Revaluate'), 'refresh-cw', 'btn-warning'] : null,
    ]) : [];
    $deletable = $exists && $user->can('delete-records') ? $item->attachments : collect();
@endphp
<x-layouts.admin :title="$module->portalTitle($item)" :nav="$nav" :icon="$module->icon()">
    <div class="portal">
        @if ($errors->any())
            <div class="toast toast-error" role="alert"><x-icon name="triangle-alert" :size="18" /> {{ __('Please correct the highlighted fields.') }}</div>
        @endif

        <section class="card portal-card">
            <form method="post" id="object-form" class="portal-form portal-card-body" enctype="multipart/form-data" data-object-form
                  action="{{ $exists ? route($module->routeName('update'), $item->id) : route($module->routeName('store')) }}">
                @csrf
                @if ($exists) @method('put') @endif

                <fieldset class="portal-fields" @disabled(! $canEdit)>
                    @include('admin.'.$slug.'._portal-top')
                </fieldset>

                {{-- What a module asks for before its documents (the car and the damage). --}}
                @includeIf('admin.'.$slug.'._portal-middle')

                <fieldset class="portal-fields" @disabled(! $canEdit)>
                    @include('admin.objects._portal-docs')
                </fieldset>

                @includeIf('admin.'.$slug.'._portal-more')
            </form>

            @foreach ($deletable as $file)
                <form method="post" action="{{ route('admin.attachments.destroy', $file) }}" id="delete-file-{{ $file->id }}" data-confirm="{{ __('Delete this file?') }}" hidden>
                    @csrf @method('delete')
                </form>
            @endforeach

            <div class="portal-card-body portal-card-end">
                @if ($exists)
                    {{-- The papers of the valuation, as on the real estate page. --}}
                    <div class="portal-files pdf-buttons">
                        <a class="btn" href="{{ route($module->routeName('contract.pdf'), [$item->id, 'qr' => 1]) }}" data-pdf><x-icon name="qr-code" :size="14" /> {{ __('Contract PDF with QR codes') }}</a>
                        <a class="btn" href="{{ route($module->routeName('contract.pdf'), $item->id) }}" data-pdf><x-icon name="file-pen-line" :size="14" /> {{ __('Contract PDF') }}</a>
                        <a class="btn" href="{{ route($module->routeName('contract'), $item->id) }}" target="_blank"><x-icon name="file-text" :size="14" /> {{ __('Contract') }}</a>
                        <a class="btn" href="{{ route($module->routeName('print'), $item->id) }}" target="_blank"><x-icon name="printer" :size="14" /> {{ __('Certificate') }}</a>
                    </div>
                @endif

                @if ($withHolat)
                    @include('admin.objects._portal-holat')
                @endif

                <div class="portal-actions portal-bottom">
                    <a class="btn btn-fill-danger" href="{{ $back }}">{{ __('Cancel') }}</a>
                    @if ($canEdit)
                        <button class="btn btn-success" type="submit" form="object-form">{{ __('Save') }}</button>
                    @endif
                    @if ($finish)
                        <form method="post" action="{{ route($module->routeName('workflow'), $item->id) }}">
                            @csrf <input type="hidden" name="action" value="submitted">
                            <button class="btn btn-info" type="submit">{{ __('Finish the valuation') }}</button>
                        </form>
                    @endif
                    @foreach ($reasons as $action => [$label, $icon, $class])
                        <details class="portal-reason">
                            <summary class="btn {{ $class }}"><x-icon :name="$icon" :size="14" /> {{ $label }}</summary>
                            <form method="post" action="{{ route($module->routeName('workflow'), $item->id) }}" class="approval-reason">
                                @csrf <input type="hidden" name="action" value="{{ $action }}">
                                <input class="input input-sm" name="comment" required maxlength="2000" placeholder="{{ __('Reason') }}" aria-label="{{ $label }}: {{ __('Reason') }}">
                                <button class="btn btn-sm {{ $class }}" type="submit">{{ __('Confirm') }}</button>
                            </form>
                        </details>
                    @endforeach
                    @if ($exists)
                        @can('delete-records')
                            <form method="post" action="{{ route($module->routeName('destroy'), $item->id) }}" class="portal-delete" data-confirm="{{ __('Delete this record? It can be restored by an administrator.') }}">
                                @csrf @method('delete')
                                <button class="btn btn-danger btn-icon" type="submit" title="{{ __('Delete') }}" aria-label="{{ __('Delete') }}"><x-icon name="trash-2" :size="16" /></button>
                            </form>
                        @endcan
                    @endif
                </div>
            </div>
        </section>
    </div>
</x-layouts.admin>
