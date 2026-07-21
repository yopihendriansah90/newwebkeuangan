<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Wallet extends Model { protected $fillable = ['name','created_by']; public function members() { return $this->belongsToMany(User::class, 'wallet_members')->withPivot('role')->withTimestamps(); } public function categories() { return $this->hasMany(Category::class); } public function transactions() { return $this->hasMany(Transaction::class); } }
