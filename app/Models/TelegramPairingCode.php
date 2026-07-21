<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class TelegramPairingCode extends Model { protected $fillable = ['wallet_id','user_id','code','expires_at','used_at']; protected $casts = ['expires_at'=>'datetime','used_at'=>'datetime']; public function wallet(){return $this->belongsTo(Wallet::class);} public function user(){return $this->belongsTo(User::class);} public function isValid(){return is_null($this->used_at) && $this->expires_at->isFuture();} }
