<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

/**
 * Arranges the flat permission list ("view patients", "create chits" ...) the way
 * the application's own menus are arranged, so an admin finds a permission under
 * the heading they see in the menu. Permissions that are not mapped yet still
 * appear (under "Other") so nothing is ever hidden.
 */
class PermissionCatalog
{
    /**
     * Module => section => permission groups (the words after the action).
     *
     * @var array<string, array{hint: string, sections: array<string, array<int, string>>}>
     */
    public const MODULES = [
        'operations' => [
            'label' => 'Operations',
            'hint' => 'Day-to-day hospital work. “View” opens the screen; add Create / Edit / Delete only where the user must change data.',
            'sections' => [
                'Dashboard' => ['dashboard', 'dashboard statistics'],
                'Patients & billing' => ['patients', 'chits', 'invoices', 'admissions'],
            ],
        ],
        'reports' => [
            'label' => 'Reports',
            'hint' => 'One permission per report group. The Reports menu appears automatically when at least one is given.',
            'sections' => [
                'Reports' => ['reports', 'opd reports', 'daily reports', 'department reports', 'admission reports', 'emergency reports', 'ssp reports'],
            ],
        ],
        'setup' => [
            'label' => 'Setup',
            'hint' => 'Master data used by the forms.',
            'sections' => [
                'Hospital setup' => ['departments', 'government departments', 'fee types', 'fee categories', 'diseases', 'districts', 'tehsils', 'admission wards'],
            ],
        ],
        'access' => [
            'label' => 'Access & identity',
            'hint' => 'Who can sign in and what they can open. Give these only to administrators.',
            'sections' => [
                'Users, roles & permissions' => ['users', 'roles', 'permissions'],
            ],
        ],
    ];

    /** @var array<string, array{0: string, 1: string}> */
    public const ACTIONS = [
        'view' => ['View', 'Open / see this screen'],
        'create' => ['Create', 'Add new records'],
        'edit' => ['Edit', 'Change existing records'],
        'delete' => ['Delete', 'Remove records'],
        'manage' => ['Manage', 'Add / change / remove entries'],
        'assign' => ['Assign', 'Give permissions to roles and users'],
    ];

    /**
     * @param  Collection<int, Permission>  $permissions
     * @return array<int, array{key: string, label: string, hint: string, sections: array<int, array{label: string, groups: array<int, array{key: string, label: string, permissions: array<int, array{id: string, name: string, action: string, label: string, help: string}>}>}>}>
     */
    public static function build(Collection $permissions): array
    {
        $grouped = [];
        foreach ($permissions as $permission) {
            [$action, $group] = array_pad(explode(' ', $permission->name, 2), 2, '');
            if ($group === '') {
                [$action, $group] = ['view', $permission->name];
            }
            [$label, $help] = self::ACTIONS[$action] ?? [Str::headline($action), ''];
            $grouped[$group][] = [
                'id' => (string) $permission->id,
                'name' => $permission->name,
                'action' => $action,
                'label' => $label,
                'help' => $help,
            ];
        }

        $order = array_flip(array_keys(self::ACTIONS));
        foreach ($grouped as &$items) {
            usort($items, fn (array $a, array $b) => [$order[$a['action']] ?? 99, $a['name']] <=> [$order[$b['action']] ?? 99, $b['name']]);
        }
        unset($items);

        $placed = [];
        $modules = [];
        foreach (self::MODULES as $key => $module) {
            $sections = [];
            foreach ($module['sections'] as $sectionLabel => $groupKeys) {
                $groups = [];
                foreach ($groupKeys as $groupKey) {
                    if (isset($grouped[$groupKey])) {
                        $placed[$groupKey] = true;
                        $groups[] = ['key' => $groupKey, 'label' => Str::headline($groupKey), 'permissions' => $grouped[$groupKey]];
                    }
                }
                if ($groups) {
                    $sections[] = ['label' => $sectionLabel, 'groups' => $groups];
                }
            }
            if ($sections) {
                $modules[] = ['key' => $key, 'label' => $module['label'], 'hint' => $module['hint'], 'sections' => $sections];
            }
        }

        $leftovers = collect($grouped)->reject(fn ($items, $key) => isset($placed[$key]))->sortKeys();
        if ($leftovers->isNotEmpty()) {
            $modules[] = [
                'key' => 'other',
                'label' => 'Other',
                'hint' => 'Permissions not yet placed on a screen.',
                'sections' => [['label' => 'Other', 'groups' => $leftovers->map(fn ($perms, $key) => ['key' => $key, 'label' => Str::headline($key), 'permissions' => $perms])->values()->all()]],
            ];
        }

        return $modules;
    }
}
