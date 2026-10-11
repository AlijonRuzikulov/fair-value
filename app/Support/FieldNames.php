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
        ];

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
