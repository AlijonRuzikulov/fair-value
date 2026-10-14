{{-- Signatures of the director, the appraiser and (on the inspection act) the owner; place for the stamp. --}}
<div class="signs">
    @if ($director ?? false)
        <div class="sign">
            <span class="role">{{ $t('Direktor') }}</span>
            <span class="line"></span>
            <small>{{ $s->v($company['director']) }}</small>
        </div>
    @endif
    @if ($appraiserSign ?? false)
        <div class="sign">
            <span class="role">{{ $t('Baholovchi') }}</span>
            <span class="line"></span>
            <small>{{ $s->v($appraiser?->name) }}@if ($appraiser?->certificate_number) · {{ $t('malaka sertifikati') }} № {{ $appraiser->certificate_number }}@endif</small>
        </div>
    @endif
    @if ($ownerSign ?? null)
        <div class="sign">
            <span class="role">{{ $t('Mulk egasi') }}</span>
            <span class="line"></span>
            <small>{{ $ownerSign->title }}</small>
        </div>
    @endif
</div>
<p class="stamp">{{ $t("M.O'. (muhr o'rni)") }}</p>
