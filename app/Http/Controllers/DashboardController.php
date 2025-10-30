<?php

namespace App\Http\Controllers;

use App\Models\Admission;
use App\Models\Chit;
use App\Models\Department;
use App\Models\FeeCategory;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\PatientTest;
use Carbon\Carbon;
use DB;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    //
    public function index()
    {


        $hif_amount_today = 0;
        $government_amount_today = 0;
        $issued_chits = 0;
        $issued_invoices_revenue = 0;
        $issued_invoices = 0;
        $today_revenue = 0;
        $non_entitled = 0;
        $entitled = 0;
        $hif_today = 0;
        $government_today = 0;
        $user = \Auth::user();
        // reports variables
        $gender_wise = ['Male' => 0, 'Female' => 0];
        $age_group_wise_data = ['0-12' => 0, '13-20' => 0, '20-30' => 0, '30-50' => 0, '50-90+' => 0,];
        $opd_department_wise = array_fill_keys(Department::pluck('name')->toArray(), 0);
        $admission_weekly_report = [];
        $patient_test_daily_report = [];
        $patient_test_daily_report_op = [];
        $patient_test_daily_report_rd = [];


        for ($i = 12; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('d/m');
            $admission_weekly_report[$date] = 0;
        }

        foreach (FeeCategory::whereIn('id',[8,9,10,11,12])->get() as $fc) {
            $patient_test_daily_report[$fc->id] = 0;
        }

        foreach (FeeCategory::whereIn('id',[9,10])->get() as $fc) {
            $patient_test_daily_report_op[$fc->id] = 0;
        }

        foreach (FeeCategory::whereIn('id',[6,7,8])->get() as $fc) {
            $patient_test_daily_report_rd[$fc->id] = 0;
        }




        // OPD Front Desk
        if ($user->hasRole('Front Desk/Receptionist')) {
            $issued_chits = Chit::where('user_id', $user->id)->whereDate('issued_date', Carbon::today())->count();
            $today_revenue = Chit::where('user_id', $user->id)->whereDate('issued_date', Carbon::today())->sum('amount');
            $non_entitled = Chit::where('user_id', $user->id)->whereDate('issued_date', Carbon::today())->where('government_non_gov', 0)->count();
            $entitled = Chit::where('user_id', $user->id)->whereDate('issued_date', Carbon::today())->where('government_non_gov', 1)->count();
            $issued_invoices = Invoice::where('user_id', $user->id)->whereDate('created_at', Carbon::today())->count();

            $issued_invoices_revenue = Invoice::where('user_id', $user->id)->whereDate('created_at', Carbon::today())->sum('total_amount');


        } elseif ($user->hasRole(['Administrator'])) {

            $issued_chits = Chit::whereDate('issued_date', Carbon::today())->count();
            $today_revenue = Chit::whereDate('issued_date', Carbon::today())->sum('amount');
            $non_entitled = Chit::whereDate('issued_date', Carbon::today())->where('government_non_gov', 0)->count();
            $entitled = Chit::whereDate('issued_date', Carbon::today())->where('government_non_gov', 1)->count();
            $issued_invoices = Invoice::whereDate('created_at', Carbon::today())->count();
            $issued_invoices_revenue = Invoice::whereDate('created_at', Carbon::today())->sum('total_amount');

            $hif_amount_today = Invoice::whereDate('created_at', Carbon::today())->sum('hif_amount') + Chit::whereDate('created_at', Carbon::today())->sum('amount_hif');
            $government_amount_today = (Invoice::whereDate('created_at', Carbon::today())->sum('total_amount') + Chit::whereDate('created_at', Carbon::today())->sum('amount')) - $hif_amount_today;


            // charts reports
            $gender_wise = ['Male' => Patient::where('sex', 1)->whereDate('created_at', Carbon::today())->count(), 'Female' => Patient::where('sex', 0)->whereDate('created_at', Carbon::today())->count()];
            $age_group_wise_data = Patient::selectRaw('
                SUM(IF(TIMESTAMPDIFF(YEAR, dob, NOW()) <= 12, 1, 0)) AS `0-12`,
                SUM(IF(TIMESTAMPDIFF(YEAR, dob, NOW()) BETWEEN 13 AND 20, 1, 0)) AS `13-20`,
                SUM(IF(TIMESTAMPDIFF(YEAR, dob, NOW()) BETWEEN 21 AND 30, 1, 0)) AS `20-30`,
                SUM(IF(TIMESTAMPDIFF(YEAR, dob, NOW()) BETWEEN 31 AND 50, 1, 0)) AS `30-50`,
                SUM(IF(TIMESTAMPDIFF(YEAR, dob, NOW()) > 50, 1, 0)) AS `50-90+`
            ')->whereDate('created_at', today())->first()->toArray();
            foreach ($age_group_wise_data as $key => $value) {
                $age_group_wise[$key] = (int) $value;
            }

            foreach ($opd_department_wise as $key => $value) {
                $id = Department::where('name', $key)->first()->id;
                $opd_department_wise[$key] = Chit::where('department_id',$id)->whereDate('issued_date', Carbon::today())->count();
            }


            $admissions_data = Admission::select(DB::raw("DATE_FORMAT(created_at, '%d/%m') AS Date"), DB::raw("COUNT(*) AS Count"))
                ->where('created_at', '>=', DB::raw('NOW() - INTERVAL 12 DAY'))
                ->where('status', '=', 'No')
                ->groupBy(DB::raw("DATE_FORMAT(created_at, '%d/%m')"))
                ->orderBy('Date', 'DESC')
                ->get();

            foreach ($admissions_data as $item) {
                $admission_weekly_report[$item->Date] = $item->Count;
            }


            $pt = DB::table('patient_tests')
                ->select('fee_types.fee_category_id', DB::raw('count(patient_tests.fee_type_id) as total'))
                ->join('fee_types', 'patient_tests.fee_type_id', '=', 'fee_types.id')
                ->whereDate('patient_tests.created_at', Carbon::today())
                ->whereIn('fee_types.fee_category_id',[8,9,10,11,12])
                ->groupBy('fee_types.fee_category_id')
                ->orderBy('fee_types.fee_category_id', 'ASC')
                ->get();
            foreach ($pt as $item) {
                $patient_test_daily_report[$item->fee_category_id] = $item->total;
            }


            $op = DB::table('patient_tests')
                ->select('fee_type_id', DB::raw('COUNT(*) AS total'))
                ->whereDate('patient_tests.created_at', Carbon::today())
                ->whereIn('fee_type_id', [9, 10])
                ->groupBy('fee_type_id')
                ->get();

            $rd = DB::table('patient_tests')
                ->select('fee_type_id', DB::raw('COUNT(*) AS total'))
                ->whereDate('patient_tests.created_at', Carbon::today())
                ->whereIn('fee_type_id', [6,7,8])
                ->groupBy('fee_type_id')
                ->get();


            foreach ($op as $item) {
                $patient_test_daily_report_op[$item->fee_type_id] = $item->total;
            }

            foreach ($rd as $item) {
                $patient_test_daily_report_rd[$item->fee_type_id] = $item->total;
            }


        }
        return view('dashboard', compact('issued_chits', 'today_revenue', 'non_entitled', 'entitled','issued_invoices','issued_invoices_revenue',
            'gender_wise',
            'age_group_wise_data',
            'opd_department_wise',
            'admission_weekly_report',
            'patient_test_daily_report',
            'government_amount_today',
            'hif_amount_today',
            'patient_test_daily_report_op',
            'patient_test_daily_report_rd',
        ));

    }
}
