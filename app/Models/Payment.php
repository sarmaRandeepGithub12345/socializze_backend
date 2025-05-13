<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Payment extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'id',
        'customer_id',
        'seller_id',
        'currency',
        'amount',
        'expire_at',
        'session_id',
        'status',
        'payment_message',
        'type',
        'transaction_id',
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
    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id');
    }
    public function seller()
    {
        return $this->belongsTo(User::class, 'seller_id');
    }
}
