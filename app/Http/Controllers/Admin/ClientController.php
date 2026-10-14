<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ClientType;
use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Client;
use App\Models\Region;
use App\Support\CsvExport;
use App\Support\Format;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * "Мижозлар" — individuals (жисмоний шахс) and legal entities (юридик шахс).
 */
class ClientController extends Controller
{
    public function index(Request $request): View
    {
        $type = $this->type($request);

        $clients = $this->query($request, $type)
            ->with(['region', 'district'])
            ->withCount(['realEstates', 'assets', 'vehicles', 'otherAssets', 'damageCases'])
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.clients.index', [
            'clients' => $clients,
            'type' => $type,
            'regions' => Region::ordered()->get()->pluck('name', 'id'),
        ]);
    }

    public function search(Request $request): JsonResponse
    {
        $term = trim((string) $request->query('q'));

        $clients = Client::query()
            ->when($term !== '', fn (Builder $q) => $q->search($term))
            ->latest('id')
            ->limit(15)
            ->get();

        return response()->json($clients->map(fn (Client $c) => [
            'id' => $c->id,
            'title' => $c->title,
            'sub' => collect([$c->type->label(), $c->document, $c->phone ? Format::phone($c->phone) : null])->filter()->implode(' · '),
        ]));
    }

    public function create(Request $request): View
    {
        return $this->form(new Client(['type' => $this->type($request)]));
    }

    public function store(Request $request): RedirectResponse
    {
        $client = Client::create($this->validated($request));

        return redirect()->route('admin.clients.show', $client)->with('status', __('Client added.'));
    }

    public function show(Client $client): View
    {
        $client->load([
            'region', 'district', 'attachments.uploader',
            'realEstates', 'assets', 'vehicles', 'otherAssets', 'damageCases',
            'activities' => fn ($q) => $q->with('user')->where('event', '!=', 'viewed')->limit(15),
        ]);
        Activity::recordView($client);

        $objects = collect()
            ->concat($client->realEstates)->concat($client->assets)->concat($client->vehicles)
            ->concat($client->otherAssets)->concat($client->damageCases)
            ->sortByDesc('created_at')
            ->values();

        return view('admin.clients.show', ['client' => $client, 'objects' => $objects]);
    }

    public function edit(Client $client): View
    {
        return $this->form($client);
    }

    public function update(Request $request, Client $client): RedirectResponse
    {
        $client->update($this->validated($request, $client));

        return redirect()->route('admin.clients.show', $client)->with('status', __('Changes saved.'));
    }

    public function destroy(Client $client): RedirectResponse
    {
        Gate::authorize('delete-records');
        $type = $client->type->value;
        $client->delete();

        return redirect()->route('admin.clients.index', ['type' => $type])->with('status', __('Deleted.'));
    }

    public function export(Request $request): StreamedResponse
    {
        $type = $this->type($request);
        $query = $this->query($request, $type)->with(['region', 'district'])->latest('id');

        $columns = $type === ClientType::Legal
            ? [
                '№' => fn ($c) => $c->id,
                __('Company name') => fn ($c) => $c->company_name,
                __('TIN') => fn ($c) => $c->inn,
                __('Director') => fn ($c) => $c->director,
                __('OKED') => fn ($c) => $c->oked,
                __('Bank') => fn ($c) => $c->bank_name,
                __('MFO') => fn ($c) => $c->bank_mfo,
                __('Bank account') => fn ($c) => $c->bank_account,
            ]
            : [
                '№' => fn ($c) => $c->id,
                __('Full name') => fn ($c) => $c->title,
                __('Date of birth') => fn ($c) => Format::date($c->birth_date),
                __('PINFL') => fn ($c) => $c->pinfl,
                __('Passport') => fn ($c) => $c->document,
            ];

        $columns += [
            __('Phone') => fn ($c) => $c->phone,
            __('E-mail') => fn ($c) => $c->email,
            __('Region') => fn ($c) => $c->region?->name,
            __('District') => fn ($c) => $c->district?->name,
            __('Address') => fn ($c) => $c->address,
        ];

        return CsvExport::download('clients-'.$type->value.'-'.now()->format('Y-m-d').'.csv', $query, $columns);
    }

    private function type(Request $request): ClientType
    {
        return ClientType::tryFrom((string) $request->input('type')) ?? ClientType::Individual;
    }

    private function query(Request $request, ClientType $type): Builder
    {
        $term = trim((string) $request->query('q'));

        return Client::query()
            ->where('type', $type->value)
            ->when($term !== '', fn (Builder $q) => $q->search($term))
            ->when($request->query('region_id'), fn (Builder $q, $v) => $q->where('region_id', $v));
    }

    private function form(Client $client): View
    {
        return view('admin.clients.form', [
            'client' => $client,
            'regions' => Region::ordered()->get(),
            'districts' => ValuationController::districtOptions(old('region_id', $client->region_id)),
        ]);
    }

    private function validated(Request $request, ?Client $client = null): array
    {
        $type = $client?->type ?? ClientType::tryFrom((string) $request->input('type'));
        $individual = $type === ClientType::Individual;

        $request->merge([
            'passport_series' => $request->filled('passport_series') ? mb_strtoupper(trim($request->input('passport_series'))) : null,
            'pinfl' => $request->filled('pinfl') ? preg_replace('/\D/', '', $request->input('pinfl')) : null,
            'inn' => $request->filled('inn') ? preg_replace('/\D/', '', $request->input('inn')) : null,
            'bank_account' => $request->filled('bank_account') ? preg_replace('/\D/', '', $request->input('bank_account')) : null,
        ]);

        $data = $request->validate([
            'type' => [$client ? 'nullable' : 'required', Rule::enum(ClientType::class)],
            'last_name' => [Rule::requiredIf($individual), 'nullable', 'string', 'max:255'],
            'first_name' => [Rule::requiredIf($individual), 'nullable', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'pinfl' => ['nullable', 'digits:14'],
            'passport_series' => ['nullable', 'string', 'max:4'],
            'passport_number' => ['nullable', 'string', 'max:12'],
            'passport_issued_by' => ['nullable', 'string', 'max:255'],
            'passport_issued_at' => ['nullable', 'date'],
            'company_name' => [Rule::requiredIf(! $individual), 'nullable', 'string', 'max:255'],
            'inn' => ['nullable', 'digits:9'],
            'director' => ['nullable', 'string', 'max:255'],
            'oked' => ['nullable', 'string', 'max:10'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'bank_mfo' => ['nullable', 'digits:5'],
            'bank_account' => ['nullable', 'digits:20'],
            'phone' => ['nullable', 'string', 'max:30'],
            'phone2' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'region_id' => ['nullable', 'integer', 'exists:regions,id'],
            'district_id' => ['nullable', 'integer', Rule::exists('districts', 'id')->where('region_id', $request->integer('region_id'))],
            'address' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ], [], [
            'pinfl' => __('PINFL'),
            'inn' => __('TIN'),
            'bank_mfo' => __('MFO'),
            'bank_account' => __('Bank account'),
            'last_name' => __('Last name'),
            'first_name' => __('First name'),
            'company_name' => __('Company name'),
        ]);

        // The type of an existing client never changes.
        if ($client) {
            unset($data['type']);
        }

        return $data;
    }
}
