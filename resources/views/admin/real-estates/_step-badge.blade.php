@if ($ok)
    <span class="badge badge-success"><x-icon name="check" :size="12" /> {{ __('Done') }}</span>
@else
    <span class="badge badge-warning">{{ __('To fill in') }}</span>
@endif
