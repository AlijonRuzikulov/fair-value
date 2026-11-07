<?php

namespace App\Services;

use App\Models\Client;
use App\Models\DamageCase;
use App\Models\Organization;
use App\Models\RealEstate;
use App\Models\Vehicle;
use App\Support\FieldNames;
use App\Support\Registry;
use App\Support\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Where a person or a thing was valued before in the other workspaces, found
 * by a passport, a PINFL, a TIN, a cadastral number, a VIN or a car number:
 * only who (the organisation) and when, never the record itself.
 */
final class ValuationHistory
{
    public const FIELDS = ['passport', 'pinfl', 'inn', 'cadastral', 'vin', 'plate'];

    private const LIMIT = 10;

    /**
     * @return list<array{organization: string, date: string, kind: string}>
     */
    public function find(string $field, string $value): array
    {
        $key = $this->normalize($field, $value);
        if ($key === null) {
            return [];
        }

        $found = match ($field) {
            'passport' => $this->ofClients(fn (Builder $q) => $q->where('passport_series', substr($key, 0, 2))->where('passport_number', substr($key, 2))),
            'pinfl' => $this->ofClients(fn (Builder $q) => $q->where('pinfl', $key)),
            'inn' => $this->ofClients(fn (Builder $q) => $q->where('inn', $key)),
            'cadastral' => $this->ofObjects(RealEstate::class, fn (Builder $q) => $q->whereRaw($this->stripped('cadastral_number', [':', ' ', '-', '/', '.']).' = ?', [$key])),
            'vin' => $this->ofObjects(Vehicle::class, fn (Builder $q) => $q->whereRaw($this->stripped('upper(vin)', [' ', '-']).' = ?', [$key]))
                ->merge($this->ofObjects(DamageCase::class, fn (Builder $q) => $q->whereRaw($this->stripped('upper(vin)', [' ', '-']).' = ?', [$key]))),
            'plate' => $this->ofObjects(Vehicle::class, fn (Builder $q) => $q->whereRaw($this->stripped('upper(plate_number)', [' ', '-']).' = ?', [$key]))
                ->merge($this->ofObjects(DamageCase::class, fn (Builder $q) => $q->whereRaw($this->stripped('upper(plate_number)', [' ', '-']).' = ?', [$key]))),
        };

        $found = $found->sortByDesc('date')->unique(fn ($row) => $row['organization_id'].'|'.$row['date']->toDateString().'|'.$row['kind'])->take(self::LIMIT);
        $names = Organization::whereIn('id', $found->pluck('organization_id')->filter()->unique())->get()->keyBy('id');

        return $found->map(fn ($row) => [
            'organization' => Tenant::label($row['organization_id'] ? $names[$row['organization_id']] ?? null : null),
            'date' => $row['date']->format('d.m.Y'),
            'kind' => $row['kind'],
        ])->values()->all();
    }

    /** The value as it is compared, or null when it is not complete enough to look for. */
    public function normalize(string $field, string $value): ?string
    {
        $value = mb_strtoupper(trim($value));
        $digits = preg_replace('/\D/', '', $value);
        $plain = preg_replace('/[^A-Z0-9]/', '', $value);

        return match ($field) {
            'passport' => preg_match('/^[A-Z]{2}\d{7}$/', $plain) ? $plain : null,
            'pinfl' => strlen($digits) === 14 ? $digits : null,
            'inn' => strlen($digits) === 9 ? $digits : null,
            'cadastral' => strlen($digits) >= 8 ? $digits : null,
            'vin' => strlen($plain) >= 11 && strlen($plain) <= 17 ? $plain : null,
            'plate' => strlen($plain) >= 6 && strlen($plain) <= 10 ? $plain : null,
            default => null,
        };
    }

    /**
     * Valuations of the clients found in the other workspaces; a client with none is listed by itself.
     *
     * @return Collection<int, array{organization_id: ?int, date: Carbon, kind: string}>
     */
    private function ofClients(\Closure $match): Collection
    {
        $clients = $this->elsewhere(Client::query())->where($match)->limit(50)->get(['id', 'organization_id', 'created_at']);
        if ($clients->isEmpty()) {
            return collect();
        }

        $links = DB::table('client_object')->whereIn('client_id', $clients->pluck('id'))->get(['client_id', 'object_type', 'object_id']);
        $rows = collect();
        foreach ($links->groupBy('object_type') as $alias => $group) {
            $class = Registry::valuationModels()[$alias] ?? null;
            if ($class) {
                $rows = $rows->merge($this->ofObjects($class, fn (Builder $q) => $q->whereIn('id', $group->pluck('object_id'))));
            }
        }

        $withObjects = $links->pluck('client_id')->unique();

        return $rows->merge($clients->reject(fn (Client $client) => $withObjects->contains($client->id))->map(fn (Client $client) => [
            'organization_id' => $client->organization_id,
            'date' => $client->created_at,
            'kind' => FieldNames::subject('client'),
        ]));
    }

    /**
     * @param  class-string<Model>  $class
     * @return Collection<int, array{organization_id: ?int, date: Carbon, kind: string}>
     */
    private function ofObjects(string $class, \Closure $match): Collection
    {
        return $this->elsewhere($class::query())->where($match)->limit(50)
            ->get(['id', 'organization_id', 'valuation_date', 'contract_date', 'created_at'])
            ->map(fn (Model $object) => [
                'organization_id' => $object->organization_id,
                'date' => $object->valuation_date ?? $object->contract_date ?? $object->created_at,
                'kind' => FieldNames::subject($object->getMorphClass()),
            ]);
    }

    /** Records of the workspaces other than the current one. */
    private function elsewhere(Builder $query): Builder
    {
        $query->withoutGlobalScope(Client::WORKSPACE_SCOPE);
        $id = Tenant::id();

        return $id === null
            ? $query->whereNotNull('organization_id')
            : $query->where(fn (Builder $q) => $q->whereNull('organization_id')->orWhere('organization_id', '!=', $id));
    }

    /** A column with these characters taken out, in SQL that SQLite and MySQL both understand. */
    private function stripped(string $column, array $characters): string
    {
        foreach ($characters as $character) {
            $column = "replace({$column}, '{$character}', '')";
        }

        return $column;
    }
}
