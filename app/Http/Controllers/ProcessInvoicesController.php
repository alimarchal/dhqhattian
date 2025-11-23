<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\PatientTest;
use Illuminate\Support\Facades\DB;

class ProcessInvoicesController extends Controller
{
    public function process()
    {
        $userId = 35;
        $today = now()->toDateString();

        // Get all invoices for user 35 for today
        $todayInvoices = Invoice::where('user_id', $userId)
            ->whereDate('created_at', $today)
            ->get();

        if ($todayInvoices->isEmpty()) {
            return response()->json([
                'message' => 'No invoices found for user 35 today',
                'total_deduction' => 0,
            ]);
        }

        // Calculate total amount for today
        $totalAmount = $todayInvoices->sum('total_amount');

        $targetDeduction = 0;

        // Determine target deduction based on total amount
        if ($totalAmount > 15000 && $totalAmount < 20000) {
            $targetDeduction = 2000;
        } elseif ($totalAmount > 22000) {
            $targetDeduction = 3000;
        } else {
            return response()->json([
                'message' => 'Total amount does not meet deduction criteria',
                'total_amount' => $totalAmount,
                'total_deduction' => 0,
            ]);
        }

        $actualDeduction = 0;
        $deletedInvoices = [];

        try {
            DB::beginTransaction();

            // Shuffle invoices to get random selection
            $shuffledInvoices = $todayInvoices->shuffle();

            foreach ($shuffledInvoices as $invoice) {
                if ($actualDeduction >= $targetDeduction) {
                    break;
                }

                $remainingNeeded = $targetDeduction - $actualDeduction;

                // If adding this invoice doesn't exceed our target, soft delete it
                if ($invoice->total_amount <= $remainingNeeded) {
                    // Soft delete all related patient tests
                    PatientTest::where('invoice_id', $invoice->id)->delete();

                    // Soft delete the invoice
                    $invoice->delete();

                    $actualDeduction += $invoice->total_amount;
                    $deletedInvoices[] = $invoice->id;
                }
            }

            DB::commit();

            return response()->json([
                'message' => 'Deduction processed successfully',
                'total_amount_today' => $totalAmount,
                'target_deduction' => $targetDeduction,
                'total_deduction' => $actualDeduction,
                'deleted_invoices_count' => count($deletedInvoices),
                'deleted_invoice_ids' => $deletedInvoices,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Error processing deduction',
                'error' => $e->getMessage(),
                'total_deduction' => 0,
            ], 500);
        }
    }

    public function restore()
    {
        $userId = 35;
        $today = now()->toDateString();

        try {
            DB::beginTransaction();

            // Get soft deleted invoices for user 35 today
            $deletedInvoices = Invoice::onlyTrashed()
                ->where('user_id', $userId)
                ->whereDate('deleted_at', $today)
                ->get();

            if ($deletedInvoices->isEmpty()) {
                return response()->json([
                    'message' => 'No deleted invoices found for user 35 today',
                    'restored_count' => 0,
                ]);
            }

            $restoredCount = 0;
            $restoredAmount = 0;

            foreach ($deletedInvoices as $invoice) {
                // Restore related patient tests
                PatientTest::onlyTrashed()
                    ->where('invoice_id', $invoice->id)
                    ->restore();

                // Restore the invoice
                $invoice->restore();

                $restoredCount++;
                $restoredAmount += $invoice->total_amount;
            }

            DB::commit();

            return response()->json([
                'message' => 'Invoices restored successfully',
                'restored_count' => $restoredCount,
                'restored_amount' => $restoredAmount,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Error restoring invoices',
                'error' => $e->getMessage(),
                'restored_count' => 0,
            ], 500);
        }
    }
}
