<?php

namespace App\Http\Controllers;

use App\Actions\BankTransaction\ImportBankTransactions;
use App\Actions\Organization\SwitchOrganization;
use App\Http\Requests\StoreBankImportRequest;
use App\Models\BankImportBatch;
use Illuminate\Http\RedirectResponse;

class BankImportBatchController extends Controller
{
    public function store(StoreBankImportRequest $request): RedirectResponse
    {
        $user = $request->user();
        $organization = SwitchOrganization::current($user);

        if (! $organization) {
            return back()->with('error', 'No organization selected.');
        }

        $this->authorize('create', [BankImportBatch::class, $organization]);

        try {
            $batch = (new ImportBankTransactions)->handle($request->validated(), $organization, $user);

            if ($batch->status === 'completed') {
                return back()->with(
                    'success',
                    "Bank statement imported: {$batch->rows_imported} imported, {$batch->rows_skipped} duplicate(s)."
                );
            }

            return back()->with('error', 'Bank statement import failed. '.$batch->error_summary);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
