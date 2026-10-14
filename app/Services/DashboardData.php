<?php

namespace App\Services;

use App\Models\Admission;
use App\Models\Chit;
use App\Models\Department;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Numbers for the two dashboards. "Govt" is the government share of a payment
 * (amount - HIF), the same meaning it has in the reports.
 */
class DashboardData
{
    /** @return array<string, mixed> */
    public static function frontDesk(User $user): array
    {
        $today = Carbon::today();
        $chits = fn () => Chit::where('user_id', $user->id);
        $invoices = fn () => Invoice::where('user_id', $user->id);

        $chitsToday = $chits()->whereDate('issued_date', $today);
        $invoicesToday = $invoices()->whereDate('created_at', $today);

        $chitRevenue = (float) (clone $chitsToday)->sum('amount');
        $chitHif = (float) (clone $chitsToday)->sum('amount_hif');
        $invoiceRevenue = (float) (clone $invoicesToday)->sum('total_amount');
        $invoiceHif = (float) (clone $invoicesToday)->sum('hif_amount');

        $days = self::lastDays(7);
        $chitSeries = self::perDay($chits(), 'issued_date', $days, 'COUNT(*)');
        $invoiceSeries = self::perDay($invoices(), 'created_at', $days, 'COUNT(*)');

        return [
            'chits' => (clone $chitsToday)->count(),
            'chits_yesterday' => $chits()->whereDate('issued_date', $today->copy()->subDay())->count(),
            'invoices' => (clone $invoicesToday)->count(),
            'invoices_yesterday' => $invoices()->whereDate('created_at', $today->copy()->subDay())->count(),
            'entitled' => (clone $chitsToday)->where('government_non_gov', 1)->count(),
            'non_entitled' => (clone $chitsToday)->where('government_non_gov', 0)->count(),
            'collected' => $chitRevenue + $invoiceRevenue,
            'govt' => ($chitRevenue - $chitHif) + ($invoiceRevenue - $invoiceHif),
            'hif' => $chitHif + $invoiceHif,
            'week_labels' => array_values(array_map(fn (Carbon $d) => $d->format('D d'), $days)),
            'week_chits' => array_values($chitSeries),
            'week_invoices' => array_values($invoiceSeries),
            'recent_chits' => $chits()->with(['patient:id,title,first_name,last_name', 'department:id,name'])
                ->whereDate('issued_date', $today)->latest('issued_date')->limit(8)->get(),
        ];
    }

