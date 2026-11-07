<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Client;
use App\Models\Organization;
use App\Support\DocumentNumber;
use App\Support\NumberInput;
use App\Support\Registry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Banks and valuation companies the system is given to. Each works in its
 * own workspace, and its contracts and reports carry the details written here.
 * Their clients and objects are not shown here: only their people are counted.
 */
class OrganizationController extends Controller
{
    public function index(): View
    {
        return view('admin.organizations.index', ['organizations' => Organization::withCount('users')->orderBy('name')->get()]);
    }

    public function create(): View
    {
        return view('admin.organizations.form', ['organization' => new Organization]);
    }

    public function store(Request $request): RedirectResponse
    {
        $organization = Organization::create($this->validated($request));
        Activity::record('created', null, ['name' => $organization->name], 'Organisation: '.$organization->name);

        return redirect()->route('admin.organizations.index')->with('status', __('Organisation added.'));
    }

    public function edit(Organization $organization): View
    {
        return view('admin.organizations.form', ['organization' => $organization]);
    }

    public function update(Request $request, Organization $organization): RedirectResponse
    {
        $organization->update($this->validated($request));
        if ($organization->wasChanged()) {
            Activity::record('updated', null, ['name' => $organization->name], 'Organisation: '.$organization->name);
        }

        return redirect()->route('admin.organizations.index')->with('status', __('Changes saved.'));
    }

    /** Only an organisation without people and without records is removed. */
    public function destroy(Organization $organization): RedirectResponse
    {
        $records = Client::withoutGlobalScopes()->where('organization_id', $organization->id)->exists()
            || collect(Registry::valuationModels())->contains(fn ($class) => $class::withoutGlobalScopes()->where('organization_id', $organization->id)->exists());
        if ($organization->users()->exists() || $records) {
            return back()->with('error', __('The organisation has users or records: deactivate its users instead.'));
        }

        $organization->delete();
        Activity::record('deleted', null, ['name' => $organization->name], 'Organisation: '.$organization->name);

        return redirect()->route('admin.organizations.index')->with('status', __('Organisation deleted.'));
    }

    /** @return array{name: string, settings: array<string, string>} */
    private function validated(Request $request): array
    {
        $request->merge(['vat_rate' => NumberInput::normalize($request->input('vat_rate'))]);
        $numberFormat = function (string $attribute, mixed $value, \Closure $fail) {
            if (! DocumentNumber::valid((string) $value)) {
                $fail(__('The format must contain {n} or {nnnn} once.'));
            }
        };

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'legal_name_ru' => ['nullable', 'string', 'max:255'],
            'director' => ['nullable', 'string', 'max:255'],
            'chief_accountant' => ['nullable', 'string', 'max:120'],
            'license' => ['nullable', 'string', 'max:255'],
            'insurance_policy' => ['nullable', 'string', 'max:500'],
            'membership' => ['nullable', 'string', 'max:1000'],
            'inn' => ['nullable', 'string', 'max:20'],
            'oked' => ['nullable', 'string', 'max:20'],
            'phone' => ['nullable', 'string', 'max:30'],
            'phone2' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'bank_account' => ['nullable', 'string', 'max:30'],
            'bank_mfo' => ['nullable', 'string', 'max:10'],
            'report_city' => ['nullable', 'string', 'max:80'],
            'vat_rate' => ['nullable', 'numeric', 'min:0', 'max:50'],
            'contract_number_format' => ['nullable', 'string', 'max:40', $numberFormat],
            'report_number_format' => ['nullable', 'string', 'max:40', $numberFormat],
            'require_approval' => ['nullable', 'in:0,1'],
        ]);

        $settings = array_filter(array_intersect_key($data, array_flip(Organization::DETAILS)), fn ($value) => filled($value));

        return ['name' => trim($data['name']), 'settings' => array_map('strval', $settings)];
    }
}
