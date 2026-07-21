<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Category extends Model { protected $fillable = ['user_id','wallet_id','name','type','color','is_active']; protected $casts = ['is_active' => 'boolean']; public function wallet() { return $this->belongsTo(Wallet::class); } public function transactions() { return $this->hasMany(Transaction::class); } }
