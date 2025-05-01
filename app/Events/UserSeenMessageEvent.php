<?php

namespace App\Events;

use App\Models\ChatParticipants;
use App\Models\Chats;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserSeenMessageEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public $chatID;
    public function __construct($chatID)
    {
        $this->chatID = $chatID;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        $participants = ChatParticipants::where('chat_id', $this->chatID)->pluck('user_id');

        foreach ($participants as $userId) {
            $channels[] = new PrivateChannel('user.' . $userId);
        }
        return $channels;
    }
    public function broadcastAs(): string
    {
        return 'message.seen';
    }
    public function broadcastWith(): array
    {

        $chat = Chats::with(['messages' => function ($query) {
            $query->latest(); // sort messages with latest first
        }])->findOrFail($this->chatID);
        $latestMessage = $chat->messages->first();
        $fomattedSeen = $latestMessage?->getSeen()->with('user')->get()->map(function ($seen) {
            return [
                'id' => $seen->id,
                'userData' => [
                    'id' => $seen->user->id,
                    'imageUrl' => $seen->user->imageUrl,
                    'username' => $seen->user->username,
                ],
                'updated_at' => $seen->updated_at,
            ];
        });

        return [
            'seenusers' => $fomattedSeen ?? [],
            'chat_id' => $this->chatID,
        ];
    }
}
