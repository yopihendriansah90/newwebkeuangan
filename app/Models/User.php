<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;
    public function wallets() { return $this->belongsToMany(Wallet::class, 'wallet_members')->withPivot('role')->withTimestamps(); }
    public function wallet()
    {
        return Wallet::shared();
    }
    public function walletCategories() { return $this->wallet()?->categories() ?? Category::whereRaw('1 = 0'); }
    public function walletTransactions() { return $this->wallet()?->transactions() ?? Transaction::whereRaw('1 = 0'); }
    public function categories() { return $this->walletCategories(); }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'username',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
