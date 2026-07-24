<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class PendingTransaction extends Model { protected $fillable=['wallet_id','user_id','chat_id','payload','status','expires_at']; protected $casts=['payload'=>'array','expires_at'=>'datetime']; public function wallet(){return $this->belongsTo(Wallet::class);} public function user(){return $this->belongsTo(User::class);} public function isValid(){return $this->status==='pending'&&$this->expires_at?->isFuture();} public function state():string{if(!$this->isValid())return 'expired';if(($this->payload['kind']??null)==='category_action')return 'category';if(!empty($this->payload['editing']))return 'editing';return 'confirmation';} }
