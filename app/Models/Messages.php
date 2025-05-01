<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Messages extends Model
{
    use HasFactory, HasUuids, SingleFileFunctions, LikeFunctions, UserSeenFunctions;

    protected $fillable = [
        'sender_id',
        'chat_id',
        'message',
        'is_missed_call',
        'media_type'
    ];
    public function singleFile()
    {
        return $this->morphMany(SingleFile::class, 'parent');
    }
    public function chats()
    {
        return $this->belongsTo(Chats::class, 'chat_id', 'id');
    }
    public function sender()
    {
        return $this->belongsTo(User::class);
    }

    public function notification()
    {
        return $this->morphMany(Notifications::class, 'notif_parent');
    }
}
