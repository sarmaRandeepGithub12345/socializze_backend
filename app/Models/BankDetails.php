<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BankDetails extends Model
{
    use HasFactory, HasUuids;
    protected $fillable = [
        'user_id',
        'type',
        'account_number',
        'ifsc',
        'upi_id',
    ];
    public function owner()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
