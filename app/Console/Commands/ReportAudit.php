<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReportAudit extends Command
{
    protected $signature = 'app:report-audit
        {--start= : Start date (Y-m-d), defaults to start of current year}
        {--end= : End date (Y-m-d), defaults to today}
        {--fix : Auto-fix found issues (soft-delete orphan records, zero-out entitled amounts)}';

    protected $description = 'Audit and fix data integrity issues that cause report discrepancies between dept-wise-two and IPD daily reports';

    public function handle(): int
    {
        $startDate = ($this->option('start') ?? now()->startOfYear()->format('Y-m-d')).' 00:00:00';
        $endDate = ($this->option('end') ?? now()->format('Y-m-d')).' 23:59:59';
        $fix = $this->option('fix');

        $this->info("Auditing: {$startDate} → {$endDate}");
        $this->newLine();

        $issuesFound = 0;

        $issuesFound += $this->checkOrphanPatientTests($startDate, $endDate, $fix);
        $issuesFound += $this->checkEntitledWithNonZeroAmount($startDate, $endDate, $fix);
        $issuesFound += $this->checkAmountMismatch($startDate, $endDate, $fix);
        $issuesFound += $this->showReportTotals($startDate, $endDate);

        $this->newLine();
        if ($issuesFound === 0) {
            $this->info('✓ No issues found. Reports are clean.');
        } else {
            $this->warn("Found {$issuesFound} issue(s)".($fix ? ' — all fixed.' : '. Run with --fix to auto-fix.'));
        }

        return self::SUCCESS;
    }

    /**
     * PatientTests whose invoice is soft-deleted but the PT itself is not.
     * These orphans appear in dept-wise-two (queries PTs) but not in IPD daily (queries invoices).
     */
    private function checkOrphanPatientTests(string $startDate, string $endDate, bool $fix): int
    {
        $this->components->twoColumnDetail('<fg=yellow>Orphan PatientTests</>', 'PTs with soft-deleted invoices');

        $orphans = DB::table('patient_tests')
            ->join('invoices', 'patient_tests.invoice_id', '=', 'invoices.id')
            ->whereNull('patient_tests.deleted_at')
            ->whereNotNull('invoices.deleted_at')
            ->whereBetween('patient_tests.created_at', [$startDate, $endDate])
            ->select(
                'patient_tests.id as pt_id',
                'patient_tests.invoice_id',
                'patient_tests.total_amount',
                'patient_tests.government_non_gov',
                'patient_tests.fee_type_id',
                DB::raw('invoices.deleted_at as inv_deleted_at')
            )
            ->get();

        if ($orphans->isEmpty()) {
            $this->line('  None found.');

            return 0;
        }

        $this->table(
            ['PT ID', 'Invoice ID', 'Amount', 'Gov', 'FeeType', 'Invoice Deleted At'],
            $orphans->map(fn ($r) => [$r->pt_id, $r->invoice_id, $r->total_amount, $r->government_non_gov, $r->fee_type_id, $r->inv_deleted_at])
        );
        $this->warn('  Sum: '.number_format($orphans->sum('total_amount'), 2));

        if ($fix) {
            $ids = $orphans->pluck('pt_id')->toArray();
            DB::table('patient_tests')
                ->whereIn('id', $ids)
                ->whereNull('deleted_at')
                ->update(['deleted_at' => now()]);
            $this->info('  Fixed: soft-deleted '.count($ids).' orphan PatientTests.');
        }

        return $orphans->count();
    }

    /**
     * Entitled patients (government_non_gov=1) should have amount=0 in chits.
     * Non-zero amounts inflate the dept-wise-two totals.
     */
    private function checkEntitledWithNonZeroAmount(string $startDate, string $endDate, bool $fix): int
    {
        $this->newLine();
        $this->components->twoColumnDetail('<fg=yellow>Entitled Chits with Non-Zero Amount</>', 'Gov=1 but amount > 0');

        $badChits = DB::table('chits')
            ->whereNull('deleted_at')
            ->where('government_non_gov', 1)
            ->where('amount', '!=', 0)
            ->whereBetween('issued_date', [$startDate, $endDate])
            ->select('id', 'patient_id', 'fee_type_id', 'amount', 'amount_hif', 'issued_date')
            ->get();

        if ($badChits->isEmpty()) {
            $this->line('  None found.');

            return 0;
        }

        $this->table(
            ['Chit ID', 'Patient', 'FeeType', 'Amount', 'HIF', 'Date'],
            $badChits->map(fn ($r) => [$r->id, $r->patient_id, $r->fee_type_id, $r->amount, $r->amount_hif, $r->issued_date])
        );

        if ($fix) {
            $ids = $badChits->pluck('id')->toArray();
            DB::table('chits')
                ->whereIn('id', $ids)
                ->update(['amount' => 0, 'amount_hif' => 0]);
            $this->info('  Fixed: zeroed amount on '.count($ids).' entitled chits.');
        }

        return $badChits->count();
    }

    /**
     * PatientTests where total_amount != hif_amount + govt_amount.
     * Fixes by recalculating govt_amount = total_amount - hif_amount.
     */
    private function checkAmountMismatch(string $startDate, string $endDate, bool $fix): int
    {
        $this->newLine();
        $this->components->twoColumnDetail('<fg=yellow>PT Amount Mismatch</>', 'total != hif + govt');

        $bad = DB::table('patient_tests')
            ->whereNull('deleted_at')
            ->where('government_non_gov', 0)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->whereRaw('ABS(total_amount - (hif_amount + govt_amount)) > 0.01')
            ->select('id', 'fee_type_id', 'total_amount', 'hif_amount', 'govt_amount', 'invoice_id', 'created_at')
            ->get();

        if ($bad->isEmpty()) {
            $this->line('  None found.');

            return 0;
        }

        $this->table(
            ['PT ID', 'FeeType', 'Total', 'HIF', 'Govt', 'Correct Govt', 'Invoice', 'Date'],
            $bad->map(fn ($r) => [
                $r->id, $r->fee_type_id, $r->total_amount, $r->hif_amount,
                $r->govt_amount, $r->total_amount - $r->hif_amount,
                $r->invoice_id, $r->created_at,
            ])
        );

        $diffSum = $bad->sum(fn ($r) => $r->total_amount - ($r->hif_amount + $r->govt_amount));
        $this->warn('  Sum of differences: '.number_format($diffSum, 2));

        if ($fix) {
            foreach ($bad as $r) {
                DB::table('patient_tests')
                    ->where('id', $r->id)
                    ->update(['govt_amount' => $r->total_amount - $r->hif_amount]);
            }
            $this->info('  Fixed: corrected govt_amount on '.count($bad).' PatientTests.');
        }

        return $bad->count();
    }

    /**
     * Compare the two report totals side by side.
     */
    private function showReportTotals(string $startDate, string $endDate): int
    {
        $this->newLine();
        $this->components->twoColumnDetail('<fg=yellow>Report Totals Comparison</>');

        $ptTotal = DB::table('patient_tests')
            ->whereNull('deleted_at')
            ->where('government_non_gov', 0)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->sum('total_amount');

        $invoiceTotal = DB::table('invoices')
            ->whereNull('deleted_at')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->sum('total_amount');

        $chitDeptWise = DB::table('chits')
            ->whereNull('deleted_at')
            ->where('government_non_gov', 0)
            ->whereBetween('issued_date', [$startDate, $endDate])
            ->sum('amount');

        $chitIPD = DB::table('chits')
            ->whereNull('deleted_at')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->sum('amount');

        $deptGrand = $ptTotal + $chitDeptWise;
        $ipdGrand = $invoiceTotal + $chitIPD;
        $diff = $ipdGrand - $deptGrand;

        $this->table(
            ['', 'PT / Invoice', 'Chits', 'Grand Total'],
            [
                ['Dept-wise-two', number_format($ptTotal, 2), number_format($chitDeptWise, 2), number_format($deptGrand, 2)],
                ['IPD Daily', number_format($invoiceTotal, 2), number_format($chitIPD, 2), number_format($ipdGrand, 2)],
                ['Difference', number_format($invoiceTotal - $ptTotal, 2), number_format($chitIPD - $chitDeptWise, 2), number_format($diff, 2)],
            ]
        );

        return $diff != 0 ? 1 : 0;
    }
}
