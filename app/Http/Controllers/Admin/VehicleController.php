<?php

namespace App\Http\Controllers\Admin;

use App\Enums\DocumentKind;
use App\Enums\FuelType;
use App\Enums\ValuationPurpose;
use App\Models\Activity;
use App\Models\Attachment;
use App\Models\Vehicle;
use App\Reports\RealEstateReport;
use App\Services\Documents\DocumentAi;
use App\Services\Documents\DocumentData;
use App\Support\AdminTheme;
use App\Support\Format;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\Rule;
use Throwable;

/**
 * "Автотранспорт"
 */
class VehicleController extends ValuationController
{
    protected function model(): string
    {
        return Vehicle::class;
    }

    protected function slug(): string
    {
        return 'vehicles';
    }

    public function title(): string
    {
        return AdminTheme::classic() ? __('List of motor vehicles') : __('List of vehicles');
    }

    /** As on the valuation portal: "Avtotransport qo'shish", its own page afterwards. */
    public function usesPortal(): bool
    {
        return AdminTheme::classic();
    }

    public function portalTitle(Model $item): string
    {
        return $item->exists ? __('Vehicle').' № '.$item->id : __('Add a vehicle');
    }

    protected function portalRequired(): array
    {
        return ['property_type', 'ownership_form', 'purpose', 'appraiser_id'];
    }

    public function portalFilters(): array
    {
        return ['status', 'from', 'to'];
    }

    public function portalTotal(): bool
    {
        return false;
    }

    public function portalDocumentsTitle(): string
    {
        return __('Documents submitted for the valuation of the vehicle');
    }

    /** Fields of the vehicle form filled in from its registration certificate. */
    private const CERTIFICATE_FIELDS = [
        'brand', 'model', 'year_made', 'color', 'plate_number', 'vin', 'engine_number', 'chassis_number',
        'tech_passport', 'body_type', 'engine_volume', 'fuel_type',
    ];

    /**
     * The registration certificate (texpasport) is read from its
     * photos and the vehicle's page opens with its fields filled in; nothing
     * changes until the appraiser checks them and saves. The photos are kept
     * with the vehicle's documents.
     */
    public function techPassport(Request $request, string $id, DocumentAi $ai): RedirectResponse
    {
        $item = $this->find($id);
        Gate::authorize('edit-valuation', $item);
        $request->validate([
            'files' => ['required', 'array', 'min:1', 'max:2'],
            'files.*' => ['file', 'max:10240', 'mimes:jpg,jpeg,png,webp,pdf'],
        ], [], ['files' => __('Photo of the registration certificate')]);
        $back = route($this->routeName('show'), $item->id).'#object-data';

        if (! $ai->available()) {
            return redirect()->to($back)->with('error', __('Reading documents needs an AI key (ANTHROPIC_API_KEY in .env).'));
        }

        set_time_limit(120);
        try {
            $read = $ai->read(DocumentData::uploads($request->file('files')))['vehicle'] ?? [];
        } catch (Throwable $e) {
            report($e);

            return redirect()->to($back)->with('error', __('The documents could not be read: :reason', ['reason' => mb_substr($e->getMessage(), 0, 200)]));
        }

        foreach ($request->file('files') as $file) {
            Attachment::keep($item, $file, DocumentKind::TechnicalPassport->value, $request->user()->id);
        }
        $fields = array_filter(array_intersect_key((array) $read, array_flip(self::CERTIFICATE_FIELDS)), fn ($value) => $value !== null && $value !== '');
        Activity::record('documents_read', $item, ['files' => array_map(fn ($file) => $file->getClientOriginalName(), $request->file('files')), 'fields' => array_keys($fields)]);

        if ($fields === []) {
            return redirect()->to($back)->with('error', __('Nothing was read from the photo. Take a clearer photo of the registration certificate.'));
        }

        return redirect()->to($back)->withInput($fields)->with('status', __('The registration certificate has been read: check the data and save.'));
    }

    protected function newItem(Request $request): Model
    {
        $item = parent::newItem($request);
        $item->property_type = RealEstateReport::OPTIONS['property_type'][0];

        return $item;
    }

    public function singular(): string
    {
        return __('Vehicle');
    }

    public function icon(): string
    {
        return 'car';
    }

