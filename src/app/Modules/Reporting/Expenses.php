<?php

namespace App\Modules\Reporting;

use App\Models\Expense;
use App\Models\ExpenseAttachment;
use App\Models\MaintenanceRecord;
use App\Models\User;
use App\Models\Vehicle;
use App\Modules\Foundation\Actions as Audit;
use App\Modules\Rentals\Money;
use App\Modules\Reservations\Conflict;
use App\Support\Input;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class Expenses
{
    public const CATEGORIES = ['maintenance', 'spare_parts', 'cleaning', 'towing', 'insurance', 'other'];

    public function query(User $u, array $in = [])
    {
        Gate::forUser($u)->authorize('finance.manage');
        $v = Validator::make(Input::normalize($in), ['vehicle_id' => 'nullable|integer|exists:vehicles,id'])->validate();

        return Expense::with(['vehicle:id,registration', 'attachments', 'reversal'])->when($v['vehicle_id'] ?? null, fn ($q, $id) => $q->where('vehicle_id', $id))->orderByDesc('incurred_on')->orderByDesc('id');
    }

    // Called inside the maintenance transaction. The unique link is the single reporting source.
    public function maintenance(MaintenanceRecord $r): Expense
    {
        return Expense::firstOrCreate(['maintenance_record_id' => $r->id], ['vehicle_id' => $r->vehicle_id, 'category' => 'maintenance', 'amount_cents' => $r->cost_cents, 'incurred_on' => $r->serviced_on->toDateString(), 'description' => $r->notes ?? $r->service_type, 'created_by' => $r->created_by]);
    }

    public function post(User $u, array $input): Expense
    {
        Gate::forUser($u)->authorize('finance.manage');
        $v = Validator::make(Input::normalize($input), ['vehicle_id' => 'required|integer|exists:vehicles,id', 'category' => 'required|in:'.implode(',', self::CATEGORIES), 'amount' => 'required', 'incurred_on' => 'required|date_format:Y-m-d|before_or_equal:'.now('Africa/Algiers')->toDateString(), 'description' => 'required|string|max:4000', 'idempotency_key' => 'required|uuid', 'maintenance_record_id' => 'prohibited'])->validate();
        $v['amount_cents'] = Money::cents($v['amount']);
        unset($v['amount']);
        if ($v['amount_cents'] <= 0) {
            throw ValidationException::withMessages(['amount' => __('rental.invalid_money')]);
        }
        ksort($v);
        $hash = hash('sha256', json_encode($v));

        return DB::transaction(function () use ($u, $v, $hash) {
            DB::table('agency_settings')->where('id', 1)->lockForUpdate()->first();
            $prior = $this->retry($u, $v['idempotency_key'], $hash);
            if ($prior) {
                return $prior;
            }Vehicle::lockForUpdate()->findOrFail($v['vehicle_id']);
            $e = Expense::create($v + ['created_by' => $u->id, 'request_hash' => $hash]);
            app(Audit::class)->audit($u, 'expense.recorded', 'expense', $e->id, null, $e->toArray());

            return $e;
        });
    }

    private function retry(User $u, string $key, string $hash): ?Expense
    {
        $e = Expense::where('idempotency_key', $key)->first();
        if ($e && ($e->created_by !== $u->id || $e->request_hash !== $hash)) {
            throw new Conflict('stale');
        }

        return $e;
    }

    public function reverse(User $u, int $id, array $input): Expense
    {
        Gate::forUser($u)->authorize('finance.manage');
        $v = Validator::make(Input::normalize($input), ['reason' => 'required|string|max:500', 'idempotency_key' => 'required|uuid'])->validate();
        $hash = hash('sha256', json_encode([$id, $v['reason']]));

        return DB::transaction(function () use ($u, $id, $v, $hash) {
            DB::table('agency_settings')->where('id', 1)->lockForUpdate()->first();
            if ($prior = $this->retry($u, $v['idempotency_key'], $hash)) {
                return $prior;
            }$e = Expense::lockForUpdate()->findOrFail($id);
            if ($e->reverses_id || $e->reversal()->exists()) {
                throw new Conflict('closed');
            }$r = Expense::create(['vehicle_id' => $e->vehicle_id, 'category' => $e->category, 'amount_cents' => -$e->amount_cents, 'incurred_on' => $e->incurred_on, 'description' => $v['reason'], 'created_by' => $u->id, 'reverses_id' => $e->id, 'idempotency_key' => $v['idempotency_key'], 'request_hash' => $hash]);
            app(Audit::class)->audit($u, 'expense.reversed', 'expense', $e->id, null, ['reversal_id' => $r->id], $v['reason']);

            return $r;
        });
    }

    public function upload(User $u, int $id, array $input): ExpenseAttachment
    {
        Gate::forUser($u)->authorize('finance.manage');
        Expense::findOrFail($id);
        $limit = (int) DB::table('agency_settings')->where('id', 1)->value('upload_limit_mb') * 1024;
        $v = Validator::make($input, ['file' => 'required|file|mimes:pdf,jpg,jpeg,png|max:'.$limit])->validate();
        $path = $v['file']->store('expenses', 'local');
        if (! $path) {
            throw new \RuntimeException('Private storage write failed');
        }
        try {
            return DB::transaction(function () use ($u, $id, $v, $path) {
                $a = ExpenseAttachment::create(['expense_id' => $id, 'path' => $path, 'mime' => $v['file']->getMimeType(), 'created_by' => $u->id]);
                app(Audit::class)->audit($u, 'expense.attachment_added', 'expense', $id, null, ['attachment_id' => $a->id]);

                return $a;
            });
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($path);
            throw $e;
        }
    }

    public function download(User $u, int $id)
    {
        Gate::forUser($u)->authorize('finance.manage');
        $a = ExpenseAttachment::findOrFail($id);
        $ext = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png'][$a->mime];

        return Storage::disk('local')->download($a->path, 'expense-'.$a->id.'.'.$ext, ['Content-Type' => $a->mime, 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store']);
    }
}
