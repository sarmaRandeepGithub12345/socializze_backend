<?php
//app/Models/LikeTable.php
namespace App\Models;

use App\Models\Like as ModelsLikeTable;


trait LikeFunctions
{

    public function likes()
    {
        return $this->morphMany(ModelsLikeTable::class, 'likeable');
    }
    public function like()
    {
        $like = new ModelsLikeTable();
        $like->user_id = auth()->id();
        $like->likeable_id = $this->id;
        $like->likeable_type = get_class($this);
        $this->likes()->save($like);
        return $like;
    }
    public function unlike()
    {

        $pLike = $this->likes()->where('user_id', auth()->id())->first();

        $pLike->delete();
        return $pLike;
    }
    public function isLiked()
    {
        return $this->likes()->where('user_id', auth()->id())->exists();
    }
}
