<?php

namespace App\Models;

use Illuminate\Support\Facades\Auth;

trait NotificationFunctions
{
    public function notifParent()
    {
        return $this->morphMany(Notifications::class, 'notif_parent');
    }
    public function createNotification($post_id)
    {
        // if (!$this->exists) {
        //     $this->save();
        // }
        // $notif = new Notifications();
        // $notif->notif_parent_id=$this->id;
        // $notif->notif_parent_type=get_class($this);
        // $this->notification()->save($notif);
        //--------------------
        $like = Like::where('user_id', Auth::user()->id)
            ->where('likeable_id', $post_id)
            ->first();
        $notif = new Notifications([
            'notif_parent_id' => $like->id,
            'notif_parent_type' => get_class($like),
        ]);
        return $this->notification()->save($notif);
    }
}
