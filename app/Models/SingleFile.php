<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SingleFile extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'parent_id',
        'parent_type',
        'aws_link',
        'thumbnail',
        'media_type'
    ];
    public function parent()
    {
        return $this->morphTo();
    }
}
