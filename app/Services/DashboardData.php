<?php

namespace App\Services;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Numbers for the two dashboards. "Govt" is the government share of a payment
 * (amount - HIF), the same meaning it has in the reports. Date filters use
 * ranges (>= start, < end) so the indexes on the date columns stay usable.
 */
class DashboardData
{
    private const CHIT_GOVT = 'amount - COALESCE(amount_hif, 0)';

    private const INVOICE_GOVT = 'total_amount - COALESCE(hif_amount, 0)';

    /**
     * Management dashboard for a date range (default: today). Cached for a minute because it scans
     * months of data; only scalars and arrays are cached.
     */
    public static function admin(?Carbon $from = null, ?Carbon $to = null): array
    {
        $to = ($to ?? Carbon::today())->copy()->startOfDay();
        $from = ($from ?? $to)->copy()->startOfDay();
        if ($from->gt($to)) {
            [$from, $to] = [$to, $from];
        }
        if ($to->gt(Carbon::today())) {
            $to = Carbon::today();
        }
        if ($from->diffInDays($to) > 800) {
            $from = $to->copy()->subDays(800);
        }

        return Cache::remember('dashboard.admin.'.$from->toDateString().'.'.$to->toDateString().'.'.now()->format('YmdHi'), 60, fn () => self::buildAdmin($from, $to));
    }

