<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserSeen extends Model
{
    use HasFactory, HasUuids;
    public $timestamps = false;

    protected $fillable = ['user_id', 'parentSeen_id', 'parentSeen_type', 'updated_at'];
    //One Seen - One Parent
    public function parentSeen()
    {
        return $this->morphTo();
    }
    //One User - One Seen
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
