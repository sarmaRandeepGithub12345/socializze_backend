<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

trait UserSeenFunctions
{
    public function getSeen()
    {
        return $this->morphMany(UserSeen::class, 'parentSeen')->orderBy('updated_at', 'desc');
    }
    public function makeSeen()
    {
        $userId = Auth::user()->id;
        return $this->getSeen()->create([
            'user_id' => $userId,
            'updated_at' => Carbon::now(),
        ]);
    }
}