    /** @return array<string, mixed> */
    private static function buildAdmin(Carbon $from, Carbon $to): array
    {
        $today = Carbon::today();
        $end = $to->copy()->addDay(); // exclusive upper bound
        $length = (int) $from->diffInDays($to) + 1;
        $prevEnd = $from->copy();
        $prevFrom = $from->copy()->subDays($length);
        $monthStart = $today->copy()->startOfMonth();
        $tomorrow = $today->copy()->addDay();

        $chit = fn (Carbon $a, Carbon $b) => DB::table('chits')->whereNull('chits.deleted_at')->where('chits.issued_date', '>=', $a)->where('chits.issued_date', '<', $b);
        $inv = fn (Carbon $a, Carbon $b) => DB::table('invoices')->whereNull('invoices.deleted_at')->where('invoices.created_at', '>=', $a)->where('invoices.created_at', '<', $b);
        $money = function (Carbon $a, Carbon $b) use ($chit, $inv) {
            $c = $chit($a, $b)->selectRaw('COUNT(*) n, COALESCE(SUM(amount),0) amt, COALESCE(SUM(amount_hif),0) hif, COALESCE(SUM(CASE WHEN government_non_gov THEN 1 ELSE 0 END),0) ent')->first();
            $i = $inv($a, $b)->selectRaw('COUNT(*) n, COALESCE(SUM(total_amount),0) amt, COALESCE(SUM(hif_amount),0) hif')->first();

            return [
                'chits' => (int) $c->n, 'invoices' => (int) $i->n, 'entitled' => (int) $c->ent,
                'collected' => (float) $c->amt + (float) $i->amt, 'hif' => (float) $c->hif + (float) $i->hif,
                'govt' => ((float) $c->amt - (float) $c->hif) + ((float) $i->amt - (float) $i->hif),
                'chit_govt' => (float) $c->amt - (float) $c->hif, 'invoice_govt' => (float) $i->amt - (float) $i->hif,
            ];
        };
        $count = fn (string $table, string $col, Carbon $a, Carbon $b) => DB::table($table)
            ->when(Schema::hasColumn($table, 'deleted_at'), fn ($q) => $q->whereNull('deleted_at'))->where($col, '>=', $a)->where($col, '<', $b)->count();

        $period = $money($from, $end);
        $previous = $money($prevFrom, $prevEnd);
        $period['patients'] = $count('patients', 'created_at', $from, $end);
        $previous['patients'] = $count('patients', 'created_at', $prevFrom, $prevEnd);
        $period['admissions'] = $count('admissions', 'created_at', $from, $end);
        $previous['admissions'] = $count('admissions', 'created_at', $prevFrom, $prevEnd);

        // ---- trend inside the period: daily up to ~3 months, monthly beyond ----
        $monthly = $length > 92;
        $bucket = $monthly ? "to_char(%s, 'YYYY-MM')" : 'CAST(%s AS DATE)';
        $keys = [];
        $labels = [];
        if ($monthly) {
            for ($d = $from->copy()->startOfMonth(); $d->lte($to); $d->addMonth()) {
                $keys[] = $d->format('Y-m');
                $labels[] = $d->format('M y');
            }
        } else {
            for ($d = $from->copy(); $d->lte($to); $d->addDay()) {
                $keys[] = $d->toDateString();
                $labels[] = $d->format('d M');
            }
        }
        $cRows = $chit($from, $end)->selectRaw(sprintf($bucket, 'chits.issued_date').' k, COUNT(*) n, SUM(chits.amount - COALESCE(chits.amount_hif,0)) g, SUM(COALESCE(chits.amount_hif,0)) h, SUM(CASE WHEN chits.government_non_gov THEN 1 ELSE 0 END) e')->groupBy('k')->get()->keyBy('k');
        $iRows = $inv($from, $end)->selectRaw(sprintf($bucket, 'invoices.created_at').' k, COUNT(*) n, SUM(invoices.total_amount - COALESCE(invoices.hif_amount,0)) g, SUM(COALESCE(invoices.hif_amount,0)) h')->groupBy('k')->get()->keyBy('k');
        $pRows = DB::table('patients')->whereNull('deleted_at')->where('created_at', '>=', $from)->where('created_at', '<', $end)->selectRaw(sprintf($bucket, 'created_at').' k, COUNT(*) n')->groupBy('k')->pluck('n', 'k');
        $aRows = DB::table('admissions')->where('created_at', '>=', $from)->where('created_at', '<', $end)->selectRaw(sprintf($bucket, 'created_at').' k, COUNT(*) n')->groupBy('k')->pluck('n', 'k');

        $series = ['chits' => [], 'invoices' => [], 'govt' => [], 'hif' => [], 'patients' => [], 'admissions' => [], 'entitled' => []];
        foreach ($keys as $k) {
            $series['chits'][] = (int) ($cRows[$k]->n ?? 0);
            $series['invoices'][] = (int) ($iRows[$k]->n ?? 0);
            $series['govt'][] = round((float) ($cRows[$k]->g ?? 0) + (float) ($iRows[$k]->g ?? 0));
            $series['hif'][] = round((float) ($cRows[$k]->h ?? 0) + (float) ($iRows[$k]->h ?? 0));
            $series['patients'][] = (int) ($pRows[$k] ?? 0);
            $series['admissions'][] = (int) ($aRows[$k] ?? 0);
            $series['entitled'][] = (int) ($cRows[$k]->e ?? 0);
        }
        $movingAverage = [];
        $window = $monthly ? 3 : 7;
        foreach ($series['chits'] as $i => $v) {
            $slice = array_slice($series['chits'], max(0, $i - $window + 1), min($window, $i + 1));
            $movingAverage[] = round(array_sum($slice) / max(1, count($slice)), 1);
        }

        // ---- distributions inside the period ----
        $departments = $chit($from, $end)->join('departments', 'departments.id', '=', 'chits.department_id')
            ->selectRaw('departments.name, COUNT(*) n, SUM(chits.amount - COALESCE(chits.amount_hif,0)) g')
            ->groupBy('departments.name')->orderByDesc('n')->limit(12)->get()->map(fn ($r) => (array) $r)->all();
        $govtDepartments = $chit($from, $end)->join('government_departments', 'government_departments.id', '=', 'chits.government_department_id')
            ->selectRaw('government_departments.name, COUNT(*) n')->groupBy('government_departments.name')->orderByDesc('n')->limit(8)->get()->map(fn ($r) => (array) $r)->all();

        $testBase = fn () => DB::table('patient_tests')->whereNull('patient_tests.deleted_at')
            ->where('patient_tests.created_at', '>=', $from)->where('patient_tests.created_at', '<', $end)
            ->join('fee_types', 'fee_types.id', '=', 'patient_tests.fee_type_id')
            ->join('fee_categories', 'fee_categories.id', '=', 'fee_types.fee_category_id');
        $govtExpr = 'SUM(patient_tests.total_amount - COALESCE(patient_tests.hif_amount,0))';
        $categories = $testBase()->selectRaw("fee_categories.name, COUNT(*) n, {$govtExpr} g")->groupBy('fee_categories.name')->orderByDesc('g')->limit(10)->get()->map(fn ($r) => (array) $r)->all();
        $services = $testBase()->selectRaw("fee_types.type name, COUNT(*) n, {$govtExpr} g")->groupBy('fee_types.type')->orderByDesc('g')->limit(10)->get()->map(fn ($r) => (array) $r)->all();
        $operationTheatre = $testBase()->whereIn('fee_types.id', [9, 10])->selectRaw('fee_types.type name, COUNT(*) n')->groupBy('fee_types.type')->orderByDesc('n')->get()->map(fn ($r) => (array) $r)->all();
        $radiology = $testBase()->whereIn('fee_types.id', [6, 7, 8])->selectRaw('fee_types.type name, COUNT(*) n')->groupBy('fee_types.type')->orderByDesc('n')->get()->map(fn ($r) => (array) $r)->all();

        $hours = array_fill(0, 24, 0);
        foreach ($chit($from, $end)->selectRaw('EXTRACT(HOUR FROM chits.issued_date)::int h, COUNT(*) n')->groupBy('h')->get() as $row) {
            $hours[(int) $row->h] = (int) $row->n;
        }
        $hourSlice = array_slice($hours, 7, 16, true);
        $peakHour = max($hourSlice) > 0 ? array_keys($hourSlice, max($hourSlice))[0] : 10;

        $weekdayTotals = array_fill(1, 7, 0);
        $weekdayDays = array_fill(1, 7, []);
        foreach ($chit($from, $end)->selectRaw('EXTRACT(ISODOW FROM chits.issued_date)::int w, CAST(chits.issued_date AS DATE) d, COUNT(*) n')->groupBy('w', 'd')->get() as $row) {
            $weekdayTotals[(int) $row->w] += (int) $row->n;
            $weekdayDays[(int) $row->w][$row->d] = true;
        }
        $weekday = [];
        foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $i => $label) {
            $days = count($weekdayDays[$i + 1]);
            $weekday[$label] = $days ? (int) round($weekdayTotals[$i + 1] / $days) : 0;
        }

