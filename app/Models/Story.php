<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Story extends Model
{
    use HasFactory, HasUuids, CommentManyFunctions, UserSeenFunctions, SingleFileOne, LikeFunctions;

    protected $fillable = [
        'user_id',
    ];
    //story maker
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function comments()
    {
        return $this->morphMany(Comment::class, 'inceptor')->orderBy('created_at', 'asc');
    }
}
