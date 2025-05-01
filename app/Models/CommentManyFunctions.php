<?php
//app/Models/LikeTable.php
namespace App\Models;

use App\Models\Comment;
use Illuminate\Support\Facades\Auth;

trait CommentManyFunctions
{

    public function comments()
    {
        return $this->morphMany(Comment::class, 'inceptor');
    }
    public function createComment($content)
    {
        return $this->comments()->create([
            //Laravel handles setting inceptor_id and inceptor_type under the hood when you use morphMany()->create().
            // 'inceptor_id' => $this->id,
            // 'inceptor_type' => get_class($this),
            'user_id' => Auth::user()->id,
            'content' => $content
        ]);
    }
}
