<?php

namespace App\Modules\Reporting;

use App\Models\FinancialAccount;
use App\Models\LedgerEntry;
use App\Models\Rental;
use App\Models\Reservation;

class Recognition
{
    public function entries(): array
    {
        $entries = LedgerEntry::all()->keyBy('id');
        $accounts = FinancialAccount::all()->keyBy('id');
        $rentals = Rental::all()->keyBy('id');
        $bookings = Reservation::all()->keyBy('id');
        $result = [];
        foreach ($entries as $e) {
            $original = $e->kind === 'reversal' ? $entries->get($e->reverses_id) : $e;
            $account = $accounts->get($e->financial_account_id);
            $r = $rentals->get($account?->rental_id);
            $booking = $bookings->get($account?->reservation_id);
            $date = $r?->starts_at ?? ($original?->category === 'cancellation' ? $original->effective_at : null);
            $result[] = ['entry_id' => $e->id, 'vehicle_id' => $r?->vehicle_id ?? $booking?->vehicle_id, 'revenue_date' => $date?->setTimezone('Africa/Algiers')->toDateString(), 'revenue' => ($date && $original?->category !== 'opening' && $r?->status !== 'draft') ? (int) $e->charge_delta : 0, 'collections_date' => $e->effective_at->setTimezone('Africa/Algiers')->toDateString(), 'collections' => in_array($original?->kind, ['payment', 'refund']) ? (int) $e->payment_delta : 0, 'standalone_cancellation' => ! $r && $original?->category === 'cancellation'];
        }

        return $result;
    }
}
