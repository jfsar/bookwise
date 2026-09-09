<?php

use App\Http\Livewire\Accounts\AccountList;
use App\Http\Livewire\FiscalYear\FiscalYearList;
use App\Http\Livewire\Journal\JournalList;
use App\Http\Livewire\JournalEntry\JournalEntryList;
use App\Http\Livewire\PeriodClosing\PeriodClosingList;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');

    Route::get('/accounts', AccountList::class)->name('accounts.index');
    Route::get('/fiscal-years', FiscalYearList::class)->name('fiscal-years.index');
    Route::get('/journals', JournalList::class)->name('journals.index');
    Route::get('/journal-entries', JournalEntryList::class)->name('journal-entries.index');
    Route::get('/period-closing', PeriodClosingList::class)->name('period-closing.index');
});
