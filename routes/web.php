<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Contracts\Role;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/


Route::get('/', function () {
//    Role::create(['name' => 'Auditor','guard_name' => 'sanctum']);
    return to_route('login');
});

Route::middleware(['auth:sanctum', config('jetstream.auth_session'), 'verified'])->group(function () {

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::resource('roles',\App\Http\Controllers\RoleController::class);
    Route::resource('permissions',\App\Http\Controllers\PermissionController::class);

    Route::resource('roles',\App\Http\Controllers\RoleController::class);
    Route::resource('permissions',\App\Http\Controllers\PermissionController::class);
    Route::get('/users', [UserController::class, 'index'])->name('users.index');
    Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
    Route::post('/users', [UserController::class, 'store'])->name('users.store');


    Route::resource('department', \App\Http\Controllers\DepartmentController::class);
    Route::resource('governmentDepartment', \App\Http\Controllers\GovernmentDepartmentController::class);

    Route::resource('labTest', \App\Http\Controllers\LabTestController::class);

    Route::get('patient/{patient}/proceed', [\App\Http\Controllers\PatientController::class, 'proceed'])->name('patient.proceed');
    Route::post('patient/{patient}/add-to-cart', [\App\Http\Controllers\PatientController::class, 'add_to_cart'])->name('patient.add-to-cart');
    Route::delete('patient/{patientTestCart}', [\App\Http\Controllers\PatientController::class, 'proceed_cart_destroy'])->name('patient_cart.destroy');
    Route::post('patient/{patient}/generateInvoice', [\App\Http\Controllers\PatientController::class, 'proceed_to_invoice'])->name('patient.proceed_to_invoice');
    Route::get('patient/{patient}/{invoice}/show', [\App\Http\Controllers\PatientController::class, 'patient_invoice'])->name('patient.patient_invoice');
    Route::get('patient/{patient}/{invoice}/show/thermal-print', [\App\Http\Controllers\PatientController::class, 'patient_invoice_thermal_print'])->name('patient.patient_invoice_thermal_print');


    Route::get('patient/{patient}/history', [\App\Http\Controllers\PatientController::class, 'patient_history'])->name('patient.history');
    Route::post('patient/invoice', [\App\Http\Controllers\PatientController::class, 'patient_test_invoice_generate'])->name('patient.patient_test_invoice_generate');

    Route::resource('patient', \App\Http\Controllers\PatientController::class);
    Route::get('patient/ipd/create', [\App\Http\Controllers\PatientController::class, 'createIPD'])->name('patient.create-ipd');
    Route::get('patient/opd/create', [\App\Http\Controllers\PatientController::class, 'createOPD'])->name('patient.create-opd');
    Route::post('patient/opd', [\App\Http\Controllers\PatientController::class, 'storeOPD'])->name('patient.store-opd');
    Route::post('patient/ipd', [\App\Http\Controllers\PatientController::class, 'storeIPD'])->name('patient.store-ipd');


    Route::resource('feeType', \App\Http\Controllers\FeeTypeController::class);
    Route::get('patient/{patient}/chit/{chit}', [\App\Http\Controllers\ChitController::class, 'print'])->name('chit.print');
    Route::get('patient/{patient}/actions', [\App\Http\Controllers\PatientController::class, 'patient_actions'])->name('patient.actions');
    Route::get('patient/{patient}/issued-chits', [\App\Http\Controllers\ChitController::class, 'issued_chits'])->name('patient.issued-chits');
    Route::get('patient/{patient}/issued-invoices', [\App\Http\Controllers\ChitController::class, 'issued_invoices'])->name('patient.issued-invoices');
    Route::get('patient/{patient}/issue-new-chit', [\App\Http\Controllers\ChitController::class, 'issue_new_chit'])->name('patient.issue-new-chit');
    Route::post('patient/{patient}/issue-new-chit', [\App\Http\Controllers\ChitController::class, 'issue_new_chit_store'])->name('patient.issue-new-chitStore');

    Route::get('chits/issued-today', [\App\Http\Controllers\ChitController::class, 'today'])->name('chits.issued-today');
    Route::get('invoice/issued-today', [\App\Http\Controllers\InvoiceController::class, 'today'])->name('invoice.issued-today');
    Route::get('chits/issued', [\App\Http\Controllers\ChitController::class, 'issued'])->name('chits.issued');
    Route::get('invoice/issued', [\App\Http\Controllers\InvoiceController::class, 'issued'])->name('invoice.issued');


    // Reports

    Route::get('reports', [\App\Http\Controllers\ReportsController::class, 'index'])->name('reports.index');
    Route::get('reports/opd', [\App\Http\Controllers\ReportsController::class, 'opd'])->name('reports.opd');
    Route::get('reports/ipd', [\App\Http\Controllers\ReportsController::class, 'ipd'])->name('reports.ipd');
    Route::get('reports/opd/reports-daily', [\App\Http\Controllers\ReportsController::class, 'reportDaily'])->name('reports.opd.reportDaily');
    Route::get('reports/ipd/reports-daily', [\App\Http\Controllers\ReportsController::class, 'reportDailyIPD'])->name('reports.opd.reportDailyIPD');

    Route::get('reports/misc', [\App\Http\Controllers\ReportsController::class, 'reportMisc'])->name('reports.misc');
//    Route::get('reports/misc/category-wise', [\App\Http\Controllers\ReportsController::class, 'categoryWise'])->name('reports.misc.category-wise');
    Route::get('reports/misc/admission', [\App\Http\Controllers\ReportsController::class, 'admission'])->name('reports.misc.admission');


    Route::get('reports/misc/department-wise', [\App\Http\Controllers\ReportsController::class, 'department_wise'])->name('reports.misc.category-wise');
    Route::get('reports/misc/department-wise-two', [\App\Http\Controllers\ReportsController::class, 'department_wise_two'])->name('reports.misc.category-wise-two');


});
