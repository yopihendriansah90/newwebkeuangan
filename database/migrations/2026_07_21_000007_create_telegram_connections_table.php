<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('telegram_pairing_codes', function (Blueprint $table) { $table->id(); $table->foreignId('wallet_id')->constrained()->cascadeOnDelete(); $table->foreignId('user_id')->constrained()->cascadeOnDelete(); $table->string('code', 32)->unique(); $table->timestamp('expires_at'); $table->timestamp('used_at')->nullable(); $table->timestamps(); });
        Schema::create('telegram_connections', function (Blueprint $table) { $table->id(); $table->foreignId('wallet_id')->constrained()->cascadeOnDelete(); $table->foreignId('user_id')->constrained()->cascadeOnDelete(); $table->string('chat_id')->unique(); $table->string('telegram_username')->nullable(); $table->string('telegram_name')->nullable(); $table->boolean('is_active')->default(true); $table->timestamp('connected_at')->nullable(); $table->timestamps(); });
    }
    public function down(): void { Schema::dropIfExists('telegram_connections'); Schema::dropIfExists('telegram_pairing_codes'); }
};
