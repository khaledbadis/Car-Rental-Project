<?php

namespace App\Modules\Rentals;

use App\Models\FinancialAccount;
use App\Models\LedgerEntry;
use App\Models\Rental;
use App\Models\Reservation;
use App\Models\User;
use App\Modules\Foundation\Actions as Audit;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

class Ledger
{
    public const KINDS = ['charge', 'payment', 'refund', 'deposit_received', 'deposit_applied', 'deposit_refunded', 'reversal'];

    public const METHODS = ['cash', 'bank_transfer', 'terminal', 'cheque', 'other'];

    public function account(string $kind, int $id): FinancialAccount
    {
        abort_unless(in_array($kind, ['rental', 'reservation']), 404);
        $owner = ($kind === 'rental' ? Rental::class : Reservation::class)::findOrFail($id);

        return FinancialAccount::firstOrCreate([$kind.'_id' => $id], ['customer_id' => $owner->customer_id]);
    }

    public function read(User $u, string $kind, int $id): array
    {
        Gate::forUser($u)->authorize('rentals.view');
        abort_unless(in_array($kind, ['rental', 'reservation']), 404);
        ($kind === 'rental' ? Rental::class : Reservation::class)::findOrFail($id);
        $account = FinancialAccount::where($kind.'_id', $id)->first();

        return ['account' => $account, 'totals' => $this->totals($account), 'entries' => $account ? $account->entries()->orderByDesc('id')->get() : collect()];
    }

    public function totals(?FinancialAccount $account): array
    {
        $charge = 0;
        $paid = 0;
        $deposit = 0;
        $cash = 0;
        foreach ($account?->entries()->get() ?? [] as $e) {
            $charge += (int) $e->charge_delta;
            $paid += (int) $e->payment_delta;
            $deposit += (int) $e->deposit_delta;
            $kind = $e->kind === 'reversal' ? LedgerEntry::find($e->reverses_id)->kind : $e->kind;
            if (in_array($kind, ['payment', 'refund'])) {
                $cash += (int) $e->payment_delta;
            }
        }

        return ['charges_cents' => $charge, 'payments_cents' => $paid, 'held_cents' => $deposit, 'cash_payments_cents' => $cash, 'outstanding_cents' => max(0, $charge - $paid), 'credit_cents' => max(0, $paid - $charge)];
    }

    private function append(User $u, FinancialAccount $account, array $v): LedgerEntry
    {
        $e = LedgerEntry::create($v + ['financial_account_id' => $account->id, 'actor_id' => $u->id, 'created_at' => now()]);
        $t = $this->totals($account);
        foreach (['charges_cents', 'payments_cents', 'held_cents', 'cash_payments_cents'] as $f) {
            if ($t[$f] < 0) {
                throw new Conflict('dependent_entries');
            }
        }
        app(Audit::class)->audit($u, 'ledger.'.$e->kind, 'ledger_entry', $e->id, null, $e->only(['financial_account_id', 'kind', 'amount_cents', 'charge_delta', 'payment_delta', 'deposit_delta', 'reverses_id', 'method', 'effective_at']), $e->reason);

        return $e;
    }

    public function base(User $u, FinancialAccount $account, int $amount, string $reason): void
    {
        $old = $account->entries()->where('category', 'base')->where('kind', 'charge')->whereNotIn('id', LedgerEntry::whereNotNull('reverses_id')->select('reverses_id'))->first();
        if ($old) {
            $this->append($u, $account, ['kind' => 'reversal', 'amount_cents' => $old->amount_cents, 'charge_delta' => -$old->charge_delta, 'reverses_id' => $old->id, 'reason' => $reason, 'effective_at' => now()]);
        }
        $this->append($u, $account, ['kind' => 'charge', 'category' => 'base', 'amount_cents' => $amount, 'charge_delta' => $amount, 'reason' => $reason, 'effective_at' => now()]);
    }

    public function post(User $u, string $owner, int $id, array $input): LedgerEntry
    {
        $kind = $input['kind'] ?? '';
        abort_unless(in_array($kind, self::KINDS), 422);
        if (in_array($kind, ['refund', 'deposit_applied', 'deposit_refunded', 'reversal'])) {
            Gate::forUser($u)->authorize('finance.manage');
        } elseif ($kind === 'charge') {
            abort_unless($u->permits('rentals.manage') || $u->permits('finance.manage'), 403);
        } else {
            Gate::forUser($u)->authorize('payments.collect');
        }

        return app(Operations::class)->run($u, 'ledger:'.$owner.':'.$id, $input, function ($in) use ($u, $owner, $id, $kind) {
            $v = Validator::make($in, ['kind' => 'required|in:'.implode(',', self::KINDS), 'amount' => $kind === 'reversal' ? 'nullable' : 'required', 'category' => 'nullable|in:late,fuel,damage,mileage,towing,cancellation,other', 'method' => in_array($kind, ['payment', 'refund', 'deposit_received', 'deposit_refunded']) ? 'required|in:'.implode(',', self::METHODS) : 'nullable|in:'.implode(',', self::METHODS), 'reference' => 'nullable|string|max:190', 'reason' => 'required|string|max:500', 'effective_at' => 'required|date_format:Y-m-d\TH:i:sP|before_or_equal:now', 'reverses_id' => $kind === 'reversal' ? 'required|integer' : 'nullable'])->validate();
            $account = $this->account($owner, $id);
            $account = FinancialAccount::lockForUpdate()->findOrFail($account->id);
            $totals = $this->totals($account);
            $fields = ['kind' => $kind, 'reason' => $v['reason'], 'effective_at' => CarbonImmutable::parse($v['effective_at'])->utc(), 'method' => $v['method'] ?? null, 'reference' => $v['reference'] ?? null];
            if ($kind === 'reversal') {
                $original = $account->entries()->findOrFail($v['reverses_id']);
                if ($original->kind === 'reversal' || $original->category === 'base' || LedgerEntry::where('reverses_id', $original->id)->exists()) {
                    throw new Conflict('cannot_reverse');
                }
                $fields += ['amount_cents' => $original->amount_cents, 'charge_delta' => -$original->charge_delta, 'payment_delta' => -$original->payment_delta, 'deposit_delta' => -$original->deposit_delta, 'reverses_id' => $original->id];
            } else {
                $amount = Money::cents($v['amount']);
                if ($amount <= 0) {
                    throw new Conflict('positive_amount');
                }
                $fields['amount_cents'] = $amount;
                if ($kind === 'charge') {
                    $fields['category'] = $v['category'] ?? 'other';
                    $fields['charge_delta'] = $amount;
                }
                if ($kind === 'payment') {
                    $fields['payment_delta'] = $amount;
                }
                if ($kind === 'refund') {
                    if ($amount > $totals['cash_payments_cents']) {
                        throw new Conflict('refund_limit');
                    }
                    $fields['payment_delta'] = -$amount;
                }
                if ($kind === 'deposit_received') {
                    $fields['deposit_delta'] = $amount;
                }
                if (in_array($kind, ['deposit_applied', 'deposit_refunded'])) {
                    if ($amount > $totals['held_cents']) {
                        throw new Conflict('deposit_limit');
                    }
                    $fields['deposit_delta'] = -$amount;
                }
                if ($kind === 'deposit_applied') {
                    if ($amount > $totals['outstanding_cents']) {
                        throw new Conflict('application_limit');
                    }
                    $fields['payment_delta'] = $amount;
                }
            }

            return $this->append($u, $account, $fields);
        }, fn ($result) => LedgerEntry::findOrFail($result));
    }
}
