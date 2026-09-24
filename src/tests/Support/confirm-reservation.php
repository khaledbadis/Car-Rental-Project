<?php

use App\Models\User;
use App\Modules\Reservations\Actions;
use App\Modules\Reservations\Conflict;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
config(['database.connections.pgsql.database' => 'rental_test', 'database.connections.pgsql.url' => null]);
DB::purge('pgsql');
if (DB::selectOne('select current_database() as name')->name !== 'rental_test') {
    exit(3);
}
fwrite(STDOUT, "READY\n");
fflush(STDOUT);
try {
    app(Actions::class)->save(User::findOrFail($argv[1]), json_decode($argv[2], true, 512, JSON_THROW_ON_ERROR));
    exit(0);
} catch (Conflict $e) {
    fwrite(STDOUT, $e->errorCode);
    exit($e->errorCode === 'overlap' ? 2 : 3);
}
