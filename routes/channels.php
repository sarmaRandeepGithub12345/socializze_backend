<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return  $user->id ===  $id;
});
Broadcast::channel('user.{id}', function ($user, $id) {
    return  $user->id ===  $id;
});
// Broadcast::channel('message-seen.{id}', function ($user, $id) {
//     return  $user->id ===  $id;
// });
