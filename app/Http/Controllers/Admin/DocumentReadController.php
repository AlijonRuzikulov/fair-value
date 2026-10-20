<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ClientType;
use App\Enums\PersonRole;
use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Client;
use App\Models\RealEstate;
use App\Services\Documents\DocumentAi;
use App\Services\Documents\DocumentData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Throwable;

/**
 * Filling the object and its owner from scans of documents: the AI reads
 * the passport and the cadastral extract, the appraiser checks every value
 * on the review page and chooses what is saved.
 */
class DocumentReadController extends Controller
{
    /** Object fields that can be taken from the documents. */
    public const OBJECT_FIELDS = ['cadastral_number', 'total_area', 'living_area', 'useful_area', 'land_area', 'built_up_area', 'floors_total', 'floor', 'rooms', 'year_built', 'address'];

    public function __construct(private DocumentAi $ai) {}

    public function read(Request $request, string $id): RedirectResponse
    {
        $item = $this->object($id);
        $data = $request->validate(['files' => ['required', 'array', 'min:1', 'max:6'], 'files.*' => ['integer']], [], ['files' => __('Documents')]);

        if (! $this->ai->available()) {
            return back()->with('error', __('Reading documents needs an AI key (ANTHROPIC_API_KEY in .env).'));
        }

        $attachments = $item->attachments()->whereIn('id', $data['files'])
            ->where(fn ($q) => $q->where('mime', 'like', 'image/%')->orWhere('mime', 'application/pdf'))
            ->orderBy('id')->get();
        if ($attachments->isEmpty()) {
            return back()->with('error', __('Choose scans of documents (images or PDF).'));
        }

        set_time_limit(180);
        try {
            $result = $this->ai->read(DocumentData::files($attachments));
        } catch (Throwable $e) {
            report($e);

            return back()->with('error', __('The documents could not be read: :reason', ['reason' => mb_substr($e->getMessage(), 0, 200)]));
        }

        Activity::record('documents_read', $item, ['files' => $attachments->pluck('name')->all()]);
        $request->session()->put($this->key($item), ['files' => $attachments->pluck('id')->all(), 'result' => $result]);

        return redirect()->route('admin.real-estates.documents.review', $item->id);
    }

    public function review(Request $request, string $id): View|RedirectResponse
    {
        $item = $this->object($id);
        $session = $request->session()->get($this->key($item));
        if (! $session) {
            return redirect()->route('admin.real-estates.report.edit', $item->id);
        }

        $result = $session['result'];
        $person = $result['person'];
        $owner = $this->owner($item, $person);
        $place = DocumentData::place($result['property']['region'], $result['property']['district']);
        $files = $item->attachments()->whereIn('id', $session['files'])->orderBy('id')->get()->values();

        return view('admin.real-estates.documents-review', [
            'item' => $item,
            'result' => $result,
            'owner' => $owner['client'],
            'ownerMode' => $owner['mode'],
            'place' => $place,
            'files' => $files,
        ]);
    }

    public function apply(Request $request, string $id): RedirectResponse
    {
        $item = $this->object($id);
        $session = $request->session()->get($this->key($item));
        if (! $session) {
            return redirect()->route('admin.real-estates.report.edit', $item->id);
        }
        $result = $session['result'];
        $chosen = fn (string $group) => array_keys(array_filter((array) $request->input($group, [])));

        DB::transaction(function () use ($item, $result, $chosen, $session) {
            // The owner: the linked one, a client with the same PINFL, or a new client.
            $personFields = array_intersect($chosen('person'), DocumentData::PERSON);
            $values = array_filter(array_intersect_key($result['person'], array_flip($personFields)), fn ($v) => $v !== null);
            if ($values !== []) {
                $owner = $this->owner($item, $result['person']);
                $client = $owner['client'] ?? new Client(['type' => ClientType::Individual]);
                $client->fill($values)->save();
                if ($owner['mode'] !== 'linked') {
                    $item->clients()->attach($client->id, ['role' => PersonRole::Owner->value]);
                }
            }

            $property = $result['property'];
            $updates = array_filter(
                array_intersect_key($property, array_flip(array_intersect($chosen('property'), self::OBJECT_FIELDS))),
                fn ($v) => $v !== null
            );
            if (in_array('place', $chosen('property'), true)) {
                $place = DocumentData::place($property['region'], $property['district']);
                $updates['region_id'] = $place['region']?->id ?? $item->region_id;
                $updates['district_id'] = $place['district']?->id ?? $item->district_id;
            }

            $report = (array) $item->report;
            if (in_array('rights', $chosen('report'), true) && $property['rights_type']) {
                $report['rights'] = $property['rights_type'].($property['share'] ? ' ('.$property['share'].')' : '');
            }
            foreach (['rights_basis', 'encumbrances'] as $key) {
                if (in_array($key, $chosen('report'), true) && $property[$key]) {
                    $report[$key] = $property[$key];
                }
            }
            // Scans of documents go to the appendix one to a page, photos four to a page.
            $roles = (array) ($report['attachments'] ?? []);
            foreach ($result['documents'] as $document) {
                $fileId = $session['files'][$document['file']] ?? null;
                if ($fileId) {
                    $roles[$fileId] = $document['kind'] === 'photo' ? 'photo' : 'document';
                }
            }
            $report['attachments'] = $roles;

            $item->fill([...$updates, 'report' => $report])->save();
        });

        $request->session()->forget($this->key($item));

        return redirect()->route('admin.real-estates.report.edit', $item->id)->with('status', __('The data from the documents has been saved.'));
    }

    /**
     * Whose data the documents hold: the linked owner, an existing client
     * with the same PINFL or passport, or a new client.
     *
     * @return array{client: ?Client, mode: 'linked'|'existing'|'new'}
     */
    private function owner(RealEstate $item, array $person): array
    {
        $item->loadMissing('clients');
        $linked = $item->clients->firstWhere('pivot.role', PersonRole::Owner->value);
        if ($linked) {
            return ['client' => $linked, 'mode' => 'linked'];
        }

        $existing = null;
        if ($person['pinfl']) {
            $existing = Client::where('pinfl', $person['pinfl'])->first();
        }
        if (! $existing && $person['passport_series'] && $person['passport_number']) {
            $existing = Client::where('passport_series', $person['passport_series'])->where('passport_number', $person['passport_number'])->first();
        }

        return $existing ? ['client' => $existing, 'mode' => 'existing'] : ['client' => null, 'mode' => 'new'];
    }

    private function object(string $id): RealEstate
    {
        $item = RealEstate::with('clients')->findOrFail($id);
        Gate::authorize('edit-valuation', $item);

        return $item;
    }

    private function key(RealEstate $item): string
    {
        return 'documents.real_estate.'.$item->id;
    }
}
