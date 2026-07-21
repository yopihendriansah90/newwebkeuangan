<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('categories', function (Blueprint $table) { $table->id(); $table->foreignId('user_id')->constrained()->cascadeOnDelete(); $table->string('name'); $table->enum('type', ['income','expense']); $table->string('color')->default('#ef4444'); $table->boolean('is_active')->default(true); $table->timestamps(); $table->unique(['user_id','name','type']); });
        Schema::create('transactions', function (Blueprint $table) { $table->id(); $table->foreignId('user_id')->constrained()->cascadeOnDelete(); $table->foreignId('category_id')->constrained()->restrictOnDelete(); $table->enum('type', ['income','expense']); $table->date('transaction_date'); $table->string('description'); $table->decimal('amount', 15, 2); $table->timestamps(); $table->index(['user_id','transaction_date']); });
    }
    public function down(): void { Schema::dropIfExists('transactions'); Schema::dropIfExists('categories'); }
};
