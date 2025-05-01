<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Sanctum\HasApiTokens;

class Phone extends Model
{
    use HasApiTokens, HasFactory, HasUuids;
    protected $fillable = ['user_id', 'phone', 'country_code', 'otp', 'otp_expires_at', 'verified_at',];
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
