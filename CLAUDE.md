# Fair Value — notes for Claude

Laravel 13 / PHP 8.3 / SQLite. Website + admin panel of a valuation company. Talk to the user in Uzbek (Latin), briefly.

## Work rules
- Branch `claude/amazing-bohr-4qrtrr`; commit as `git -c user.name="Alijon Ruzikulov" -c user.email="alijonruziqulov.1996@gmail.com" commit`, push to origin. No PRs unless asked.
- Before a commit: `vendor/bin/pint --dirty` and `php artisan test --compact` (prints JSON; ~300 tests, ~70 s). Run single files with `--filter` while working.
- Translations: add keys to `lang/uz.json` and `lang/ru.json` (sorted, `ensure_ascii=False`, indent 4), then `php artisan lang:cyrillic` builds `lang/oz.json`. Tests default to `oz`; use `withSession(['locale' => 'uz'])`.
- Icons: Lucide sprite `public/images/icons.svg` (`<x-icon name>`); add missing ones from `lucide-static` (npm) by rebuilding the sprite.
- Never access clients-app.baxolash.uz directly; the user sends screenshots or pasted text of it. Copy its layout, fields and wording, not its code, name or logo.

## The "classic" look (default, Settings → admin_theme)
Copies the appraisers' valuation portal, light blue (`admin_color`: azure / orange).
- CSS: `public/css/admin-classic.css` (tokens `--accent*` on `body.theme-classic`). JS: `public/js/admin.js`.
- Real estate: `resources/views/admin/real-estates/portal.blade.php` is the add, edit and object page (one form `#object-form` → `RealEstateController::store/main`), with `_portal-steps`, `_portal-top` (two columns), `_main-fields`, `_portal-approach`, `_portal-status` (Holat/Tarixi), `_map-picker` (`compact`).
- Lists: `admin/objects/index.blade.php` (`$classic` branch); real estate rows `real-estates/_portal-row.blade.php`.
- Clients: `admin/clients/index.blade.php` (classic branch), `admin/clients/portal-form.blade.php`; "Qo'shish yangi (pasport)" reads a passport photo (`ClientController::passport`, `DocumentAi`).
- Mixed property, vehicles, damage: `usesPortal()` in their controllers → one page `admin/objects/portal.blade.php` (add, edit and object page) with `admin/{slug}/_portal-top` (two columns), `_portal-middle`/`_portal-more` (folds), `objects/_portal-docs` (boxes saved with the form as `documents[kind][]`), `objects/_portal-holat` (assets only). Lists: `admin/{slug}/_portal-head` + `_portal-row`; filters from `portalFilters()`. No notes or hints on these pages.
- No licence and no plans (the user removed both): the system always works. A user may have `access_until`; after it `EnsureAccessAllowsChanges` (alias `access`, `App\Support\Access`) makes the panel read-only for them; main admins have no end. AI needs only `ANTHROPIC_API_KEY` (`ClaudeDocumentAi`/`ClaudeListingAi::available()`; texpasport → `VehicleController::techPassport`).
- Organisations (`Organization`, `App\Support\Tenant`, middleware `workspace` before route bindings): clients and valuation objects use `BelongsToOrganization` (global scope by `organization_id`, null = the main company); activities carry `organization_id` (filtered in `ActivityController`). `Setting::get` reads an organisation's own details (`Organization::OWN_SETTINGS`), `Setting::main` the main company's. Gates `admin`/`manage-catalogs`/`main-workspace` = main company only. `ValuationHistory` (`admin.history`, `data-history` on inputs) shows who and when only.
- Report data fields kept in `real_estates.report` JSON (`RealEstateReport::values/defaults/OPTIONS`); option lists of the portal are in `RealEstateReport::OPTIONS`, `ValuationPurpose::portalOptions`, `Client::ORGANIZATIONS/MANAGEMENT`.

## Still unknown from the portal (ask the user)
"Ўзимизда" badge and the small yellow number in lists; "Манба" filter of mixed property; "Toifasi" values; "Tashkilot"/"Boshqaruv turi" option lists; non-residential "Foydalanish maqsadi" list; inside of the Documents, Comparative, Income and Reconciliation sections; Akt and So'rovnoma windows (where the portal takes a vehicle's make/model and the value of mixed property or a vehicle — here folds on the saved page); the add page of a damaged car; "Boshqa mol-mulk".
