<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class TelegramConnection extends Model { protected $fillable = ['wallet_id','user_id','chat_id','telegram_username','telegram_name','is_active','connected_at']; protected $casts = ['is_active'=>'boolean','connected_at'=>'datetime']; public function wallet(){return $this->belongsTo(Wallet::class);} public function user(){return $this->belongsTo(User::class);} }
