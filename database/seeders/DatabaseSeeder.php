<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Category;
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
        foreach ([['name' => 'Gaji', 'type' => 'income', 'color' => '#10b981'], ['name' => 'Bonus', 'type' => 'income', 'color' => '#10b981'], ['name' => 'Makanan', 'type' => 'expense', 'color' => '#ef4444'], ['name' => 'Transportasi', 'type' => 'expense', 'color' => '#f97316'], ['name' => 'Tagihan', 'type' => 'expense', 'color' => '#8b5cf6']] as $category) {
            Category::firstOrCreate(['user_id' => $user->id, 'name' => $category['name'], 'type' => $category['type']], $category);
        }
    }
}
