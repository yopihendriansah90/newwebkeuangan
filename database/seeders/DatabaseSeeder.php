<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Category;
use App\Models\Wallet;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $user = User::firstOrCreate(['username' => 'admin'], [
            'name' => 'Admin', 'email' => 'admin@keuangan.local', 'password' => 'admin',
        ]);
        $wallet = $user->wallet() ?? Wallet::create(['name' => 'Dompet Keluarga', 'created_by' => $user->id]);
        if (!$user->wallets()->whereKey($wallet->id)->exists()) $user->wallets()->attach($wallet->id, ['role' => 'owner']);
        foreach ([['name' => 'Gaji', 'type' => 'income', 'color' => '#10b981', 'icon' => 'payments'], ['name' => 'Bonus', 'type' => 'income', 'color' => '#10b981', 'icon' => 'card_giftcard'], ['name' => 'Makanan', 'type' => 'expense', 'color' => '#ef4444', 'icon' => 'restaurant'], ['name' => 'Transportasi', 'type' => 'expense', 'color' => '#f97316', 'icon' => 'directions_car'], ['name' => 'Tagihan', 'type' => 'expense', 'color' => '#8b5cf6', 'icon' => 'receipt_long']] as $category) {
            Category::firstOrCreate(['wallet_id' => $wallet->id, 'name' => $category['name'], 'type' => $category['type']], [...$category, 'user_id' => $user->id, 'wallet_id' => $wallet->id]);
        }
    }
}