        $gender = ['Male' => 0, 'Female' => 0];
        $age = array_fill_keys(['0-12', '13-20', '21-30', '31-50', '50+'], 0);
        $pt = DB::table('patients')->whereNull('deleted_at')->where('created_at', '>=', $from)->where('created_at', '<', $end);
        $gender['Male'] = (clone $pt)->where('sex', 1)->count();
        $gender['Female'] = (clone $pt)->where('sex', 0)->count();
        $cut = fn (int $y) => $to->copy()->subYears($y)->toDateString();
        $ar = (clone $pt)->selectRaw('
            COALESCE(SUM(CASE WHEN dob > ? THEN 1 ELSE 0 END),0) a1,
            COALESCE(SUM(CASE WHEN dob <= ? AND dob > ? THEN 1 ELSE 0 END),0) a2,
            COALESCE(SUM(CASE WHEN dob <= ? AND dob > ? THEN 1 ELSE 0 END),0) a3,
            COALESCE(SUM(CASE WHEN dob <= ? AND dob > ? THEN 1 ELSE 0 END),0) a4,
            COALESCE(SUM(CASE WHEN dob <= ? THEN 1 ELSE 0 END),0) a5', [$cut(13), $cut(13), $cut(21), $cut(21), $cut(31), $cut(31), $cut(51), $cut(51)])->first();
        $age = ['0-12' => (int) $ar->a1, '13-20' => (int) $ar->a2, '21-30' => (int) $ar->a3, '31-50' => (int) $ar->a4, '50+' => (int) $ar->a5];

        $wards = DB::table('admissions')->where('created_at', '>=', $from)->where('created_at', '<', $end)->whereNotNull('unit_ward')
            ->selectRaw('unit_ward name, COUNT(*) n')->groupBy('unit_ward')->orderByDesc('n')->limit(8)->get()->map(fn ($r) => (array) $r)->all();

        // ---- always-on context: last 12 months, 90-day heat map, anomalies, month projection ----
        $months = [];
        for ($m = 11; $m >= 0; $m--) {
            $d = $monthStart->copy()->subMonths($m);
            $months[$d->format('Y-m')] = $d;
        }
        $firstMonth = Carbon::parse(array_key_first($months).'-01');
        $chitMonthly = $chit($firstMonth, $tomorrow)->selectRaw("to_char(chits.issued_date, 'YYYY-MM') m, COUNT(*) n, SUM(chits.amount - COALESCE(chits.amount_hif,0)) g, SUM(COALESCE(chits.amount_hif,0)) h")->groupBy('m')->get()->keyBy('m');
        $invMonthly = $inv($firstMonth, $tomorrow)->selectRaw("to_char(invoices.created_at, 'YYYY-MM') m, SUM(invoices.total_amount - COALESCE(invoices.hif_amount,0)) g, SUM(COALESCE(invoices.hif_amount,0)) h")->groupBy('m')->get()->keyBy('m');

        $heatStart = $to->copy()->subDays(89);
        $heat = array_fill(1, 7, array_fill(8, 14, 0)); // ISO weekday x 08..21
        foreach ($chit($heatStart, $end)->selectRaw('EXTRACT(ISODOW FROM chits.issued_date)::int w, EXTRACT(HOUR FROM chits.issued_date)::int h, COUNT(*) n')->groupBy('w', 'h')->get() as $row) {
            if (isset($heat[(int) $row->w][(int) $row->h])) {
                $heat[(int) $row->w][(int) $row->h] = (int) $row->n;
            }
        }
        $heatSeries = [];
        foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $i => $label) {
            $heatSeries[] = ['name' => $label, 'data' => array_map(fn ($h, $n) => ['x' => sprintf('%02d', $h), 'y' => $n], array_keys($heat[$i + 1]), array_values($heat[$i + 1]))];
        }

