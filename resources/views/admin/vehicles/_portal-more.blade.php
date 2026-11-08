{{-- What the portal asks for in its other windows, once the vehicle is saved: its data and its value. --}}
@if ($item->exists)
    <details class="portal-fold" id="object-data" data-remember @if (blank($item->brand) || session()->hasOldInput()) open @endif>
        <summary>{{ __('Data of the vehicle') }}</summary>
        <fieldset class="portal-fields" @disabled(! $canEdit)>
            <div class="form-grid">
                {{-- The fields are read from the photo of the registration certificate (the form is outside this one). --}}
                @php $readsCertificates = app(\App\Services\Documents\DocumentAi::class)->available(); @endphp
                <div class="span-12 ai-fill">
                    <label @class(['btn', 'btn-info', 'is-disabled' => ! $readsCertificates])>
                        <x-icon name="scan-search" :size="16" /> <span data-busy-target>{{ __('Fill in from the registration certificate') }}</span>
                        @if ($readsCertificates)
                            <input type="file" name="files[]" form="tech-passport-form" accept="image/*,application/pdf" capture="environment" multiple class="sr-only"
                                   data-submit-on-change data-busy-text="{{ __('Reading…') }}" aria-label="{{ __('Photo of the registration certificate') }}">
                        @endif
                    </label>
                    @unless ($readsCertificates)
                        <span class="ai-fill-note">{{ __('Reading documents needs an AI key (ANTHROPIC_API_KEY in .env).') }}</span>
                    @endunless
                </div>
                @include('admin.vehicles._fields')
            </div>
        </fieldset>
    </details>
    @include('admin.objects._portal-valuation')
@endif
