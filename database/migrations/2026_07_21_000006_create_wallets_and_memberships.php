<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('wallets', function (Blueprint $table) { $table->id(); $table->string('name'); $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete(); $table->timestamps(); });
        Schema::create('wallet_members', function (Blueprint $table) { $table->id(); $table->foreignId('wallet_id')->constrained()->cascadeOnDelete(); $table->foreignId('user_id')->constrained()->cascadeOnDelete(); $table->enum('role', ['owner','member'])->default('member'); $table->timestamps(); $table->unique(['wallet_id','user_id']); });
        Schema::table('categories', function (Blueprint $table) { $table->foreignId('wallet_id')->nullable()->after('user_id')->constrained()->nullOnDelete(); });
        Schema::table('transactions', function (Blueprint $table) { $table->foreignId('wallet_id')->nullable()->after('user_id')->constrained()->nullOnDelete(); });
        foreach (DB::table('users')->get() as $user) {
            $walletId = DB::table('wallets')->insertGetId(['name' => 'Dompet Keluarga', 'created_by' => $user->id, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('wallet_members')->insert(['wallet_id' => $walletId, 'user_id' => $user->id, 'role' => 'owner', 'created_at' => now(), 'updated_at' => now()]);
            DB::table('categories')->where('user_id', $user->id)->update(['wallet_id' => $walletId]);
            DB::table('transactions')->where('user_id', $user->id)->update(['wallet_id' => $walletId]);
        }
    }
    public function down(): void { Schema::table('transactions', fn (Blueprint $table) => $table->dropForeign(['wallet_id'])); Schema::table('transactions', fn (Blueprint $table) => $table->dropColumn('wallet_id')); Schema::table('categories', fn (Blueprint $table) => $table->dropForeign(['wallet_id'])); Schema::table('categories', fn (Blueprint $table) => $table->dropColumn('wallet_id')); Schema::dropIfExists('wallet_members'); Schema::dropIfExists('wallets'); }
};