        // days that were unusually busy or quiet (|z| > 2) in the 90 days up to the end of the period
        $daily = $chit($heatStart, $end)->selectRaw('CAST(chits.issued_date AS DATE) d, COUNT(*) n')->groupBy('d')->pluck('n', 'd')->map(fn ($n) => (int) $n)->all();
        $anomalies = [];
        if (count($daily) >= 14) {
            $mean = array_sum($daily) / count($daily);
            $std = sqrt(array_sum(array_map(fn ($v) => ($v - $mean) ** 2, $daily)) / count($daily));
            if ($std > 0) {
                foreach ($daily as $d => $n) {
                    $z = ($n - $mean) / $std;
                    if (abs($z) >= 2) {
                        $anomalies[] = ['date' => Carbon::parse($d)->format('D d M'), 'chits' => $n, 'z' => round($z, 1), 'kind' => $z > 0 ? 'busy' : 'quiet'];
                    }
                }
                usort($anomalies, fn ($a, $b) => abs($b['z']) <=> abs($a['z']));
                $anomalies = array_slice($anomalies, 0, 6);
            }
        }
        $dailyMean = $daily ? (int) round(array_sum($daily) / count($daily)) : 0;

        $mtd = $money($monthStart, $tomorrow);
        $elapsed = max(1, $today->day);
        $daysInMonth = $today->daysInMonth;
        $projection = [
            'elapsed' => $elapsed, 'days' => $daysInMonth,
            'chits' => (int) round($mtd['chits'] / $elapsed * $daysInMonth),
            'govt' => round($mtd['govt'] / $elapsed * $daysInMonth),
            'mtd_chits' => $mtd['chits'], 'mtd_govt' => $mtd['govt'],
        ];

