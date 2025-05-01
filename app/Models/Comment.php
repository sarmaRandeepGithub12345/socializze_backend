<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Comment extends Model
{
    use HasFactory, HasUuids, LikeFunctions;
    protected $fillable = [
        'inceptor_id', //original parent could be post,story
        'inceptor_type',
        'replied_to_id', //for handling notifications ,must be comment
        'closest_parentComment_id', //for loading child comments,must be comment
        'user_id',
        'content',
    ];
    public function inceptor()
    {
        return $this->morphTo();
    }
    public function parentComment()
    {
        return $this->belongsTo(Comment::class, 'closest_parentComment_id');
    }
    public function childComments()
    {
        return $this->hasMany(Comment::class, 'closest_parentComment_id');
    }
    public function replies()
    {
        return $this->hasMany(Comment::class, 'replied_to_id');
    }
    public function repliedToF()
    {
        return $this->belongsTo(Comment::class, 'replied_to_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
    public function likes()
    {
        return $this->morphMany(Like::class, 'likeable')->orderBy('created_at', 'desc');
    }
    public function notification()
    {
        return $this->morphMany(Notifications::class, 'notif_parent');
    }
}
