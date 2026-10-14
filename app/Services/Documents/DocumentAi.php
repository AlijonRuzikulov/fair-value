<?php

namespace App\Services\Documents;

/**
 * Reads scans and photos of documents (passport / ID card, cadastral
 * extract, technical passport, contract) and returns their data. The
 * appraiser checks every value before it is saved.
 */
interface DocumentAi
{
    public function available(): bool;

    /**
     * @param  list<array{name: string, mime: string, data: string}>  $files  base64 images or PDFs
     * @return array{documents: list<array{file: int, kind: string}>, person: array<string, ?string>, property: array<string, mixed>, notes: ?string}
     */
    public function read(array $files): array;
}
