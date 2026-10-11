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
     * @return list<array{key:string, label:string, icon:string, url?:string, badge?:int|null, children?:list<array{key:string,label:string,url:string}>}|array{section:string}>
     */
    public static function items(int $newLeads = 0): array
    {
        $items = [
            ['key' => 'dashboard', 'label' => __('Dashboard'), 'icon' => 'layout-dashboard', 'url' => route('admin.dashboard')],
            ['key' => 'clients', 'label' => __('Clients'), 'icon' => 'users', 'children' => [
                ['key' => 'clients.individual', 'label' => __('Individuals'), 'url' => route('admin.clients.index', ['type' => 'individual'])],
                ['key' => 'clients.legal', 'label' => __('Legal entities'), 'url' => route('admin.clients.index', ['type' => 'legal'])],
            ]],
            ['key' => 'realestate', 'label' => __('Real estate'), 'icon' => 'house', 'children' => [
                ['key' => 'realestate.nonresidential', 'label' => __('Non-residential'), 'url' => route('admin.real-estates.index', ['kind' => 'nonresidential'])],
                ['key' => 'realestate.residential', 'label' => __('Residential'), 'url' => route('admin.real-estates.index', ['kind' => 'residential'])],
            ]],
            ['key' => 'assets', 'label' => __('Movable property'), 'icon' => 'boxes', 'url' => route('admin.assets.index')],
            ['key' => 'vehicles', 'label' => __('Vehicles'), 'icon' => 'car', 'url' => route('admin.vehicles.index')],
            ['key' => 'damage', 'label' => __('Damage caused'), 'icon' => 'wrench', 'children' => [
                ['key' => 'damage.cases', 'label' => __('Damage assessments'), 'url' => route('admin.damage-cases.index')],
                ['key' => 'damage.models', 'label' => __('List of cars'), 'url' => route('admin.car-models.index')],
                ['key' => 'damage.parts', 'label' => __('Car spare parts'), 'url' => route('admin.spare-parts.index')],
                ['key' => 'damage.small', 'label' => __('Small spare parts'), 'url' => route('admin.small-parts.index')],
                ['key' => 'damage.norms', 'label' => __('Repair time norms'), 'url' => route('admin.repair-norms.index')],
            ]],
            ['key' => 'other', 'label' => __('Other property'), 'icon' => 'monitor', 'children' => [
                ['key' => 'other.list', 'label' => __('List of other property'), 'url' => route('admin.other-assets.index')],
                ['key' => 'other.tools', 'label' => __('Valuation plus'), 'url' => route('admin.tools')],
            ]],
            ['key' => 'leads', 'label' => __('Website requests'), 'icon' => 'inbox', 'url' => route('admin.leads.index'), 'badge' => $newLeads ?: null],
        ];

        if (Gate::allows('admin')) {
            $items[] = ['section' => __('Management')];
            $items[] = ['key' => 'users', 'label' => __('Employees'), 'icon' => 'user-cog', 'url' => route('admin.users.index')];
            $items[] = ['key' => 'regions', 'label' => __('Regions and districts'), 'icon' => 'map-pin', 'url' => route('admin.regions.index')];
            $items[] = ['key' => 'activity', 'label' => __('Activity log'), 'icon' => 'history', 'url' => route('admin.activity.index')];
            $items[] = ['key' => 'settings', 'label' => __('Settings'), 'icon' => 'settings', 'url' => route('admin.settings.edit')];
        }

        return $items;
    }

    /** Whether the item (or one of its children) is the current page. */
    public static function isActive(string $itemKey, ?string $current): bool
    {
        return $current !== null && ($current === $itemKey || str_starts_with($current, $itemKey.'.'));
    }
}
