<?php

namespace App\Reports;

use App\Enums\DocumentKind;
use App\Enums\PersonRole;
use App\Models\RealEstate;
use Illuminate\Database\Eloquent\Model;

/**
 * The questionnaire of the customer ("So'rovnoma"): what the customer states
 * about the object before the valuation — its use and lease, repairs,
 * encumbrances, disputes, alterations, known defects, debts, the documents
 * given and who shows the object. The customer signs it in the contract
 * pack; what was entered is also listed in the report.
 */
final class Survey
{
    /** Question → [text in the documents, name on the form, only for real estate]. */
    public const QUESTIONS = [
        'use' => ['Obyektdan hozirgi foydalanish', 'Current use of the object', false],
        'lease' => ['Ijara shartlari (ijarachi, oylik ijara haqi, muddati)', 'Lease terms (tenant, monthly rent, term)', false],
        'last_repair' => ["Oxirgi ta'mir (yili va turi)", 'Last repair (year and kind)', false],
        'planned_works' => ["Rejalashtirilgan ta'mir yoki rekonstruksiya", 'Planned repair or reconstruction', false],
        'encumbrances' => ['Garov, taqiq, ijara va boshqa yuklamalar', 'Pledge, arrest, lease and other encumbrances', false],
        'disputes' => ["Sud nizolari va uchinchi shaxslarning da'volari", 'Court disputes and claims of third parties', false],
        'alterations' => ["O'zboshimcha qurilish yoki qayta rejalashtirish", 'Unauthorised construction or re-planning', true],
        'defects' => ["Buyurtmachiga ma'lum bo'lgan kamchiliklar", 'Defects known to the customer', false],
        'debts' => ["Kommunal to'lovlar bo'yicha qarzdorlik", 'Debts for utilities', true],
        'documents' => ['Taqdim etilgan hujjatlar', 'Documents provided', false],
        'contact' => ["Obyektni ko'rsatadigan shaxs va uning telefoni", 'Person who shows the object, phone', false],
    ];

    /** Typical answers offered on the form (anything may be typed). */
    public const OPTIONS = [
        'use' => ["O'z ehtiyoji uchun foydalaniladi", 'Ijaraga berilgan', 'Foydalanilmaydi', "Ta'mirlanmoqda"],
        'encumbrances' => ['Mavjud emas', 'Bank garovida', 'Taqiq (arest) qo‘yilgan', 'Ijaraga berilgan'],
        'disputes' => ['Mavjud emas'],
        'alterations' => ['Mavjud emas', 'Qayta rejalashtirilgan (hujjatlashtirilmagan)'],
        'debts' => ['Mavjud emas'],
    ];

    /** @return list<string> the questions that apply to the object */
    public static function keys(Model $item): array
    {
        return array_keys(array_filter(self::QUESTIONS, fn ($question) => ! $question[2] || $item instanceof RealEstate));
    }

    /**
     * Answers known without asking: the documents uploaded and the customer's phone.
     *
     * @return array<string, string>
     */
    public static function defaults(Model $item): array
    {
        $item->loadMissing(['clients', 'attachments']);
        $customer = $item->clients->firstWhere('pivot.role', PersonRole::Customer->value)
            ?? $item->clients->firstWhere('pivot.role', PersonRole::Owner->value)
            ?? $item->clients->first();
        $documents = $item->attachments->pluck('kind')->filter()->unique()
            ->map(fn ($kind) => DocumentKind::tryFrom((string) $kind))
            ->filter(fn ($kind) => $kind !== null && ! in_array($kind, [DocumentKind::Photo, DocumentKind::Other], true))
            ->map(fn (DocumentKind $kind) => $kind->label())->implode(', ');

        return array_filter([
            'documents' => $documents,
            'contact' => $customer ? trim($customer->title.($customer->phone ? ', '.$customer->phone : '')) : '',
        ]);
    }

    /**
     * The questions with the answers entered (or known); an empty answer is left to be written by hand.
     *
     * @return list<array{key: string, question: string, answer: ?string}>
     */
    public static function build(Model $item): array
    {
        $saved = $item instanceof RealEstate ? (array) (((array) $item->report)['survey'] ?? []) : [];
        $answers = [...self::defaults($item), ...array_filter($saved, fn ($answer) => is_string($answer) && trim($answer) !== '')];

        return array_map(fn ($key) => [
            'key' => $key,
            'question' => self::QUESTIONS[$key][0],
            'answer' => $answers[$key] ?? null,
        ], self::keys($item));
    }
}
