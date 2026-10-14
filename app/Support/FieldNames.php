<?php

namespace App\Support;

/**
 * Human readable names of database columns, used in the activity log.
 */
class FieldNames
{
    public static function label(string $field): string
    {
        $labels = [
            'name' => __('Name'),
            'kind' => __('Object type'),
            'type' => __('Type'),
            'status' => __('Status'),
            'market_value' => __('Market value'),
            'value_cost' => __('Cost approach'),
            'value_comparative' => __('Comparative approach'),
            'value_income' => __('Income approach'),
            'approaches' => __('Approaches'),
            'purpose' => __('Purpose of valuation'),
            'valuation_date' => __('Valuation date'),
            'inspection_date' => __('Inspection date'),
            'contract_number' => __('Contract number'),
            'contract_date' => __('Contract date'),
            'service_fee' => __('Service fee'),
            'report_number' => __('Report number'),
            'appraiser_id' => __('Appraiser'),
            'region_id' => __('Region'),
            'district_id' => __('District'),
            'address' => __('Address'),
            'notes' => __('Notes'),
            'cadastral_number' => __('Cadastral number'),
            'total_area' => __('Total area'),
            'useful_area' => __('Useful area'),
            'living_area' => __('Living area'),
            'land_area' => __('Land area'),
            'features' => __('Special amenities'),
            'phone' => __('Phone'),
            'email' => __('E-mail'),
            'completed_at' => __('Completed'),
            'market_value_usd' => __('Value in USD'),
            'parts_total' => __('Spare parts'),
            'wear_total' => __('Wear'),
            'labor_total' => __('Repair work'),
            'paint_total' => __('Paint materials'),
            'wear_percent' => __('Vehicle wear'),
            'labor_rate' => __('Standard hour rate'),
            'price' => __('Price'),
            'hours' => __('Norm hours'),
            'role' => __('Role'),
            'is_active' => __('Active'),
            'password' => __('Password'),
            'file' => __('File'),
            'files' => __('Files'),
            'rows' => __('Rows'),
            'skipped' => __('Skipped'),
            'filters' => __('Filters'),
            'list' => __('List'),
            'reason' => __('Reason'),
            'remember' => __('Remember me'),
            'ip' => 'IP',
            'code' => __('Verification code'),
            'found' => __('Found'),
            'mode' => __('Way'),
            'added' => __('Added'),
            'title' => __('Name'),
            'task' => __('Task'),
            'error' => __('Error'),
            'ok' => __('Result'),
            'checked' => __('Checked'),
            'record' => __('Record'),
            'records' => __('Records'),
            'older_than_days' => __('Older than, days'),
            'legal_name' => __('Legal name'),
            'director' => __('Director'),
            'license' => __('License'),
            'stat_years' => __('Years of experience'),
            'stat_reports' => __('Reports completed'),
            'stat_clients' => __('Clients'),
            'stat_banks' => __('Partner banks'),
            'legal_name_ru' => __('Legal name in Russian'),
            'oked' => __('OKED'),
            'membership' => __('Membership certificate of the appraisers\' association'),
            'report_city' => __('City printed on reports'),
            'sections' => __('Sections'),
            'document' => __('Document'),
            'script' => __('Alphabet of the report'),
            'certificate_date' => __('Certificate issued on'),
            'certificate_issuer' => __('Certificate issued by'),
            'report' => __('Report data'),
        ];

        // Fields of the valuation report form are logged as "report.<field>".
        if (str_starts_with($field, 'report.')) {
            return __('Report data').': '.str_replace('_', ' ', substr($field, 7));
        }

        return $labels[$field] ?? str_replace('_', ' ', $field);
    }

    /** Name of a record type by its morph alias. */
    public static function subject(?string $alias): string
    {
        return match ($alias) {
            'real_estate' => __('Real estate'),
            'asset' => __('Movable property'),
            'vehicle' => __('Vehicle'),
            'other_asset' => __('Other property'),
            'damage_case' => __('Damage assessment'),
            'client' => __('Client'),
            'lead' => __('Website request'),
            'user' => __('Employee'),
            'car_model' => __('Car model'),
            'spare_part' => __('Spare part'),
            'small_part' => __('Small part'),
            'repair_norm' => __('Repair norm'),
            default => (string) $alias,
        };
    }
}
