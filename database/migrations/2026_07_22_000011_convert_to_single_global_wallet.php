<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('wallets')) return;

        $primary = DB::table('wallets')->orderBy('id')->first();
        if (!$primary && Schema::hasTable('users')) {
            $userId = DB::table('users')->orderBy('id')->value('id');
            if ($userId) {
                $id = DB::table('wallets')->insertGetId(['name' => 'Dompet Bersama', 'created_by' => $userId, 'created_at' => now(), 'updated_at' => now()]);
                $primary = DB::table('wallets')->where('id', $id)->first();
            }
        }
        if (!$primary) return;

        foreach (['categories','transactions','telegram_pairing_codes','telegram_connections','pending_transactions'] as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'wallet_id')) {
                DB::table($table)->where(function ($query) use ($primary) { $query->whereNull('wallet_id')->orWhere('wallet_id', '!=', $primary->id); })->update(['wallet_id' => $primary->id]);
            }
        }

        if (Schema::hasTable('wallet_members') && Schema::hasTable('users')) {
            foreach (DB::table('users')->pluck('id') as $userId) {
                $exists = DB::table('wallet_members')->where('wallet_id', $primary->id)->where('user_id', $userId)->exists();
                if (!$exists) DB::table('wallet_members')->insert(['wallet_id' => $primary->id, 'user_id' => $userId, 'role' => 'member', 'created_at' => now(), 'updated_at' => now()]);
            }
        }
    }

    public function down(): void
    {
        // Data consolidation is intentionally not reversed automatically.
    }
};
