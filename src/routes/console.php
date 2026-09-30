<?php

use App\Models\LedgerEntry;
use App\Models\User;
use App\Modules\Foundation\Actions;
use App\Modules\Release\CutoverRehearsal;
use App\Modules\Release\Documents;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

Artisan::command('app:create-manager', function () {
    $name = $this->ask('Full name');
    $email = strtolower($this->ask('Email'));
    $password = $this->secret('Password (at least 12 characters)');
    $data = Validator::make(compact('name', 'email', 'password'), ['name' => 'required|string|max:120', 'email' => 'required|email|unique:users', 'password' => 'required|string|min:12|max:128'])->validate();
    DB::transaction(function () use ($data) {
        DB::table('agency_settings')->where('id', 1)->lockForUpdate()->first();
        abort_if(User::where('role', 'manager')->where('active', true)->exists(), 409, 'An active manager already exists; use staff administration.');
        $u = User::create($data + ['role' => 'manager', 'must_change_password' => true]);
        app(Actions::class)->audit(null, 'manager.provisioned', 'user', $u->id, null, $u->only(['name', 'email', 'role']));
    });
    $this->info('Manager created. Password change is required on first sign-in.');
})->purpose('Securely provision the first manager; never accepts passwords in shell arguments');

Artisan::command('app:backfill-receipts', function () {
    $count = 0;
    foreach (LedgerEntry::whereIn('kind', Documents::RECEIPTABLE)->where(fn ($q) => $q->whereNull('category')->orWhere('category', '!=', 'opening'))->whereDoesntHave('receipt')->orderBy('id')->cursor() as $entry) {
        app(Documents::class)->issue($entry);
        $count++;
    }
    $this->info("Issued {$count} missing receipts. Existing numbers were preserved.");
})->purpose('Issue permanent receipts for transactions predating Phase 5');

Artisan::command('app:rehearse-cutover', function () {
    $actor = User::where('role', 'manager')->where('active', true)->firstOrFail();
    $report = app(CutoverRehearsal::class)->run($actor);
    $this->line(json_encode($report, JSON_PRETTY_PRINT));
})->purpose('Reconcile fictional cutover fixtures locally; rolls back every imported record');
