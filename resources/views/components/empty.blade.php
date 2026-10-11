@props(['icon' => 'inbox', 'title' => null, 'text' => null])
<div class="empty">
    <span class="empty-icon"><x-icon :name="$icon" :size="26" /></span>
    <p class="empty-title">{{ $title ?? __('Nothing found') }}</p>
    @if ($text)<p class="empty-text">{{ $text }}</p>@endif
    {{ $slot }}
</div>