    protected function numericFields(): array
    {
        return ['mileage', 'engine_volume'];
    }

    protected function rules(Request $request, ?Model $item): array
    {
        // On the portal a vehicle is added before its make and model are typed.
        $name = $request->exists('owner_id') ? 'nullable' : 'required';

        return [
            'brand' => [$name, 'string', 'max:255'],
            'model' => [$name, 'string', 'max:255'],
            'property_type' => ['nullable', 'string', 'max:150'],
            'ownership_form' => ['nullable', 'string', 'max:150'],
            'identification' => ['nullable', 'string', 'max:5000'],
            'restrictions' => ['nullable', 'string', 'max:5000'],
            'year_made' => ['nullable', 'integer', 'min:1950', 'max:'.(now()->year + 1)],
            'body_type' => ['nullable', 'string', 'max:40'],
            'color' => ['nullable', 'string', 'max:40'],
            'vin' => ['nullable', 'string', 'max:40'],
            'engine_number' => ['nullable', 'string', 'max:40'],
            'chassis_number' => ['nullable', 'string', 'max:40'],
            'plate_number' => ['nullable', 'string', 'max:20'],
            'tech_passport' => ['nullable', 'string', 'max:30'],
            'mileage' => ['nullable', 'integer', 'min:0', 'max:9999999'],
            'engine_volume' => ['nullable', 'numeric', 'min:0', 'max:99'],
            'fuel_type' => ['nullable', Rule::enum(FuelType::class)],
            'transmission' => ['nullable', Rule::in(array_keys(self::transmissions()))],
            'condition' => ['nullable', 'string', 'max:255'],
        ];
    }

    protected function formData(Model $item): array
    {
        return [
            'fuelTypes' => FuelType::options(),
            'transmissions' => self::transmissions(),
            'propertyTypes' => RealEstateReport::choices('property_type', $item->property_type),
            'ownershipForms' => RealEstateReport::choices('ownership_form', $item->ownership_form),
            'portalPurposes' => ValuationPurpose::portalOptions($item->purpose),
        ];
    }

    public static function transmissions(): array
    {
        return ['manual' => __('Manual'), 'automatic' => __('Automatic'), 'robot' => __('Robotic'), 'cvt' => __('CVT')];
    }

    public function columns(): array
    {
        return [
            __('State number') => fn (Vehicle $i) => $i->plate_number
                ? new HtmlString('<span class="code">'.e($i->plate_number).'</span>')
                : '—',
            __('Mileage') => fn (Vehicle $i) => $i->mileage !== null ? Format::number($i->mileage).' '.__('km') : '—',
        ];
    }

    public function facts(Model $item): array
    {
        /** @var Vehicle $item */
        return [
            __('Brand') => $item->brand,
            __('Model') => $item->model,
            __('Year of manufacture') => $item->year_made,
            __('Body type') => $item->body_type,
            __('Color') => $item->color,
            __('State number') => $item->plate_number,
            __('Body number (VIN)') => $item->vin,
            __('Engine number') => $item->engine_number,
            __('Chassis number') => $item->chassis_number,
            __('Registration certificate') => $item->tech_passport,
            __('Mileage') => $item->mileage !== null ? Format::number($item->mileage).' '.__('km') : null,
            __('Engine capacity') => $item->engine_volume ? Format::number($item->engine_volume, 1).' '.__('l') : null,
            __('Fuel') => $item->fuelLabel(),
            __('Transmission') => $item->transmission ? (self::transmissions()[$item->transmission] ?? $item->transmission) : null,
            __('Technical condition') => $item->condition,
        ];
    }

    protected function attributeNames(): array
    {
        return [...parent::attributeNames(), 'property_type' => __('Type of the property'), 'ownership_form' => __('Form of ownership')];
    }

    protected function exportColumns(): array
    {
        return [
            __('Brand') => fn ($i) => $i->brand,
            __('Model') => fn ($i) => $i->model,
            __('Year of manufacture') => fn ($i) => $i->year_made,
            __('State number') => fn ($i) => $i->plate_number,
            __('Body number (VIN)') => fn ($i) => $i->vin,
            __('Engine number') => fn ($i) => $i->engine_number,
            __('Mileage') => fn ($i) => $i->mileage,
        ];
    }
}
