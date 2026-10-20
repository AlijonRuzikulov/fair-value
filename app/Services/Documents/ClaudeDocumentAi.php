<?php

namespace App\Services\Documents;

use Anthropic\Client;
use RuntimeException;

/**
 * DocumentAi on Claude: the scans are sent with a JSON schema and the
 * values are copied from the documents as written.
 */
class ClaudeDocumentAi implements DocumentAi
{
    // Routes a declined request to a substitute model on the server side.
    private const BETAS = ['server-side-fallback-2026-07-01'];

    private const SYSTEM = <<<'TXT'
        You read documents for a licensed real-estate appraiser in Uzbekistan. The files are scans or phone photos of:
        - an ID card or biometric passport of the Republic of Uzbekistan (front and back; the back has the personal number, PINFL, 14 digits);
        - an extract from the state register of rights to real estate ("Davlat Reyestridan KO'CHIRMA", issued by the Cadastre Agency);
        - a technical (cadastral) passport, a purchase or gift contract, or another document.
        Copy every value exactly as the document shows it. Use null for anything that is not visible or not readable. Never guess.
        person: the owner of the property / the holder of the ID document. Names in Latin letters as printed on the ID card
        (last_name = surname, first_name = given name, middle_name = patronymic). Dates as YYYY-MM-DD.
        passport_series: the two letters (e.g. "AE"); passport_number: the 7 digits; passport_issued_by: the issuing authority as printed.
        property: from the extract or the technical passport. cadastral_number exactly with colons (e.g. "11:20:01:08:01:0079:0001:051").
        address: a clean address in Uzbek Latin (region, city or district, street, house, flat), even if the document mixes Russian and Uzbek;
        region and district separately (e.g. "Toshkent viloyati", "Chirchiq shahri"). Areas in square metres as numbers; built_up_area is the area under the building ("qurilish osti maydoni").
        rights_basis: the document that establishes the right, as written (e.g. "notarial idoraning 27.10.2025 yildagi ... son oldi-sotdi shartnomasi").
        encumbrances: mortgage, arrest or other restrictions as written, or null if the field is empty.
        documents: for every file (numbered from 0 in the order given) say what it is.
        TXT;

    public function __construct(private ?Client $client = null) {}

    public function available(): bool
    {
        return config('fairvalue.ai.documents') && ($this->client !== null || filled(config('services.anthropic.key')));
    }

    public function read(array $files): array
    {
        $content = [];
        foreach ($files as $i => $file) {
            $content[] = ['type' => 'text', 'text' => "File {$i}: {$file['name']}"];
            $content[] = $file['mime'] === 'application/pdf'
                ? ['type' => 'document', 'source' => ['type' => 'base64', 'mediaType' => 'application/pdf', 'data' => $file['data']]]
                : ['type' => 'image', 'source' => ['type' => 'base64', 'mediaType' => $file['mime'], 'data' => $file['data']]];
        }
        $content[] = ['type' => 'text', 'text' => 'Read these documents and return their data.'];

        $message = $this->client()->beta->messages->create(
            model: config('services.anthropic.model'),
            maxTokens: 16000,
            system: self::SYSTEM,
            messages: [['role' => 'user', 'content' => $content]],
            outputConfig: [
                'effort' => 'medium',
                'format' => ['type' => 'json_schema', 'schema' => self::schema()],
            ],
            fallbacks: 'default',
            betas: self::BETAS,
        );

        if ($message->stopReason === 'refusal') {
            throw new RuntimeException(__('The AI service declined the request.'));
        }
        if ($message->stopReason === 'max_tokens') {
            throw new RuntimeException(__('The documents could not be read completely.'));
        }

        foreach ($message->content as $block) {
            if ($block->type === 'text') {
                $data = json_decode($block->text, true);
                if (is_array($data)) {
                    return DocumentData::normalise($data);
                }
            }
        }

        throw new RuntimeException(__('The documents could not be read.'));
    }

    private function client(): Client
    {
        return $this->client ??= new Client(
            apiKey: (string) config('services.anthropic.key'),
            requestOptions: ['timeout' => 140, 'maxRetries' => 1],
        );
    }

    private static function schema(): array
    {
        $nullable = fn (string $type) => ['anyOf' => [['type' => $type], ['type' => 'null']]];
        $object = fn (array $fields) => [
            'type' => 'object',
            'properties' => $fields,
            'required' => array_keys($fields),
            'additionalProperties' => false,
        ];
        $strings = fn (array $keys) => array_fill_keys($keys, $nullable('string'));

        return $object([
            'documents' => ['type' => 'array', 'items' => $object([
                'file' => ['type' => 'integer'],
                'kind' => ['type' => 'string', 'enum' => DocumentData::KINDS],
            ])],
            'person' => $object($strings(DocumentData::PERSON)),
            'property' => $object([
                ...$strings(['cadastral_number', 'object_type', 'name', 'address', 'region', 'district', 'owner_name', 'share', 'rights_type', 'registration_date', 'rights_basis', 'encumbrances']),
                ...array_fill_keys(['total_area', 'living_area', 'useful_area', 'land_area', 'built_up_area'], $nullable('number')),
                ...array_fill_keys(['floors_total', 'floor', 'rooms', 'year_built'], $nullable('integer')),
            ]),
            'notes' => $nullable('string'),
        ]);
    }
}
