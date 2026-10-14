<?php

namespace App\Http\Controllers;

use App\Models\GovernmentDepartment;

class GovernmentDepartmentController extends MasterCrudController
{
    protected function model(): string
    {
        return GovernmentDepartment::class;
    }

    protected function config(): array
    {
        return [
            'title' => 'Government Departments',
            'singular' => 'Government department',
            'route' => 'governmentDepartment',
            'permission' => 'government departments',
            'usedBy' => [['chits', 'government_department_id', 'chits'], ['patients', 'government_department_id', 'patients']],
            'lede' => 'Departments whose employees are entitled (government) patients.',
            'searchPlaceholder' => 'Search by department name',
            'search' => ['name'],
            'defaultSort' => ['name', 'asc'],
            'columns' => [
                ['key' => 'name', 'label' => 'Government department', 'sortable' => true],
            ],
            'fields' => [
                ['name' => 'name', 'label' => 'Department name', 'type' => 'text', 'rules' => 'required|string|max:255'],
            ],
        ];
    }
}
