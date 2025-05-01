<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notifications extends Model
{
    use HasFactory,HasUuids;
    public $timestamps = true;
    protected $fillable = [
        'user_id',
        'first_parent_id',
        'first_parent_type',
        'second_parent_id',
        'second_parent_type',
        'seen'    ];
    public function firstParent(){
        return $this->morphTo();
    }
    public function secondParent(){
        return $this->morphTo();
    }
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    // public function userseen()
    // {
    //     return $this->hasMany(UserSeen::class, 'seenparent', 'id')
    //                 ->where('seen_type', self::class);
    // }  
}
