<?php

namespace App\Modules\Maintenance;

use App\Models\MaintenanceAttachment;
use App\Models\MaintenanceRecord;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleCommitment;
use App\Modules\Foundation\Actions as Foundation;
use App\Modules\Rentals\Money;
use App\Modules\Reporting\Expenses;
use App\Modules\Reservations\Actions as Schedule;
use App\Modules\Reservations\Conflict;
use App\Support\Input;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class Actions
{
    public const TYPES = ['oil', 'tyres', 'brakes', 'inspection', 'repair', 'other'];

    public function query(User $u, array $input = [])
    {
        Gate::forUser($u)->authorize('maintenance.view');
        $v = Validator::make(Input::normalize($input), ['vehicle_id' => 'nullable|integer|exists:vehicles,id'])->validate();
        $query = MaintenanceRecord::with(['vehicle:id,registration,mileage_km', 'block']);
        if (! $u->permits('finance.manage')) {
            $query->select(['id', 'vehicle_id', 'vehicle_commitment_id', 'service_type', 'serviced_on', 'mileage_km', 'next_due_on', 'next_due_km', 'created_at']);
        } else {
            $query->with('attachments');
        }

        return $query->when($v['vehicle_id'] ?? null, fn ($q, $id) => $q->where('vehicle_id', $id))->orderByDesc('serviced_on')->orderByDesc('id');
    }

    public function record(User $u, array $input): MaintenanceRecord
    {
        Gate::forUser($u)->authorize('maintenance.manage');
        $v = Validator::make(Input::normalize($input), [
            'vehicle_id' => 'required|integer|exists:vehicles,id',
            'vehicle_commitment_id' => 'nullable|integer|exists:vehicle_commitments,id',
            'service_type' => 'required|in:'.implode(',', self::TYPES),
            'serviced_on' => 'required|date_format:Y-m-d|before_or_equal:'.now('Africa/Algiers')->toDateString(),
            'mileage_km' => 'required|integer|min:0|max:2147483647', 'cost' => 'required',
            'notes' => 'nullable|string|max:4000', 'next_due_on' => 'nullable|date_format:Y-m-d|after:serviced_on',
            'next_due_km' => 'nullable|integer|gt:mileage_km|max:2147483647', 'idempotency_key' => 'required|uuid',
        ])->validate();
        $v['cost_cents'] = Money::cents($v['cost'], 'cost');
        unset($v['cost']);
        ksort($v);
        $hash = hash('sha256', json_encode($v));

        return DB::transaction(function () use ($u, $v, $hash) {
            DB::table('agency_settings')->where('id', 1)->lockForUpdate()->first();
            $prior = MaintenanceRecord::where('idempotency_key', $v['idempotency_key'])->first();
            if ($prior) {
                if ($prior->request_hash !== $hash || $prior->created_by !== $u->id) {
                    throw new Conflict('stale');
                }

                return $prior;
            }
            $car = Vehicle::lockForUpdate()->findOrFail($v['vehicle_id']);
            if ($car->archived_at) {
                throw new Conflict('archived');
            }
            if (! empty($v['vehicle_commitment_id'])) {
                $block = VehicleCommitment::lockForUpdate()->findOrFail($v['vehicle_commitment_id']);
                if ($block->vehicle_id !== $car->id || $block->kind !== 'maintenance') {
                    throw ValidationException::withMessages(['vehicle_commitment_id' => __('maintenance.invalid_block')]);
                }
            }
            $record = MaintenanceRecord::create($v + ['created_by' => $u->id, 'request_hash' => $hash]);
            app(Expenses::class)->maintenance($record);
            // Historical service readings never lower the latest known odometer.
            if ($v['mileage_km'] > $car->mileage_km) {
                $car->update(['mileage_km' => $v['mileage_km'], 'version' => $car->version + 1]);
            }
            app(Foundation::class)->audit($u, 'maintenance.recorded', 'maintenance', $record->id, null, $record->toArray());

            return $record;
        });
    }

    public function release(User $u, int $id, array $input): VehicleCommitment
    {
        Gate::forUser($u)->authorize('maintenance.manage');
        $record = MaintenanceRecord::findOrFail($id);
        abort_unless($record->vehicle_commitment_id, 422, __('maintenance.invalid_block'));

        return app(Schedule::class)->release($u, $record->vehicle_commitment_id, $input);
    }

    public function upload(User $u, int $id, array $input): MaintenanceAttachment
    {
        Gate::forUser($u)->authorize('maintenance.manage');
        MaintenanceRecord::findOrFail($id);
        $limit = (int) DB::table('agency_settings')->where('id', 1)->value('upload_limit_mb') * 1024;
        $v = Validator::make($input, ['file' => 'required|file|mimes:pdf,jpg,jpeg,png|max:'.$limit])->validate();
        $path = $v['file']->store('maintenance', 'local');
        if (! $path) {
            throw new \RuntimeException('Private storage write failed');
        }
        try {
            return DB::transaction(function () use ($u, $id, $v, $path) {
                $attachment = MaintenanceAttachment::create(['maintenance_record_id' => $id, 'path' => $path, 'mime' => $v['file']->getMimeType(), 'created_by' => $u->id]);
                app(Foundation::class)->audit($u, 'maintenance.attachment_added', 'maintenance', $id, null, ['attachment_id' => $attachment->id]);

                return $attachment;
            });
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($path);
            throw $e;
        }
    }

    public function download(User $u, int $id)
    {
        Gate::forUser($u)->authorize('maintenance.view');
        Gate::forUser($u)->authorize('finance.manage');
        $a = MaintenanceAttachment::findOrFail($id);
        $extension = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png'][$a->mime];

        return Storage::disk('local')->download($a->path, 'maintenance-'.$a->id.'.'.$extension, ['Content-Type' => $a->mime, 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store']);
    }

    public function reminders(User $u): array
    {
        Gate::forUser($u)->authorize('maintenance.view');
        $settings = DB::table('agency_settings')->find(1);
        $today = now('Africa/Algiers')->startOfDay();
        // Latest service for each vehicle/type supersedes its previous reminder, including clearing it.
        $records = MaintenanceRecord::with('vehicle')->orderByDesc('serviced_on')->orderByDesc('id')->get()->unique(fn ($r) => $r->vehicle_id.':'.$r->service_type);

        return $records->filter(fn ($r) => ! $r->vehicle->archived_at)->map(function ($r) use ($settings, $today) {
            $due = ($r->next_due_on && $r->next_due_on->toDateString() <= $today->toDateString()) || ($r->next_due_km !== null && $r->vehicle->mileage_km >= $r->next_due_km);
            $soon = ($r->next_due_on && $r->next_due_on->toDateString() <= $today->copy()->addDays($settings->maintenance_warning_days)->toDateString()) || ($r->next_due_km !== null && $r->vehicle->mileage_km + $settings->maintenance_warning_km >= $r->next_due_km);

            return $due || $soon ? ['id' => $r->id, 'vehicle_id' => $r->vehicle_id, 'registration' => $r->vehicle->registration, 'service_type' => $r->service_type, 'status' => $due ? 'due' : 'soon', 'next_due_on' => $r->next_due_on?->toDateString(), 'next_due_km' => $r->next_due_km, 'mileage_km' => $r->vehicle->mileage_km] : null;
        })->filter()->values()->all();
    }
}
