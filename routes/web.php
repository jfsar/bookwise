<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FiscalYearController;
use App\Http\Controllers\JournalController;
use App\Http\Controllers\JournalEntryController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\PeriodClosingController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/accounts', [AccountController::class, 'index'])->name('accounts.index');
    Route::post('/accounts', [AccountController::class, 'store'])->name('accounts.store');
    Route::put('/accounts/{account}', [AccountController::class, 'update'])->name('accounts.update');

    Route::get('/fiscal-years', [FiscalYearController::class, 'index'])->name('fiscal-years.index');
    Route::post('/fiscal-years', [FiscalYearController::class, 'store'])->name('fiscal-years.store');

    Route::get('/journals', [JournalController::class, 'index'])->name('journals.index');
    Route::post('/journals', [JournalController::class, 'store'])->name('journals.store');

    Route::get('/journal-entries', [JournalEntryController::class, 'index'])->name('journal-entries.index');
    Route::post('/journal-entries', [JournalEntryController::class, 'store'])->name('journal-entries.store');
    Route::post('/journal-entries/{journalEntry}/submit', [JournalEntryController::class, 'submit'])->name('journal-entries.submit');
    Route::post('/journal-entries/{journalEntry}/approve', [JournalEntryController::class, 'approve'])->name('journal-entries.approve');
    Route::post('/journal-entries/{journalEntry}/post', [JournalEntryController::class, 'post'])->name('journal-entries.post');
    Route::post('/journal-entries/{journalEntry}/reverse', [JournalEntryController::class, 'reverse'])->name('journal-entries.reverse');
    Route::post('/journal-entries/{journalEntry}/cancel', [JournalEntryController::class, 'cancel'])->name('journal-entries.cancel');

    Route::get('/period-closing', [PeriodClosingController::class, 'index'])->name('period-closing.index');
    Route::post('/accounting-periods/{accountingPeriod}/close', [PeriodClosingController::class, 'close'])->name('accounting-periods.close');
    Route::post('/accounting-periods/{accountingPeriod}/reopen', [PeriodClosingController::class, 'reopen'])->name('accounting-periods.reopen');

    Route::post('/organizations', [OrganizationController::class, 'store'])->name('organizations.store');
    Route::post('/organizations/switch/{organization}', [OrganizationController::class, 'switch'])->name('organizations.switch');
});
