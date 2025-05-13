<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Payouts extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'id',
        'beneficiary_id',
        "amount",
        'reference_id',
        'status'
    ];
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (!$model->id) {
                $model->id = (string) Str::uuid(); // Generate UUID if not manually set
            }
        });
    }
    public function beneficiary()
    {
        return $this->belongsTo(User::class, 'beneficiary_id');
    }
}