        return [
            'from' => $from->toDateString(), 'to' => $to->toDateString(), 'length' => $length, 'monthly' => $monthly,
            'prev_from' => $prevFrom->toDateString(), 'prev_to' => $prevEnd->copy()->subDay()->toDateString(),
            'period' => $period, 'previous' => $previous,
            'labels' => $labels, 'series' => $series, 'moving_average' => $movingAverage, 'window' => $window,
            'departments' => $departments, 'govt_departments' => $govtDepartments,
            'categories' => $categories, 'services' => $services, 'operation_theatre' => $operationTheatre, 'radiology' => $radiology,
            'hours' => array_values($hourSlice), 'hour_labels' => array_map(fn ($h) => sprintf('%02d:00', $h), array_keys($hourSlice)), 'peak_hour' => $peakHour,
            'weekday' => $weekday, 'gender' => $gender, 'age' => $age, 'wards' => $wards,
            'months' => array_values(array_map(fn (Carbon $d) => $d->format('M y'), $months)),
            'chits_monthly' => array_values(array_map(fn ($k) => (int) ($chitMonthly[$k]->n ?? 0), array_keys($months))),
            'govt_monthly' => array_values(array_map(fn ($k) => round((float) ($chitMonthly[$k]->g ?? 0) + (float) ($invMonthly[$k]->g ?? 0)), array_keys($months))),
            'hif_monthly' => array_values(array_map(fn ($k) => round((float) ($chitMonthly[$k]->h ?? 0) + (float) ($invMonthly[$k]->h ?? 0)), array_keys($months))),
            'heat' => $heatSeries, 'heat_from' => $heatStart->format('d M Y'),
            'anomalies' => $anomalies, 'daily_mean' => $dailyMean, 'projection' => $projection,
            'team' => self::team($from, $end),
            'last_chit_at' => ($last = DB::table('chits')->whereNull('deleted_at')->max('issued_date')) ? Carbon::parse($last)->toIso8601String() : null,
        ];
    }

    /** Collection per user for the chosen period. */
    private static function team(Carbon $from, Carbon $end): array
    {
        $rows = [];
        $add = function ($table, $dateCol, $govtExpr, $key) use (&$rows, $from, $end) {
            foreach (DB::table($table)->whereNull('deleted_at')->where($dateCol, '>=', $from)->where($dateCol, '<', $end)
                ->selectRaw("user_id, COUNT(*) n, SUM({$govtExpr}) g")->groupBy('user_id')->get() as $r) {
                $rows[$r->user_id][$key] = (int) $r->n;
                $rows[$r->user_id]['govt'] = ($rows[$r->user_id]['govt'] ?? 0) + (float) $r->g;
            }
        };
        $add('chits', 'issued_date', self::CHIT_GOVT, 'chits');
        $add('invoices', 'created_at', self::INVOICE_GOVT, 'invoices');

        $names = User::whereIn('id', array_keys($rows))->pluck('name', 'id');
        $total = max(1.0, (float) collect($rows)->sum('govt'));

        return collect($rows)->map(fn ($v, $id) => [
            'name' => $names[$id] ?? 'User #'.$id,
            'chits' => $v['chits'] ?? 0,
            'invoices' => $v['invoices'] ?? 0,
            'govt' => round($v['govt'] ?? 0),
            'share' => round(($v['govt'] ?? 0) / $total * 100, 1),
        ])->sortByDesc('govt')->values()->all();
    }

    /** @return array<string, mixed> */
    public static function frontDesk(User $user): array
    {
        $today = Carbon::today();
        $tomorrow = $today->copy()->addDay();
        $yesterday = $today->copy()->subDay();
        $monthStart = $today->copy()->startOfMonth();

        $chit = fn (Carbon $from, Carbon $to) => DB::table('chits')->whereNull('chits.deleted_at')->where('chits.user_id', $user->id)->where('chits.issued_date', '>=', $from)->where('chits.issued_date', '<', $to);
        $inv = fn (Carbon $from, Carbon $to) => DB::table('invoices')->whereNull('deleted_at')->where('user_id', $user->id)->where('created_at', '>=', $from)->where('created_at', '<', $to);
        $sum = function (Carbon $from, Carbon $to) use ($chit, $inv) {
            $c = $chit($from, $to)->selectRaw('COUNT(*) n, COALESCE(SUM(amount),0) amt, COALESCE(SUM(amount_hif),0) hif, COALESCE(SUM(CASE WHEN government_non_gov THEN 1 ELSE 0 END),0) ent')->first();
            $i = $inv($from, $to)->selectRaw('COUNT(*) n, COALESCE(SUM(total_amount),0) amt, COALESCE(SUM(hif_amount),0) hif')->first();

            return [
                'chits' => (int) $c->n, 'invoices' => (int) $i->n, 'entitled' => (int) $c->ent, 'non_entitled' => (int) $c->n - (int) $c->ent,
                'collected' => (float) $c->amt + (float) $i->amt, 'hif' => (float) $c->hif + (float) $i->hif,
                'govt' => ((float) $c->amt - (float) $c->hif) + ((float) $i->amt - (float) $i->hif),
            ];
        };

        $now = $sum($today, $tomorrow);
        $prev = $sum($yesterday, $today);
        $month = $sum($monthStart, $tomorrow);
        $workedDays = DB::table('chits')->whereNull('deleted_at')->where('user_id', $user->id)->where('issued_date', '>=', $monthStart)->where('issued_date', '<', $tomorrow)
            ->selectRaw('COUNT(DISTINCT CAST(issued_date AS DATE)) d')->value('d');

        $days = self::lastDays(7);
        $start = Carbon::parse(array_key_first($days));
        $chitDaily = $chit($start, $tomorrow)->selectRaw('CAST(issued_date AS DATE) d, COUNT(*) n')->groupBy('d')->pluck('n', 'd');
        $invDaily = $inv($start, $tomorrow)->selectRaw('CAST(created_at AS DATE) d, COUNT(*) n')->groupBy('d')->pluck('n', 'd');

        $departments = $chit($today, $tomorrow)
            ->join('departments', 'departments.id', '=', 'chits.department_id')
            ->selectRaw('departments.name, COUNT(*) n')->groupBy('departments.name')->orderByDesc('n')->limit(6)->get();

        $hours = array_fill(8, 15, 0); // 08:00 - 22:00
        foreach ($chit($today, $tomorrow)->selectRaw('EXTRACT(HOUR FROM issued_date)::int h, COUNT(*) n')->groupBy('h')->get() as $row) {
            if (isset($hours[(int) $row->h])) {
                $hours[(int) $row->h] = (int) $row->n;
            }
        }

        $registered = DB::table('patients')->whereNull('deleted_at')->where('user_id', $user->id)->where('created_at', '>=', $today)->where('created_at', '<', $tomorrow)->count();
        $last = $chit($today, $tomorrow)->max('issued_date');

        return [
            'now' => $now, 'prev' => $prev, 'month' => $month,
            'month_daily_avg' => $workedDays ? (int) round($month['chits'] / $workedDays) : 0,
            'worked_days' => (int) $workedDays,
            'registered' => $registered,
            'last_chit_at' => $last ? Carbon::parse($last) : null,
            'week_labels' => array_values(array_map(fn (Carbon $d) => $d->format('D d'), $days)),
            'week_chits' => array_values(array_map(fn ($k) => (int) ($chitDaily[$k] ?? 0), array_keys($days))),
            'week_invoices' => array_values(array_map(fn ($k) => (int) ($invDaily[$k] ?? 0), array_keys($days))),
            'departments' => $departments,
            'hours' => array_values($hours),
            'hour_labels' => array_map(fn ($h) => sprintf('%02d', $h), array_keys($hours)),
            'recent_chits' => \App\Models\Chit::where('user_id', $user->id)->with(['patient:id,title,first_name,last_name', 'department:id,name'])
                ->where('issued_date', '>=', $today)->where('issued_date', '<', $tomorrow)->latest('issued_date')->limit(8)->get(),
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
}
