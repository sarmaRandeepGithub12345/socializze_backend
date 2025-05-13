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

class SeeStory implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public $story, $see;
    public function __construct($story, $see)
    {
        $this->story = $story;
        $this->see = $see;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {

        return [
            new PrivateChannel('user.' . $this->story->user_id),
        ];
    }
    public function broadcastAs(): string
    {
        return 'story.seen';
    }
    public function broadcastWith(): array
    {
        return [
            'story_created_at' => $this->story->created_at,
            'story_id' => $this->story->id,
            'seenInfo' => [
                'id' => $this->see->id,
                'userData' => [
                    'id' => $this->see->user->id,
                    'username' => $this->see->user->username,
                    'imageUrl' => $this->see->user->imageUrl,
                ],
                'updated_at' => $this->see->updated_at,
            ],

        ];
    }
}
