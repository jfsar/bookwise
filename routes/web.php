<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\BankAccountController;
use App\Http\Controllers\BankImportBatchController;
use App\Http\Controllers\BankReconciliationController;
use App\Http\Controllers\BankTransactionController;
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
use App\Http\Controllers\VendorBillController;
use App\Http\Controllers\VendorController;
use App\Http\Controllers\VendorDebitNoteController;
use App\Http\Controllers\VendorPaymentController;
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

    Route::get('/bills', [VendorBillController::class, 'index'])->name('bills.index');
    Route::post('/bills', [VendorBillController::class, 'store'])->name('bills.store')->middleware('password.confirm');
    Route::post('/bills/{vendorBill}/post', [VendorBillController::class, 'post'])->name('bills.post');
    Route::post('/bills/{vendorBill}/void', [VendorBillController::class, 'void'])->name('bills.void');

    Route::get('/vendor-payments', [VendorPaymentController::class, 'index'])->name('vendor-payments.index');
    Route::post('/vendor-payments', [VendorPaymentController::class, 'store'])->name('vendor-payments.store')->middleware('password.confirm');
    Route::post('/vendor-payments/{vendorPayment}/post', [VendorPaymentController::class, 'post'])->name('vendor-payments.post');
    Route::post('/vendor-payments/{vendorPayment}/allocate', [VendorPaymentController::class, 'allocate'])->name('vendor-payments.allocate');
    Route::post('/vendor-payments/{vendorPayment}/void', [VendorPaymentController::class, 'void'])->name('vendor-payments.void');

    Route::get('/vendor-debit-notes', [VendorDebitNoteController::class, 'index'])->name('vendor-debit-notes.index');
    Route::post('/vendor-debit-notes', [VendorDebitNoteController::class, 'store'])->name('vendor-debit-notes.store')->middleware('password.confirm');
    Route::post('/vendor-debit-notes/{vendorDebitNote}/post', [VendorDebitNoteController::class, 'post'])->name('vendor-debit-notes.post');
    Route::post('/vendor-debit-notes/{vendorDebitNote}/void', [VendorDebitNoteController::class, 'void'])->name('vendor-debit-notes.void');

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

    Route::get('/bank-accounts', [BankAccountController::class, 'index'])->name('bank-accounts.index');
    Route::post('/bank-accounts', [BankAccountController::class, 'store'])->name('bank-accounts.store')->middleware('password.confirm');
    Route::put('/bank-accounts/{bankAccount}', [BankAccountController::class, 'update'])->name('bank-accounts.update')->middleware('password.confirm');

    Route::get('/bank-transactions', [BankTransactionController::class, 'index'])->name('bank-transactions.index');
    Route::post('/bank-transactions', [BankTransactionController::class, 'store'])->name('bank-transactions.store');
    Route::put('/bank-transactions/{bankTransaction}', [BankTransactionController::class, 'update'])->name('bank-transactions.update');
    Route::post('/bank-imports', [BankImportBatchController::class, 'store'])->name('bank-import-batches.store');

    Route::get('/bank-reconciliations', [BankReconciliationController::class, 'index'])->name('reconciliations.index');
    Route::post('/bank-reconciliations', [BankReconciliationController::class, 'store'])->name('reconciliations.store');
    Route::get('/bank-reconciliations/{bankReconciliation}', [BankReconciliationController::class, 'show'])->name('reconciliations.show');
    Route::post('/bank-reconciliations/{bankReconciliation}/matches', [BankReconciliationController::class, 'match'])->name('reconciliations.match');
    Route::delete('/bank-reconciliations/{bankReconciliation}/matches/{reconciliationMatch}', [BankReconciliationController::class, 'destroyMatch'])->name('reconciliations.matches.destroy');
    Route::post('/bank-reconciliations/{bankReconciliation}/finalize', [BankReconciliationController::class, 'finalize'])->name('reconciliations.finalize')->middleware('password.confirm');
    Route::post('/bank-reconciliations/{bankReconciliation}/reopen', [BankReconciliationController::class, 'reopen'])->name('reconciliations.reopen')->middleware('password.confirm');

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
    Route::get('/reports/ap-aging', [ReportController::class, 'apAging'])->name('reports.ap-aging');
    Route::get('/reports/exports', [ReportController::class, 'exports'])->name('reports.exports');
    Route::post('/reports/export', [ReportController::class, 'store'])->name('reports.export')->middleware('password.confirm');
    Route::get('/reports/exports/{reportExport}', [ReportController::class, 'download'])->name('reports.download')->middleware('password.confirm');

    Route::post('/organizations', [OrganizationController::class, 'store'])->name('organizations.store');
    Route::post('/organizations/switch/{organization}', [OrganizationController::class, 'switch'])->name('organizations.switch');
});
