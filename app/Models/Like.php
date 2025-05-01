<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Like extends Model
{
    use HasFactory, HasUuids,NotificationFunctions;
    public $timestamps = true;
    protected $fillable = ['user_id','likeable_id','likeable_type'];
    
    public function users(){
        return $this->belongsTo(User::class,'user_id', 'id');
    } 
    public function likeable(){
        return $this->morphTo();
    }
    public function notification()
    {
        return $this->morphOne(Notifications::class, 'secondParent');
    }
   
}
