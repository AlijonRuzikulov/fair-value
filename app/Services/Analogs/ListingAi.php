<?php

namespace App\Services\Analogs;

/**
 * AI help for the comparative approach. Only public listing pages and the
 * characteristics of the object (area, rooms, district) are sent, never
 * client data.
 */
interface ListingAi
{
    public function available(): bool;

    /**
     * Sale offers described on a page: one for a listing page, several for
     * a page of search results.
     *
     * @return list<array<string, mixed>> listings in the ListingParser::empty() shape
     */
    public function extract(string $digest): array;

    /**
     * Addresses of listing pages similar to the object.
     *
     * @param  array<string, mixed>  $subject
     * @return list<string>
     */
    public function search(array $subject): array;
}
