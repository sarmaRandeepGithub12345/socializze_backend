<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChatParticipants extends Model
{
    use HasFactory,HasUuids;
    protected $fillable=[
       'user_id',
       'chat_id',
       'role',
       'status',
    ];
    public function chats(){
        return $this->belongsTo(Chats::class,'chat_id', 'id');
    }
    public function users(){
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
