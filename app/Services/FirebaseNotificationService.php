<?php

namespace App\Services;

use App\Models\Notifications;
use Illuminate\Support\Facades\Auth;
use Kreait\Firebase\Factory;
use Kreait\Firebase\ServiceAccount;

class FirebaseNotificationService
{
    protected $messaging;

    public function __construct()
    {
        //$firebase = (new Factory)->withServiceAccount(storage_path('app/firebase_auth.json'));
        $credentials = config('firebase.credentials');
        $firebase = (new Factory())->withServiceAccount($credentials);
        $this->messaging = $firebase->createMessaging();
    }
    public function sendNotification($token, $title, $body, $image)
    {

        $message = [
            'token' => $token,
            'notification' => [
                'title' => $title,
                'body' => $body,
            ],
            'data' => [
                'user_image' => 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQb-NGEQDekk2BwsllLjk4tcIM_BPIzXECdsg&s',
                'post_image' => 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQb-NGEQDekk2BwsllLjk4tcIM_BPIzXECdsg&s',
            ],
        ];

        $this->messaging->send($message);
    }
    public function sendCommentNotification($token, $username, $body)
    {
        $message = [
            'token' => $token,
            'notification' => [
                'title' => $username,
                'body' => $body,
                'user_image' => 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQb-NGEQDekk2BwsllLjk4tcIM_BPIzXECdsg&s',
                'post_image' => 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQb-NGEQDekk2BwsllLjk4tcIM_BPIzXECdsg&s',
            ],
        ];

        $this->messaging->send($message);
    }

    public function likeCommentNotification($token, $title, $body, $image)
    {

        $message = [
            'token' => $token,
            'notification' => [
                // 'type' => 'comment/like',
                'title' => $title,
                'body' => $body,

                // 'post_image'=>'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQb-NGEQDekk2BwsllLjk4tcIM_BPIzXECdsg&s',
            ],
            'data' => [
                'type' => 'comment/like',
                'post_image' => $image,
            ],
        ];

        $this->messaging->send($message);
    }
    public function followUserNotification($token, $title, $body, $image)
    {

        $message = [
            'token' => $token,
            'notification' => [
                'title' => $title,
                'body' => $body,

                // 'post_image'=>'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQb-NGEQDekk2BwsllLjk4tcIM_BPIzXECdsg&s',
            ],
            'data' => [
                'type' => 'followed_me',
                'post_image' => $image,
            ],
        ];

        $this->messaging->send($message);
    }

    public function madeCommentNotification($token, $title, $body, $postimage, $profileimage)
    {
        $message = [
            'token' => $token,
            'notification' => [
                'title' => $title,
                'body' => $body,
            ],
            'data' => [
                'type' => 'comment/parent/make',
                'post_image' => $postimage,
                'profile_image' => $profileimage,
            ],
        ];

        $this->messaging->send($message);
    }
}
