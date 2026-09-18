<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\CustomerCreditNoteController;
use App\Http\Controllers\CustomerInvoiceController;
use App\Http\Controllers\CustomerPaymentController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FiscalYearController;
use App\Http\Controllers\JournalController;
use App\Http\Controllers\JournalEntryController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\PeriodClosingController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SessionController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VendorController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/accounts', [AccountController::class, 'index'])->name('accounts.index');
    Route::post('/accounts', [AccountController::class, 'store'])->name('accounts.store')->middleware('password.confirm');
    Route::put('/accounts/{account}', [AccountController::class, 'update'])->name('accounts.update')->middleware('password.confirm');

    Route::get('/fiscal-years', [FiscalYearController::class, 'index'])->name('fiscal-years.index');
    Route::post('/fiscal-years', [FiscalYearController::class, 'store'])->name('fiscal-years.store')->middleware('password.confirm');

    Route::get('/journals', [JournalController::class, 'index'])->name('journals.index');
    Route::post('/journals', [JournalController::class, 'store'])->name('journals.store');

    Route::get('/journal-entries', [JournalEntryController::class, 'index'])->name('journal-entries.index');
    Route::post('/journal-entries', [JournalEntryController::class, 'store'])->name('journal-entries.store');
    Route::post('/journal-entries/{journalEntry}/submit', [JournalEntryController::class, 'submit'])->name('journal-entries.submit');
    Route::post('/journal-entries/{journalEntry}/approve', [JournalEntryController::class, 'approve'])->name('journal-entries.approve');
    Route::post('/journal-entries/{journalEntry}/reject', [JournalEntryController::class, 'reject'])->name('journal-entries.reject');
    Route::post('/journal-entries/{journalEntry}/post', [JournalEntryController::class, 'post'])->name('journal-entries.post');
    Route::post('/journal-entries/{journalEntry}/reverse', [JournalEntryController::class, 'reverse'])->name('journal-entries.reverse');
    Route::post('/journal-entries/{journalEntry}/cancel', [JournalEntryController::class, 'cancel'])->name('journal-entries.cancel');

    Route::get('/customers', [CustomerController::class, 'index'])->name('customers.index');
    Route::post('/customers', [CustomerController::class, 'store'])->name('customers.store')->middleware('password.confirm');
    Route::put('/customers/{customer}', [CustomerController::class, 'update'])->name('customers.update')->middleware('password.confirm');

    Route::get('/vendors', [VendorController::class, 'index'])->name('vendors.index');
    Route::post('/vendors', [VendorController::class, 'store'])->name('vendors.store')->middleware('password.confirm');
    Route::put('/vendors/{vendor}', [VendorController::class, 'update'])->name('vendors.update')->middleware('password.confirm');

    Route::get('/invoices', [CustomerInvoiceController::class, 'index'])->name('invoices.index');
    Route::post('/invoices', [CustomerInvoiceController::class, 'store'])->name('invoices.store')->middleware('password.confirm');
    Route::post('/invoices/{customerInvoice}/post', [CustomerInvoiceController::class, 'post'])->name('invoices.post');
    Route::post('/invoices/{customerInvoice}/void', [CustomerInvoiceController::class, 'void'])->name('invoices.void');

    Route::get('/payments', [CustomerPaymentController::class, 'index'])->name('payments.index');
    Route::post('/payments', [CustomerPaymentController::class, 'store'])->name('payments.store')->middleware('password.confirm');
    Route::post('/payments/{customerPayment}/allocate', [CustomerPaymentController::class, 'allocate'])->name('payments.allocate');
    Route::post('/payments/{customerPayment}/post', [CustomerPaymentController::class, 'post'])->name('payments.post');
    Route::post('/payments/{customerPayment}/void', [CustomerPaymentController::class, 'void'])->name('payments.void');

    Route::get('/credit-notes', [CustomerCreditNoteController::class, 'index'])->name('credit-notes.index');
    Route::post('/credit-notes', [CustomerCreditNoteController::class, 'store'])->name('credit-notes.store')->middleware('password.confirm');
    Route::post('/credit-notes/{customerCreditNote}/post', [CustomerCreditNoteController::class, 'post'])->name('credit-notes.post');
    Route::post('/credit-notes/{customerCreditNote}/void', [CustomerCreditNoteController::class, 'void'])->name('credit-notes.void');

    Route::get('/period-closing', [PeriodClosingController::class, 'index'])->name('period-closing.index');
    Route::post('/accounting-periods/{accountingPeriod}/close', [PeriodClosingController::class, 'close'])->name('accounting-periods.close')->middleware('password.confirm');
    Route::post('/accounting-periods/{accountingPeriod}/reopen', [PeriodClosingController::class, 'reopen'])->name('accounting-periods.reopen')->middleware('password.confirm');

    Route::get('/reports/general-ledger', [ReportController::class, 'generalLedger'])->name('reports.general-ledger');

    Route::get('/settings/sessions', [SessionController::class, 'index'])->name('settings.sessions.index');
    Route::delete('/settings/sessions/{session}', [SessionController::class, 'destroy'])->name('settings.sessions.destroy');
    Route::delete('/settings/sessions', [SessionController::class, 'destroyOthers'])->name('settings.sessions.destroy-others');

    Route::get('/settings/audit-logs', [AuditLogController::class, 'index'])->name('settings.audit-logs.index');

    Route::get('/settings/organization', [OrganizationController::class, 'index'])->name('settings.organization.index');
    Route::put('/organizations/{organization}', [OrganizationController::class, 'update'])->name('organizations.update')->middleware('password.confirm');

    Route::get('/settings/users', [UserController::class, 'index'])->name('settings.users.index');
    Route::post('/settings/users', [UserController::class, 'store'])->name('settings.users.store')->middleware('password.confirm');
    Route::put('/settings/users/{user}', [UserController::class, 'update'])->name('settings.users.update')->middleware('password.confirm');

    Route::get('/settings/roles', [RoleController::class, 'index'])->name('settings.roles.index');
    Route::post('/settings/roles', [RoleController::class, 'store'])->name('settings.roles.store')->middleware('password.confirm');
    Route::put('/settings/roles/{role}', [RoleController::class, 'update'])->name('settings.roles.update')->middleware('password.confirm');
    Route::delete('/settings/roles/{role}', [RoleController::class, 'destroy'])->name('settings.roles.destroy')->middleware('password.confirm');
    Route::get('/reports/trial-balance', [ReportController::class, 'trialBalance'])->name('reports.trial-balance');
    Route::get('/reports/income-statement', [ReportController::class, 'incomeStatement'])->name('reports.income-statement');
    Route::get('/reports/balance-sheet', [ReportController::class, 'balanceSheet'])->name('reports.balance-sheet');
    Route::get('/reports/cash-flow', [ReportController::class, 'cashFlow'])->name('reports.cash-flow');
    Route::get('/reports/ar-aging', [ReportController::class, 'arAging'])->name('reports.ar-aging');
    Route::get('/reports/exports', [ReportController::class, 'exports'])->name('reports.exports');
    Route::post('/reports/export', [ReportController::class, 'store'])->name('reports.export')->middleware('password.confirm');
    Route::get('/reports/exports/{reportExport}', [ReportController::class, 'download'])->name('reports.download')->middleware('password.confirm');

    Route::post('/organizations', [OrganizationController::class, 'store'])->name('organizations.store');
    Route::post('/organizations/switch/{organization}', [OrganizationController::class, 'switch'])->name('organizations.switch');
});
