<?php

namespace App\Support;

use Illuminate\Support\Facades\Gate;

/**
 * Sidebar navigation of the admin panel. Every page passes its "nav" key
 * to the layout, which marks the matching item and opens its group.
 */
class AdminMenu
{
    /**
     * @return list<array{key:string, label:string, icon:string, color:string, url?:string, badge?:int|null, children?:list<array{key:string,label:string,url:string,mini?:string}>}|array{section:string}>
     */
    public static function items(int $newLeads = 0): array
    {
        // The order and the groups follow the valuation portal the appraisers know;
        // what this system adds (dashboard, website requests, management) comes after.
        $items = [
            ['key' => 'clients', 'label' => __('Clients'), 'icon' => 'puzzle', 'color' => '#38bdf8', 'children' => [
                ['key' => 'clients.individual', 'mini' => 'P', 'label' => __('Individuals'), 'url' => route('admin.clients.index', ['type' => 'individual'])],
                ['key' => 'clients.legal', 'mini' => 'L', 'label' => __('Legal entities'), 'url' => route('admin.clients.index', ['type' => 'legal'])],
            ]],
            ['key' => 'realestate', 'label' => __('New real estate'), 'icon' => 'house', 'color' => '#34d399', 'children' => [
                ['key' => 'realestate.nonresidential', 'mini' => mb_substr(__('Non-residential'), 0, 1), 'label' => __('Non-residential'), 'url' => route('admin.real-estates.index', ['kind' => 'nonresidential'])],
                ['key' => 'realestate.residential', 'mini' => mb_substr(__('Residential'), 0, 1), 'label' => __('Residential'), 'url' => route('admin.real-estates.index', ['kind' => 'residential'])],
            ]],
            ['key' => 'assets', 'label' => __('Movable property'), 'icon' => 'boxes', 'color' => '#fbbf24', 'children' => [
                ['key' => 'assets', 'mini' => 'A', 'label' => __('Movable property'), 'url' => route('admin.assets.index')],
            ]],
            ['key' => 'vehicles', 'label' => __('Vehicles'), 'icon' => 'car', 'color' => '#22d3ee', 'children' => [
                ['key' => 'vehicles', 'mini' => 'A', 'label' => __('Vehicles'), 'url' => route('admin.vehicles.index')],
            ]],
            ['key' => 'damage', 'label' => __('Damage caused'), 'icon' => 'wrench', 'color' => '#fb7185', 'children' => [
                ['key' => 'damage.cases', 'label' => __('Damage assessments'), 'url' => route('admin.damage-cases.index')],
                ['key' => 'damage.models', 'label' => __('List of cars'), 'url' => route('admin.car-models.index')],
                ['key' => 'damage.parts', 'label' => __('Car spare parts'), 'url' => route('admin.spare-parts.index')],
                ['key' => 'damage.small', 'label' => __('Small spare parts'), 'url' => route('admin.small-parts.index')],
                ['key' => 'damage.norms', 'label' => __('Repair time norms'), 'url' => route('admin.repair-norms.index')],
            ]],
            ['key' => 'other', 'label' => __('Other property'), 'icon' => 'monitor', 'color' => '#a78bfa', 'children' => [
                ['key' => 'other.list', 'label' => __('List of other property'), 'url' => route('admin.other-assets.index')],
                ['key' => 'other.tools', 'label' => __('Valuation plus'), 'url' => route('admin.tools')],
            ]],
            ['section' => __('Management')],
            ['key' => 'dashboard', 'label' => __('Dashboard'), 'icon' => 'layout-dashboard', 'color' => '#818cf8', 'url' => route('admin.dashboard')],
            ['key' => 'leads', 'label' => __('Website requests'), 'icon' => 'inbox', 'color' => '#f472b6', 'url' => route('admin.leads.index'), 'badge' => $newLeads ?: null],
        ];

        if (Gate::allows('admin')) {
            $items[] = ['key' => 'users', 'label' => __('Employees'), 'icon' => 'user-cog', 'color' => '#38bdf8', 'url' => route('admin.users.index')];
            $items[] = ['key' => 'regions', 'label' => __('Regions and districts'), 'icon' => 'map-pin', 'color' => '#34d399', 'url' => route('admin.regions.index')];
            $items[] = ['key' => 'activity', 'label' => __('Audit log'), 'icon' => 'history', 'color' => '#fbbf24', 'url' => route('admin.activity.index')];
            $items[] = ['key' => 'system', 'label' => __('System status'), 'icon' => 'activity', 'color' => '#f87171', 'url' => route('admin.system.index')];
            $items[] = ['key' => 'settings', 'label' => __('Settings'), 'icon' => 'settings', 'color' => '#94a3b8', 'url' => route('admin.settings.edit')];
        }

        return $items;
    }

    /** Whether the item (or one of its children) is the current page. */
    public static function isActive(string $itemKey, ?string $current): bool
    {
        return $current !== null && ($current === $itemKey || str_starts_with($current, $itemKey.'.'));
    }
}
