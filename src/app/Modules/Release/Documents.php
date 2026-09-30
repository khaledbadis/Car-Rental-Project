<?php

namespace App\Modules\Release;

use App\Models\FinancialAccount;
use App\Models\LedgerEntry;
use App\Models\Receipt;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class Documents
{
    public const RECEIPTABLE = ['payment', 'refund', 'deposit_received', 'deposit_applied', 'deposit_refunded', 'reversal'];

    // Called inside the ledger transaction, before any success is returned.
    public function issue(LedgerEntry $entry): ?Receipt
    {
        if ($entry->category === 'opening' || ! in_array($entry->kind, self::RECEIPTABLE)) {
            return null;
        }

        return DB::transaction(function () use ($entry) {
            DB::table('agency_settings')->where('id', 1)->lockForUpdate()->first();
            if ($existing = Receipt::where('ledger_entry_id', $entry->id)->first()) {
                return $existing;
            }
            $prefix = in_array($entry->kind, ['refund', 'deposit_refunded']) ? 'REF' : 'REC';
            $series = $prefix.'-'.now('Africa/Algiers')->format('Y');
            DB::table('receipt_sequences')->insertOrIgnore(['series' => $series, 'last_number' => 0]);
            $sequence = DB::table('receipt_sequences')->where('series', $series)->lockForUpdate()->first();
            $next = $sequence->last_number + 1;
            DB::table('receipt_sequences')->where('series', $series)->update(['last_number' => $next]);
            $account = FinancialAccount::findOrFail($entry->financial_account_id);

            return Receipt::create([
                'ledger_entry_id' => $entry->id, 'number' => $series.'-'.str_pad((string) $next, 6, '0', STR_PAD_LEFT),
                'snapshot' => ['agency' => config('contract.agency'), 'sample' => config('contract.sample'), 'entry' => $entry->fresh()->toArray(), 'customer' => $account->customer->only(['id', 'name']), 'rental_id' => $account->rental_id, 'reservation_id' => $account->reservation_id, 'actor_username' => User::findOrFail($entry->actor_id)->username, 'corrects' => $entry->reverses_id ? Receipt::where('ledger_entry_id', $entry->reverses_id)->value('number') : null],
                'created_at' => now(),
            ]);
        });
    }

    public function receipt(User $u, int $id): Receipt
    {
        Gate::forUser($u)->authorize('receipts.view');

        return Receipt::findOrFail($id);
    }

    public function contract(User $u, int $id, int $version): array
    {
        Gate::forUser($u)->authorize('contracts.view');
        $record = DB::table('rental_versions')->where('rental_id', $id)->where('number', $version)->first();
        abort_unless($record, 404);

        $snapshot = json_decode($record->snapshot, true);
        $snapshot['template'] ??= json_decode($record->legacy_template, true);

        return ['id' => $id, 'version' => $version, 'created_at' => $record->created_at, 'snapshot' => $snapshot];
    }
}
