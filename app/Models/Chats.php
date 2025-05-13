<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Chats extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'is_group',
        'chat_name',
        'groupIcon',
    ];
    public function chatparticipants()
    {
        return $this->hasMany(ChatParticipants::class, 'chat_id', 'id');
    }
    public function latestMessage()
    {
        return $this->hasOne(Messages::class, 'chat_id', 'id')->latest();
    }
    public function messages()
    {
        return $this->hasMany(Messages::class, 'chat_id', 'id');
    }
}
