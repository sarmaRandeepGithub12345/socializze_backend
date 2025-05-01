<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    use HasFactory, HasUuids, CommentManyFunctions, LikeFunctions, SingleFileFunctions;

    protected $fillable = [
        'description',
        'user_id',
        'isVideo'
    ];
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function comments()
    {
        //return $this->morphMany(Comment::class, 'inceptor')->with('users')->orderBy('created_at', 'desc');

        return $this->morphMany(Comment::class, 'inceptor')->orderBy('created_at', 'desc');
    }
    public function userMany()
    {
        return $this->belongsToMany(User::class);
    }
    // Add the likes relationship
    public function likes()
    {
        return $this->morphMany(Like::class, 'likeable')->orderBy('created_at', 'desc');
    }
    public function saves()
    {
        return $this->belongsToMany(User::class, 'saved_posts');
    }

    public function shares()
    {
        return $this->belongsToMany(User::class, 'shares');
    }
    public function views()
    {
        return $this->hasMany(VideoViews::class);
    }
    // public function singleFile()
    // {
    //     return $this->morphMany(SingleFile::class, 'parent');
    // }
    public function notifications()
    {
        return $this->morphMany(Notifications::class, 'firstParent')->orderBy('updated_at', 'desc');;
    }
}
