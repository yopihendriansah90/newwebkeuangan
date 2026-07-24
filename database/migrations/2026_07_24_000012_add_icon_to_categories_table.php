<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->string('icon', 60)->default('more_horiz')->after('color');
        });

        foreach (['Gaji' => 'payments', 'Bonus' => 'card_giftcard', 'Makanan' => 'restaurant', 'Transportasi' => 'directions_car', 'Tagihan' => 'receipt_long'] as $name => $icon) {
            DB::table('categories')->where('name', $name)->update(['icon' => $icon]);
        }
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('icon');
        });
    }
};