    /** @return array<string, mixed> */
    public static function admin(): array
    {
        $today = Carbon::today();
        $yesterday = $today->copy()->subDay();

        $chitsToday = Chit::whereDate('issued_date', $today);
        $invoicesToday = Invoice::whereDate('created_at', $today);
        $chitRevenue = (float) (clone $chitsToday)->sum('amount');
        $chitHif = (float) (clone $chitsToday)->sum('amount_hif');
        $invoiceRevenue = (float) (clone $invoicesToday)->sum('total_amount');
        $invoiceHif = (float) (clone $invoicesToday)->sum('hif_amount');

        $days = self::lastDays(14);
        $chitCount = self::perDay(Chit::query(), 'issued_date', $days, 'COUNT(*)');
        $chitGovt = self::perDay(Chit::query(), 'issued_date', $days, 'SUM(amount - COALESCE(amount_hif, 0))');
        $invoiceGovt = self::perDay(Invoice::query(), 'created_at', $days, 'SUM(total_amount - COALESCE(hif_amount, 0))');
        $admissionDays = self::lastDays(13);

        $now = now();
        $d13 = $now->copy()->subYears(13)->toDateString();
        $d21 = $now->copy()->subYears(21)->toDateString();
        $d31 = $now->copy()->subYears(31)->toDateString();
        $d51 = $now->copy()->subYears(51)->toDateString();
        $age = Patient::whereDate('created_at', $today)->selectRaw('
                SUM(CASE WHEN dob > ? THEN 1 ELSE 0 END) as a1,
                SUM(CASE WHEN dob <= ? AND dob > ? THEN 1 ELSE 0 END) as a2,
                SUM(CASE WHEN dob <= ? AND dob > ? THEN 1 ELSE 0 END) as a3,
                SUM(CASE WHEN dob <= ? AND dob > ? THEN 1 ELSE 0 END) as a4,
                SUM(CASE WHEN dob <= ? THEN 1 ELSE 0 END) as a5
            ', [$d13, $d13, $d21, $d21, $d31, $d31, $d51, $d51])->first();

        $departments = DB::table('chits')
            ->join('departments', 'departments.id', '=', 'chits.department_id')
            ->whereNull('chits.deleted_at')
            ->whereDate('chits.issued_date', $today)
            ->selectRaw('departments.name, COUNT(*) as total')
            ->groupBy('departments.name')->orderByDesc('total')->limit(10)->pluck('total', 'name');

        $tests = DB::table('patient_tests')
            ->join('fee_types', 'fee_types.id', '=', 'patient_tests.fee_type_id')
            ->join('fee_categories', 'fee_categories.id', '=', 'fee_types.fee_category_id')
            ->whereNull('patient_tests.deleted_at')
            ->whereDate('patient_tests.created_at', $today)
            ->selectRaw('fee_categories.name, COUNT(*) as total')
            ->groupBy('fee_categories.name')->orderByDesc('total')->limit(8)->pluck('total', 'name');

        $byUser = [];
        foreach (DB::table('chits')->whereNull('deleted_at')->whereDate('issued_date', $today)
            ->selectRaw('user_id, COUNT(*) as n, SUM(amount - COALESCE(amount_hif, 0)) as govt')->groupBy('user_id')->get() as $row) {
            $byUser[$row->user_id]['chits'] = (int) $row->n;
            $byUser[$row->user_id]['govt'] = ($byUser[$row->user_id]['govt'] ?? 0) + (float) $row->govt;
        }
        foreach (DB::table('invoices')->whereNull('deleted_at')->whereDate('created_at', $today)
            ->selectRaw('user_id, COUNT(*) as n, SUM(total_amount - COALESCE(hif_amount, 0)) as govt')->groupBy('user_id')->get() as $row) {
            $byUser[$row->user_id]['invoices'] = (int) $row->n;
            $byUser[$row->user_id]['govt'] = ($byUser[$row->user_id]['govt'] ?? 0) + (float) $row->govt;
        }
        $names = User::whereIn('id', array_keys($byUser))->pluck('name', 'id');
        $team = collect($byUser)->map(fn ($v, $id) => [
            'name' => $names[$id] ?? 'User #'.$id,
            'chits' => $v['chits'] ?? 0,
            'invoices' => $v['invoices'] ?? 0,
            'govt' => $v['govt'] ?? 0,
        ])->sortByDesc('govt')->values()->all();

        return [
            'chits' => (clone $chitsToday)->count(),
            'chits_yesterday' => Chit::whereDate('issued_date', $yesterday)->count(),
            'invoices' => (clone $invoicesToday)->count(),
            'invoices_yesterday' => Invoice::whereDate('created_at', $yesterday)->count(),
            'patients' => Patient::whereDate('created_at', $today)->count(),
            'admissions' => Admission::whereDate('created_at', $today)->count(),
            'entitled' => (clone $chitsToday)->where('government_non_gov', 1)->count(),
            'non_entitled' => (clone $chitsToday)->where('government_non_gov', 0)->count(),
            'collected' => $chitRevenue + $invoiceRevenue,
            'govt' => ($chitRevenue - $chitHif) + ($invoiceRevenue - $invoiceHif),
            'hif' => $chitHif + $invoiceHif,
            'days' => array_values(array_map(fn (Carbon $d) => $d->format('d M'), $days)),
            'chit_series' => array_values($chitCount),
            'govt_series' => array_values(array_map(fn ($k) => round($chitGovt[$k] + $invoiceGovt[$k], 2), array_keys($chitCount))),
            'admission_labels' => array_values(array_map(fn (Carbon $d) => $d->format('d/m'), $admissionDays)),
            'admission_series' => array_values(self::perDay(Admission::query(), 'created_at', $admissionDays, 'COUNT(*)')),
            'gender' => ['Male' => Patient::where('sex', 1)->whereDate('created_at', $today)->count(), 'Female' => Patient::where('sex', 0)->whereDate('created_at', $today)->count()],
            'age' => ['0-12' => (int) $age->a1, '13-20' => (int) $age->a2, '21-30' => (int) $age->a3, '31-50' => (int) $age->a4, '50+' => (int) $age->a5],
            'departments' => $departments->all(),
            'tests' => $tests->all(),
            'team' => $team,
        ];
    }

    /** @return array<string, Carbon> keyed by Y-m-d, oldest first */
    private static function lastDays(int $count): array
    {
        $days = [];
        for ($i = $count - 1; $i >= 0; $i--) {
            $d = Carbon::today()->subDays($i);
            $days[$d->toDateString()] = $d;
        }

        return $days;
    }

    /**
     * One value per day (0 for quiet days) for a model query.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  array<string, Carbon>  $days
     * @return array<string, float|int>
     */
    private static function perDay($query, string $column, array $days, string $aggregate): array
    {
        $start = array_key_first($days);
        $rows = $query->whereDate($column, '>=', $start)
            ->selectRaw("CAST({$column} AS DATE) as d, {$aggregate} as v")
            ->groupBy('d')->pluck('v', 'd');

        $out = [];
        foreach (array_keys($days) as $key) {
            $out[$key] = (float) ($rows[$key] ?? 0);
        }

        return $out;
    }
}
