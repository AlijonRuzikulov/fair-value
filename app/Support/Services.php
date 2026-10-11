<?php

namespace App\Support;

/**
 * Services shown on the website and offered in the request form.
 * Texts are translated through lang/*.json.
 */
class Services
{
    /**
     * @return array<string, array{icon:string, title:string, short:string, text:string, items:list<string>, documents:list<string>, term:string}>
     */
    public static function all(): array
    {
        return [
            'real-estate' => [
                'icon' => 'building-2',
                'title' => __('Real estate valuation'),
                'short' => __('Apartments, houses, offices, shops, warehouses, production buildings and land plots.'),
                'text' => __('We determine the market value of residential and non-residential property for bank loans, sale and purchase, inheritance, court and the charter capital. The report complies with the national valuation standards and is accepted by banks, notaries and courts.'),
                'items' => [__('Apartments and private houses'), __('Offices, shops, restaurants'), __('Warehouses and production buildings'), __('Land use rights'), __('Unfinished construction')],
                'documents' => [__('Owner\'s passport or company registration certificate'), __('Cadastral passport of the property'), __('Title document (purchase agreement, certificate, order)'), __('Technical passport (if available)')],
                'term' => __('1–3 working days'),
            ],
            'vehicles' => [
                'icon' => 'car',
                'title' => __('Vehicle valuation'),
                'short' => __('Cars, trucks, buses and special machinery of any year of manufacture.'),
                'text' => __('We inspect the vehicle, take into account its configuration, mileage and condition and compare it with the current market. The valuation is used for collateral, sale, inheritance, customs and accounting.'),
                'items' => [__('Cars'), __('Trucks and buses'), __('Special and agricultural machinery'), __('Motorcycles and trailers')],
                'documents' => [__('Vehicle registration certificate'), __('Owner\'s passport'), __('Power of attorney, if the owner is absent')],
                'term' => __('1 working day'),
            ],
            'damage' => [
                'icon' => 'wrench',
                'title' => __('Damage assessment'),
                'short' => __('Restoration cost of a vehicle or property after an accident, flood or fire.'),
                'text' => __('We calculate the cost of repair using spare part prices, repair time norms and paint costs, taking the wear of the vehicle into account, and determine the loss of commodity value. The conclusion is used to claim compensation from the insurer or the person responsible.'),
                'items' => [__('Damage to vehicles after an accident'), __('Loss of commodity value'), __('Damage to apartments after flooding or fire'), __('Damage to equipment and goods')],
                'documents' => [__('Vehicle registration certificate and owner\'s passport'), __('Road accident certificate'), __('Insurance policy (if any)'), __('Notice to the other party about the inspection')],
                'term' => __('1–2 working days after inspection'),
            ],
            'equipment' => [
                'icon' => 'factory',
                'title' => __('Equipment valuation'),
                'short' => __('Machines, production lines, office equipment, furniture and inventory.'),
                'text' => __('We value single machines and whole production complexes, including imported equipment. The valuation is needed for collateral, leasing, revaluation of fixed assets and contribution to the charter capital.'),
                'items' => [__('Machine tools and production lines'), __('Construction and special machinery'), __('Computers and office equipment'), __('Goods and inventory')],
                'documents' => [__('List of equipment with inventory numbers'), __('Purchase contracts and invoices'), __('Balance sheet data'), __('Technical documentation')],
                'term' => __('2–5 working days'),
            ],
            'business' => [
                'icon' => 'briefcase',
                'title' => __('Business and shares valuation'),
                'short' => __('Enterprises, stakes in the charter capital, shares and intangible assets.'),
                'text' => __('We determine the value of a business as a whole or of a stake in it, of shares, trademarks and software. Used for the sale of a business, attracting investment, reorganisation and corporate disputes.'),
                'items' => [__('Enterprise as a property complex'), __('Stakes and shares'), __('Trademarks and patents'), __('Software and other intangible assets')],
                'documents' => [__('Charter and registration documents'), __('Financial statements for the last 3 years'), __('List of fixed assets'), __('Information on receivables and payables')],
                'term' => __('5–10 working days'),
            ],
            'business-plans' => [
                'icon' => 'chart-line',
                'title' => __('Business plans and feasibility studies'),
                'short' => __('Business plans and feasibility studies (TEO) for banks and investors.'),
                'text' => __('We prepare business plans and feasibility studies that meet the requirements of commercial banks, investors and state programmes: market analysis, production plan, financial model and payback calculation.'),
                'items' => [__('Business plans for bank loans'), __('Feasibility studies (TEO)'), __('Financial models and cash flow forecasts'), __('Investment projects')],
                'documents' => [__('Description of the project idea'), __('Information about the initiator'), __('Commercial offers for equipment')],
                'term' => __('From 5 working days'),
            ],
            'import-expertise' => [
                'icon' => 'ship',
                'title' => __('Expert opinion on import contracts'),
                'short' => __('Conclusion on the conformity of import contract prices with the market.'),
                'text' => __('We check whether the prices of goods and equipment in an import contract correspond to market prices. The conclusion is required by banks when opening letters of credit and paying under foreign trade contracts.'),
                'items' => [__('Equipment and machinery'), __('Raw materials and goods'), __('Spare parts and components')],
                'documents' => [__('Import contract with specification'), __('Commercial offers from suppliers'), __('Technical characteristics of the goods')],
                'term' => __('1–3 working days'),
            ],
            'audit' => [
                'icon' => 'file-check',
                'title' => __('Audit opinion'),
                'short' => __('Audit of financial statements and consulting on accounting.'),
                'text' => __('Together with partner audit organisations we audit financial statements, check accounting and taxation and issue an audit opinion for shareholders, banks and government bodies.'),
                'items' => [__('Mandatory and initiative audit'), __('Accounting and tax review'), __('Consulting on financial statements')],
                'documents' => [__('Financial statements'), __('Accounting registers'), __('Constituent documents')],
                'term' => __('By agreement'),
            ],
        ];
    }

    public static function find(string $slug): ?array
    {
        return self::all()[$slug] ?? null;
    }

    /** @return array<string, string> slug => title */
    public static function titles(): array
    {
        return array_map(fn ($s) => $s['title'], self::all());
    }
}
