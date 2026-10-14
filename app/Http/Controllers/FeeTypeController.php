<?php

namespace App\Http\Controllers;

use App\Models\FeeCategory;
use App\Models\FeeType;
use Illuminate\Database\Eloquent\Model;

class FeeTypeController extends MasterCrudController
{
    protected function model(): string
    {
        return FeeType::class;
    }

    protected function config(): array
    {
        $categories = fn () => FeeCategory::orderBy('name')->pluck('name', 'id')->all();

        return [
            'title' => 'Hospital Fees',
            'singular' => 'Fee',
            'route' => 'feeType',
            'permission' => 'fee types',
            'usedBy' => [['chits', 'fee_type_id', 'chits'], ['patient_tests', 'fee_type_id', 'patient tests'], ['patient_test_carts', 'fee_type_id', 'the test cart']],
            'nameKey' => 'type',
            'lede' => 'Every fee a chit, test or admission can be charged, with the HIF share of each.',
            'searchPlaceholder' => 'Search by fee name',
            'search' => ['type'],
            'with' => ['feeCategory'],
            'defaultSort' => ['type', 'asc'],
            'filters' => [
                ['name' => 'fee_category_id', 'label' => 'Category', 'options' => $categories],
                ['name' => 'status', 'label' => 'Type', 'options' => ['Normal' => 'Normal', 'Return Fee' => 'Return fee']],
            ],
            'columns' => [
                ['key' => 'type', 'label' => 'Fee', 'sortable' => true],
                ['key' => 'fee_category_id', 'label' => 'Category', 'value' => fn ($r) => $r->feeCategory?->name, 'badge' => true],
                ['key' => 'amount', 'label' => 'Amount', 'sortable' => true, 'align' => 'right', 'value' => fn ($r) => number_format($r->amount, 2)],
                ['key' => 'hif', 'label' => 'HIF', 'sortable' => true, 'align' => 'right', 'value' => fn ($r) => number_format($r->hif, 2)],
                ['key' => 'govt', 'label' => 'Govt', 'align' => 'right', 'value' => fn ($r) => number_format($r->amount - $r->hif, 2)],
                ['key' => 'status', 'label' => 'Type', 'sortable' => true, 'align' => 'center', 'value' => fn ($r) => $r->status],
            ],
            'fields' => [
                ['name' => 'fee_category_id', 'label' => 'Fee category', 'type' => 'select', 'options' => $categories, 'rules' => 'required|exists:fee_categories,id'],
                ['name' => 'type', 'label' => 'Fee name', 'type' => 'text', 'rules' => 'required|string|max:255'],
                ['name' => 'amount', 'label' => 'Total amount', 'type' => 'number', 'step' => '0.01', 'rules' => 'required|numeric', 'help' => 'A negative amount is saved as a return fee.'],
                ['name' => 'hif', 'label' => 'HIF share', 'type' => 'number', 'step' => '0.01', 'rules' => 'required|numeric', 'help' => 'The rest (amount − HIF) is the government share.'],
            ],
        ];
    }

    protected function prepare(array $data, ?Model $record): array
    {
        // A negative amount is a return fee; otherwise the saved status is kept (some positive fees are return fees).
        $data['status'] = (float) $data['amount'] < 0 ? 'Return Fee' : ($record?->status ?? 'Normal');

        return $data;
    }
}
