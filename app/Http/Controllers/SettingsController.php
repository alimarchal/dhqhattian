<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class SettingsController extends Controller
{
    /** Opening Settings needs at least one of these (each card is also checked on its own). */
    public const PERMISSIONS = [
        'view departments',
        'view government departments',
        'view fee types',
        'view users',
        'view roles',
        'view permissions',
        'manage permissions',
    ];

    public function __invoke(Request $request)
    {
        $user = $request->user();

        $sections = collect([
            [
                'title' => 'Hospital setup',
                'hint' => 'Master data used by the registration, chit and billing forms.',
                'cards' => [
                    ['label' => 'Departments', 'text' => 'OPD departments and their daily patient limits', 'route' => 'department.index', 'permission' => 'view departments', 'tone' => 'navy', 'icon' => 'M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21'],
                    ['label' => 'Government Departments', 'text' => 'Departments whose staff are entitled patients', 'route' => 'governmentDepartment.index', 'permission' => 'view government departments', 'tone' => 'slate', 'icon' => 'M12 21v-8.25M15.75 21v-8.25M8.25 21v-8.25M3 9l9-6 9 6m-1.5 12V10.332A48.36 48.36 0 0 0 12 9.75c-2.551 0-5.056.2-7.5.582V21M3 21h18M12 6.75h.008v.008H12V6.75Z'],
                    ['label' => 'Hospital Fees', 'text' => 'Chit, test and admission fees with the HIF share', 'route' => 'feeType.index', 'permission' => 'view fee types', 'tone' => 'green', 'icon' => 'M12 6v12m-3-2.818.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z'],
                ],
            ],
            [
                'title' => 'Access & identity',
                'hint' => 'Who can sign in and what they can open.',
                'cards' => [
                    ['label' => 'Users', 'text' => 'Logins, roles and extra permissions', 'route' => 'users.index', 'permission' => 'view users', 'tone' => 'navy', 'icon' => 'M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z'],
                    ['label' => 'Roles', 'text' => 'Ready-made sets of permissions', 'route' => 'roles.index', 'permission' => 'view roles', 'tone' => 'amber', 'icon' => 'M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z'],
                    ['label' => 'Permissions', 'text' => 'Every action that can be allowed', 'route' => 'permissions.index', 'permission' => 'view permissions', 'tone' => 'slate', 'icon' => 'M15.75 5.25a3 3 0 0 1 3 3m3 0a6 6 0 0 1-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1 1 21.75 8.25Z'],
                ],
            ],
        ])->map(function ($section) use ($user) {
            $section['cards'] = collect($section['cards'])->filter(fn ($card) => $user->can($card['permission']))->values()->all();

            return $section;
        })->filter(fn ($section) => $section['cards'])->values()->all();

        return view('settings.index', ['sections' => $sections]);
    }
}
