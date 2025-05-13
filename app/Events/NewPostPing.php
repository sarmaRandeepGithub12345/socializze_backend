<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Auth;

class NewPostPing implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public array $followerIDs;
    public function __construct(array $followerIDs)
    {
        $this->followerIDs = $followerIDs;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        $channels = [];

        foreach ($this->followerIDs as $userId) {
            $channels[] = new PrivateChannel('user.' . $userId);
        }

        return $channels;
    }
    public function broadcastAs(): string
    {
        return 'new.post.ping';
    }
    // public function broadcastWith(): array
    // {

    //     return [
    //         'new_post_ping' => true,
    //     ];
    // }
}
