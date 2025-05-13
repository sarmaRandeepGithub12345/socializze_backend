<?php

namespace App\Events;

use Illuminate\Support\Facades\Auth;

use App\Models\ChatParticipants;
use App\Models\Chats;
use App\Models\Messages;
use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Auth as FacadesAuth;

class MessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public $message;
    public function __construct($message)
    {
        $this->message = $message;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {

        $participants = ChatParticipants::where('chat_id', $this->message->chat_id)->pluck('user_id');

        foreach ($participants as $userId) {
            $channels[] = new PrivateChannel('user.' . $userId);
        }
        return $channels;
        // return [
        //     new PrivateChannel('chat.9eb66a73-cadb-4dfa-bcda-ad8bd8fb90ca'),
        // ];
    }
    public function broadcastAs(): string
    {
        return 'message.sent';
    }
    public function broadcastWith(): array
    {
        // $channels = [];

        // $participants = ChatParticipants::where('chat_id', $this->message->chat_id)->pluck('user_id');

        // foreach ($participants as $userId) {
        //     $channels[] = new PrivateChannel('chat.' . $userId);
        // }
        // $loggeduser = Auth::user();

        $user = User::where('id', $this->message->sender_id)->select('id', 'username', 'imageUrl')->first();
        $checkChatOrRequest = ChatParticipants::where('chat_id', $this->message->chat_id)
            ->where('status', "pending")
            ->whereHas('chats', function ($query) {
                return $query->where('is_group', false);
            })->count();
        return [
            //1=>chat/2->request
            'check_chat_or_request' => $checkChatOrRequest > 0 ? 2 : 1,
            'sender' => [
                'sender_id' => $user->id, // Get the sender's ID
                'username' => $user->username, // Get the sender's username
                'imageUrl' => $user->imageUrl, // Get the sender's image URL
            ],
            'chat_id' => $this->message->chat_id,
            'message' => $this->message->message,
            'is_missed_call' => $this->message->is_missed_call == false ? 0 : 1,
            'media_type' => $this->message->media_type == 0 || $this->message->media_type == "0" ? 0 : 1,
            'seenusers' => [],
            'created_at' => $this->message->created_at, // Format the time
            'updated_at' => $this->message->updated_at,
        ];
    }
}
