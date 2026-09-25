<?php

namespace App\Modules\Rentals;

use App\Models\User;
use App\Support\Input;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class Operations
{
    public function run(User $u, string $operation, array $input, callable $work, callable $result): mixed
    {
        $input = Input::normalize($input);
        Validator::make($input, ['idempotency_key' => 'required|uuid'])->validate();
        $key = $input['idempotency_key'];
        unset($input['idempotency_key']);
        $sorted = $input;
        ksort($sorted);
        $fingerprint = hash('sha256', json_encode($sorted, JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($u, $operation, $input, $key, $fingerprint, $work, $result) {
            DB::table('agency_settings')->where('id', 1)->lockForUpdate()->first();
            $prior = DB::table('operation_keys')->where('actor_id', $u->id)->where('key', $key)->first();
            if ($prior) {
                if ($prior->operation !== $operation || $prior->fingerprint !== $fingerprint) {
                    throw new Conflict('retry_mismatch');
                }

                return $result($prior->result_id);
            }
            $record = $work($input);
            DB::table('operation_keys')->insert(['actor_id' => $u->id, 'key' => $key, 'operation' => $operation, 'fingerprint' => $fingerprint, 'result_id' => $record->id, 'created_at' => now()]);

            return $record;
        });
    }
}
