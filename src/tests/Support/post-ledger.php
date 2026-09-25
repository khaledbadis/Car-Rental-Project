<?php

use App\Models\User;
use App\Modules\Rentals\Conflict;
use App\Modules\Rentals\Ledger;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
config(['database.connections.pgsql.database' => 'rental_test', 'database.connections.pgsql.url' => null]);
DB::purge('pgsql');
if (DB::selectOne('select current_database() as name')->name !== 'rental_test') {
    exit(3);
}fwrite(STDOUT, "READY\n");
fflush(STDOUT);
try {
    app(Ledger::class)->post(User::findOrFail($argv[1]), 'rental', (int) $argv[2], json_decode($argv[3], true, 512, JSON_THROW_ON_ERROR));
    exit(0);
} catch (Conflict $e) {
    fwrite(STDOUT, $e->errorCode);
    exit(in_array($e->errorCode, ['deposit_limit', 'application_limit']) ? 2 : 3);
}
