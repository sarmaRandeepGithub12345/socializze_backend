<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PostUsers extends Model
{
    use HasFactory,HasUuids;
    protected $fillable = [
        'tagged_id',
        'post_id',
       
    ];
}