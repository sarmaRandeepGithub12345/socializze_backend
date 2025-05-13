<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FollowerFollowing extends Model
{
    use HasFactory,HasUuids;

    // Disable auto-incrementing and set composite primary key
    public $timestamps = true;
    protected $fillable = [
        'follower_id',
        'following_id'
    ];
    
    public function notification(){
        return $this->morphMany(Notifications::class,'notif_parent');
    }
}
