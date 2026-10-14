<?php

namespace App\Http\Controllers;

use App\Models\Department;

class DepartmentController extends MasterCrudController
{
    protected function model(): string
    {
        return Department::class;
    }

    protected function config(): array
    {
        return [
            'title' => 'Departments',
            'singular' => 'Department',
            'route' => 'department',
            'permission' => 'departments',
            'usedBy' => [['chits', 'department_id', 'chits'], ['users', 'department_id', 'users']],
            'lede' => 'OPD departments a chit can be issued for, with the daily patient limit of each.',
            'searchPlaceholder' => 'Search by department name',
            'search' => ['name', 'category'],
            'defaultSort' => ['name', 'asc'],
            'columns' => [
                ['key' => 'name', 'label' => 'Department', 'sortable' => true],
                ['key' => 'category', 'label' => 'Category', 'sortable' => true, 'badge' => true],
                ['key' => 'daily_patient_limit', 'label' => 'Daily patient limit', 'sortable' => true, 'align' => 'right'],
            ],
            'fields' => [
                ['name' => 'name', 'label' => 'Department name', 'type' => 'text', 'rules' => 'required|string|max:255'],
                ['name' => 'category', 'label' => 'Category', 'type' => 'select', 'options' => ['OPD' => 'OPD', 'IPD' => 'IPD'], 'default' => 'OPD', 'rules' => 'required|in:OPD,IPD'],
                ['name' => 'daily_patient_limit', 'label' => 'Daily patient limit', 'type' => 'number', 'step' => 1, 'min' => 1, 'default' => 200, 'rules' => 'required|integer|min:1|max:100000',
                    'help' => 'No more chits are issued for this department once the limit is reached for the day.'],
            ],
        ];
    }
}
