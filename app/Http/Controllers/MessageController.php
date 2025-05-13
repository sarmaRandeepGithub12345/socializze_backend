<?php

namespace App\Http\Controllers;


use App\Events\MessageSent;
use App\Events\test;
use App\Events\UserSeenMessageEvent;
use App\Models\ChatParticipants;
use App\Models\Chats;
use App\Models\FollowerFollowing;
use App\Models\Messages;
use App\Models\User;
use App\Services\HelperService;
use DateTime;
use Google\Service\Analytics\UserRef;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\Console\Helper\Helper;

class MessageController extends Controller
{
    protected $helperService;

    public function __construct(HelperService $helperService)
    {
        $this->helperService = $helperService;
    }
    public function sendInitialMessage(Request $request)
    {
        //return $request;
        $request->merge([
            'is_group' => filter_var($request->input('is_group'), FILTER_VALIDATE_BOOLEAN),
        ]);
        $validation = Validator::make($request->all(), [
            'receiver_ids' => 'required|array', // Ensure it's an array
            'receiver_ids.*' => 'uuid|exists:users,id',
            'chat_id' => 'uuid|exists:chats,id',
            'type' => 'required|integer',
            'is_group' => 'required|boolean',
            'chat_name' => 'string|min:0|max:20',
            'text_message' => 'string|min:1|max:2000',
        ]);

        if ($validation->fails()) {
            return HelperResponse('error', $validation->errors()->first(), 422, $validation->errors()->messages());
        }

        try {
            $chat = null;
            if ($request->chat_id) {
                // $chat = Chats::where('is_group',$request->is_group)->whereHas('chatparticipants', function($query) use ($allids) {
                //     $query->whereIn('user_id', $allids);
                // })->distinct()->first();
                $chat = Chats::find($request->chat_id);
            }
            $loggeduser = Auth::user();
            //if chat not present
            if (!$chat) {

                $chat = Chats::create([
                    'is_group' => $request->is_group,
                    'chat_name' => $request->is_group == true ? $request->chat_name : null,
                ]);

                $allparticipants = array_merge([$loggeduser->id], $request->receiver_ids);

                foreach ($allparticipants as $participants_id) {
                    //if participant id == logged id and group == true ?"admin" else "member"
                    $checkIfAdmin = ($participants_id == $loggeduser->id && $request->is_group == true) ? 'admin' : 'member';
                    $checkStatus = $participants_id != $loggeduser->id ? 'pending' : 'accepted';
                    // $checkchatParticipant = ChatParticipants::where('user_id',$participants_id)->where('chat_id',$chat->id)->exists();
                    ChatParticipants::create([
                        'user_id' => $participants_id,
                        'chat_id' => $chat->id,
                        'role' => $checkIfAdmin,
                        'status' => $checkStatus,
                    ]);
                }
            }

            $message = Messages::create([
                'sender_id' => $loggeduser->id,
                'chat_id' => $chat->id,
                'message' => $request->text_message,
                'is_missed_call' => false,
                'media_type' => $request->type,
            ]);
            $message->makeSeen();
            //broadcast
            // broadcast(new MessageSent($message));
            //echo-private:chat-channel.{user_id},MessageSent; for listening        

            // broadcast(new test("Hello"));
            //MessageSent::broadcast($message)->toOthers();
            //main
            MessageSent::dispatch($message);
            UserSeenMessageEvent::dispatch($chat->id);

            return HelperResponse('success', 'Chat created', 201, [
                'chat_id' => $chat->id,
                'message' => $message,
                'is_group' => $chat->is_group,
            ]);
        } catch (\Throwable $th) {
            return HelperResponse('error', $th->getMessage(), 422);
        }
    }

    public function getChatsVariable(Request $request)
    {
        try {
            $validation = Validator::make($request->all(), [
                'status' => 'required|string|in:rejected,pending,accepted', // Ensure it's an
            ]);
            if ($validation->fails()) {
                return HelperResponse('error', $validation->errors()->first(), 422, $validation->errors()->messages());
            }

            $loggeduser = Auth::user();
            // User::find(Auth::user()->id);

            // Paginate chat participants with 'pending' status and eager load 'chats'
            //  $chats = Chats::where('is_group',$request->is_group)->whereHas('chatparticipants', function($query) use ($loggeduser,$request) {
            //         $query->where('user_id', $loggeduser->id)->where('status',$request->status);
            //     })->paginate(10);

            $chatParticipants = $this->getSelectedChatType($request->status);

            // Now $chatParticipants contains paginated chats with transformed data
            return HelperResponse('success', 'chats found', 200, $chatParticipants);
        } catch (\Throwable $th) {
            return HelperResponse("error", $th->getMessage(), 422);
        }
    }
    public function getSelectedChatType($variable)
    {
        try {
            $loggeduser = Auth::user();

            $chatParticipants = $loggeduser
                ->chatparticipants()
                ->where('status', $variable)
                ->select('chat_participants.*')
                ->join('chats', 'chats.id', '=', 'chat_participants.chat_id')
                ->leftJoin('messages', function ($join) {
                    $join->on('messages.chat_id', '=', 'chats.id')
                        ->where('messages.created_at', function ($query) {
                            $query->select('created_at')
                                ->from('messages')
                                ->whereColumn('chat_id', 'chats.id')
                                ->latest()
                                ->take(1);
                        });
                })
                ->orderBy('messages.created_at', 'desc')
                ->with(['chats' => function ($query) {
                    $query->with('latestMessage');
                }])
                ->paginate(10); // Paginate the chat participants
            // $chatParticipants = $loggeduser
            //     ->chatparticipants()
            //     ->where('status', $variable)
            //     ->with(['chats' => function ($query) {
            //         $query->with(['latestMessage' => function ($q) {
            //             $q->latest()->first(); // or some latest order
            //         }]);
            //     }])
            //     ->orderByDesc(
            //         ChatParticipants::select('created_at')
            //             ->whereColumn('chat_participants.chat_id', 'chats.id')
            //             ->latest()
            //             ->limit(1)
            //     )
            //     ->paginate(10);
            // Transform the paginated result
            $chatParticipants->getCollection()->transform(function ($chatParticipant) use ($loggeduser, $variable,) {
                // Get the chat related to this chat participant
                $chat = $chatParticipant->chats;

                // Get all recipients (other users in the chat)
                $recipients = ChatParticipants::where('chat_id', $chat->id)
                    ->where('user_id', '!=', $loggeduser->id) // Exclude the logged-in user
                    ->with('users') // Load the related user for each participant
                    ->get()
                    ->map(function ($participant) {
                        return [
                            'id' => $participant->users->id,
                            'username' => $participant->users->username,
                            'name' => $participant->users->name,
                            'imageUrl' => $participant->users->imageUrl,
                            'status' => $participant->status,
                            'role' => $participant->role,
                        ];
                    });
                $unseenCount = $chat->messages()
                    ->where('sender_id', '!=', $loggeduser->id)
                    ->whereDoesntHave('getSeen', function ($query) use ($loggeduser) {
                        $query->where('user_id', $loggeduser->id);
                    })
                    ->count();
                $latestMessages = $chat
                    ->messages() // Get the messages for this chat
                    ->with('sender') // Load the sender for each message
                    ->orderBy('created_at', 'desc') // Sort messages by newest first
                    ->orderBy('id', 'desc')
                    // ->paginate(23)
                    ->take(23) // Only get the latest 3 messages
                    ->get()
                    ->map
                    // ->through
                    (function ($message) use ($loggeduser) {
                        // Transform the message into a custom structure
                        $seenusers =  $message->getSeen()
                            // ->where('user_id', '!=', $loggeduser->id)
                            ->with(['user'])->get()->map(function ($seen) {
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
                            'sender' => [
                                'sender_id' => $message->sender->id, // Get the sender's ID
                                'username' => $message->sender->username, // Get the sender's username
                                'imageUrl' => $message->sender->imageUrl, // Get the sender's image URL
                            ],
                            'chat_id' => $message->chat_id,
                            'message' => $message->message,
                            'is_missed_call' => $message->is_missed_call,
                            'media_type' => $message->media_type,

                            // Get the content of the message

                            'created_at' => $message->created_at, // Format the time
                            'updated_at' => $message->updated_at, // Format the time
                            'seenusers' => $seenusers,
                        ];
                    });


                return [
                    'recipients' => $recipients,
                    'id' => $chat->id,
                    'is_group' => $chat->is_group,
                    'groupIcon' => $chat->groupIcon,
                    'chat_name' => $chat->chat_name,
                    'unread_messages' =>  $unseenCount,
                    'created_at' => $chat->created_at,
                    'updated_at' => $chat->updated_at,
                    'lastMessages' => $latestMessages,

                ];
            });
            return $chatParticipants;
        } catch (\Throwable $th) {
            return $th;
        }
    }
    public function getAllChats()
    {
        try {
            $user = Auth::user();
            $chatParticipantAccepted = $this->getSelectedChatType("accepted");
            $chatParticipantPending = $this->getSelectedChatType("pending");

            // Now $chatParticipants contains paginated chats with transformed data
            return HelperResponse('success', 'chats found', 200, [
                "accepted" => $chatParticipantAccepted,
                "pending" => $chatParticipantPending,
                'unseenMessageCount' => $this->helperService->getMessageCount($user->id,),

            ]);
        } catch (\Throwable $th) {
            return HelperResponse("error", $th->getMessage(), 422);
        }
    }
    public function searchuser(Request $request)
    {
        $validation = Validator::make($request->all(), [
            'search' => 'required|string',
        ]);
        if ($validation->fails()) {
            return HelperResponse('error', $validation->errors()->first(), 422, $validation->errors()->messages());
        }
        try {
            $search = $request->search;
            $loggeduser = Auth::user();

            $users = User::where('id', '!=', Auth::user()->id) // Exclude the authenticated user
                ->where(function ($query) use ($search) {
                    $query->where('username', 'LIKE', "%$search%")->orWhere('name', 'LIKE', "%$search%");
                })
                ->withCount(['followers', 'posts'])
                ->paginate(3);
            $users->transform(function ($user) use ($loggeduser) {
                // $followStatus = FollowerFollowing::where('follower_id', $loggeduser->id)
                //     ->where('following_id', $user->id)
                //     ->first();
                // $allids = array_merge([$loggeduser->id], [$user->id]);
                // $followedStatus = FollowerFollowing::where('following_id', $loggeduser->id)
                //     ->where('follower_id', $user->id)
                //     ->first();
                $loggedUserId = $loggeduser->id;
                $otherUserId = $user->id;

                $chat = Chats::where('is_group', false)
                    ->whereHas(
                        'chatparticipants',
                        function ($query) use ($loggedUserId, $otherUserId) {
                            $query->whereIn('user_id', [$loggedUserId, $otherUserId]);
                        },
                        '=',
                        2,
                    ) // Ensure exactly 2 participants match the criteria
                    ->first();
                $latestMessages = [];
                $unseenCount = 0;
                if ($chat) {
                    $chatParticipant = $chat->chatparticipants()->where('user_id', $otherUserId)->first();
                    $latestMessages = $chat
                        ->messages() // Get the messages for this chat
                        ->with('sender') // Load the sender for each message
                        ->orderBy('created_at', 'desc') // Sort messages by newest first
                        ->take(3) // Only get the latest 3 messages
                        ->get()
                        ->map(function ($message) {
                            // Transform the message into a custom structure
                            $seenusers = $message->getSeen()->get()->map(function ($seen) {

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
                                'sender' => [
                                    'sender_id' => $message->sender->id, // Get the sender's ID
                                    'username' => $message->sender->username, // Get the sender's username
                                    'imageUrl' => $message->sender->imageUrl, // Get the sender's image URL
                                ],
                                'chat_id' => $message->chat_id,
                                'message' => $message->message,
                                'is_missed_call' => $message->is_missed_call,
                                'media_type' => $message->media_type,
                                'seenusers' => $seenusers,
                                // Get the content of the message

                                'created_at' => $message->created_at, // Format the time
                                'updated_at' => $message->updated_at, // Format the time
                            ];
                        });

                    $unseenCount = $chat->messages()
                        ->where('sender_id', '!=', $loggeduser->id)
                        ->whereDoesntHave('getSeen', function ($query) use ($loggeduser) {
                            $query->where('user_id', $loggeduser->id);
                        })
                        ->count();
                }


                return [
                    'recipients' => [
                        [
                            'id' => $user->id,
                            'username' => $user->username,
                            'name' => $user->name,
                            'imageUrl' => $user->imageUrl,
                            'role' => 'member',
                            'status' => $chat != null ? $chatParticipant->status : 'accepted',
                        ],
                    ],

                    'id' => $chat ? $chat->id : null,
                    'is_group' => $chat ? $chat->is_group : 0,
                    'groupIcon' => null,
                    'chat_name' => null,
                    'unread_messages' =>  $unseenCount,
                    'created_at' => $chat ? $chat->created_at : Carbon::now(),
                    'updated_at' => $chat ? $chat->updated_at : Carbon::now(),
                    'lastMessages' => $latestMessages,
                ];
            });
            return HelperResponse('success', 'Users found', 200, $users);
        } catch (\Throwable $th) {
            return HelperResponse('error', $th->getMessage(), 422);
        }
    }
    public function acceptChatRequest(Request $request)
    {
        $validation = Validator::make($request->all(), [
            'chat_id' => 'uuid|exists:chats,id',
            'status' => 'required|string|in:rejected,pending,accepted',
        ]);

        if ($validation->fails()) {
            return HelperResponse('error', $validation->errors()->first(), 422, $validation->errors()->messages());
        }

        // Find the participant based on the current user's ID and chat_id
        $participant = ChatParticipants::where('user_id', Auth::user()->id)
            ->where('chat_id', $request->chat_id)
            ->first();

        if ($participant) {
            $authUser = Auth::user();
            // Update the status and save the participant
            $participant->status = $request->status;
            $participant->save();

            Messages::where('chat_id', $request->chat_id)
                // ->where('sender_id', '!=', $authUser->id)
                ->whereDoesntHave(
                    'getSeen',
                    function ($query) use ($authUser) {
                        $query->where('user_id', $authUser->id);
                    }
                )
                ->get()
                ->each(function ($message) {
                    $message->makeSeen();
                });
            UserSeenMessageEvent::dispatch($request->chat_id);
            return HelperResponse('success', 'Status updated successfully', 200);
        } else {
            return HelperResponse('error', 'Participant not found', 404);
        }
    }
    public function seeMessages(Request $request)
    {

        $validation = Validator::make($request->all(), [
            'chat_id' => 'uuid|exists:chats,id',
        ]);
        if ($validation->fails()) {
            return HelperResponse('error', $validation->errors()->first(), 422, $validation->errors()->messages());
        }
        try {
            $authUser = Auth::user();
            $chatParticipant = ChatParticipants::where('chat_id', $request->chat_id)->get();
            if (count($chatParticipant) == 2) {
                $checkPending = $chatParticipant->where('user_id', '!=', $authUser->id)->where('status', 'pending')->count();
                if ($checkPending == 1) {
                    return HelperResponse("error", "Chat request pending", 422);
                }
            }
            Messages::where('chat_id', $request->chat_id)
                // ->where('sender_id', '!=', $authUser->id)
                ->whereDoesntHave(
                    'getSeen',
                    function ($query) use ($authUser) {
                        $query->where('user_id', $authUser->id);
                    }
                )
                ->get()
                ->each(function ($message) {
                    $message->makeSeen();
                });

            UserSeenMessageEvent::dispatch($request->chat_id);

            return HelperResponse("success", "See granted", 200,);
        } catch (\Throwable $th) {
            return HelperResponse('error', $th->getMessage(), 422, $th);
        }
    }
    public function fetchChatsByID(Request $request)
    {
        $validation = Validator::make($request->all(), [
            'chat_id' => 'uuid|exists:chats,id',
        ]);

        if ($validation->fails()) {
            return HelperResponse('error', $validation->errors()->first(), 422, $validation->errors()->messages());
        }

        try {

            $loggeduser = Auth::user();
            $chat = Chats::find($request->chat_id);
            $recipients = ChatParticipants::where('chat_id', $chat->id)
                ->where('user_id', '!=', $loggeduser->id) // Exclude the logged-in user
                ->with('users') // Load the related user for each participant
                ->get()
                ->map(function ($participant) {
                    return [
                        'id' => $participant->users->id,
                        'username' => $participant->users->username,
                        'name' => $participant->users->name,
                        'imageUrl' => $participant->users->imageUrl,
                        'status' => $participant->status,
                        'role' => $participant->role,
                    ];
                });
            $unseenCount = $chat->messages()
                ->where('sender_id', '!=', $loggeduser->id)
                ->whereDoesntHave('getSeen', function ($query) use ($loggeduser) {
                    $query->where('user_id', $loggeduser->id);
                })
                ->count();
            $latestMessages = $chat
                ->messages() // Get the messages for this chat
                ->with('sender') // Load the sender for each message
                ->orderBy('created_at', 'desc') // Sort messages by newest first
                ->take(23) // Only get the latest 3 messages
                ->get()
                ->map(function ($message) use ($loggeduser) {
                    // Transform the message into a custom structure
                    $seenusers =  $message->getSeen()
                        // ->where('user_id', '!=', $loggeduser->id)
                        ->with(['user'])->get()->map(function ($seen) {
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
                        'sender' => [
                            'sender_id' => $message->sender->id, // Get the sender's ID
                            'username' => $message->sender->username, // Get the sender's username
                            'imageUrl' => $message->sender->imageUrl, // Get the sender's image URL
                        ],
                        'chat_id' => $message->chat_id,
                        'message' => $message->message,
                        'is_missed_call' => $message->is_missed_call,
                        'media_type' => $message->media_type,

                        // Get the content of the message

                        'created_at' => $message->created_at, // Format the time
                        'updated_at' => $message->updated_at, // Format the time
                        'seenusers' => $seenusers,
                    ];
                });




            $responseChat = [
                'recipients' => $recipients,
                'id' => $chat->id,
                'is_group' => $chat->is_group,
                'groupIcon' => $chat->groupIcon,
                'chat_name' => $chat->chat_name,
                'unread_messages' =>  $unseenCount,
                'created_at' => $chat->created_at,
                'updated_at' => $chat->updated_at,
                'lastMessages' => $latestMessages,

            ];
            return HelperResponse('success', 'Chat found', 200, [
                'responseChat' => $responseChat,
            ]);
        } catch (\Throwable $th) {
            return HelperResponse('error', $th->getMessage(), '422');
        }
    }
    public function deleteParticularChat(Request $request)
    {
        $validation = Validator::make($request->all(), [
            'chat_id' => 'uuid|exists:chats,id',
        ]);

        if ($validation->fails()) {
            return HelperResponse('error', $validation->errors()->first(), 422, $validation->errors()->messages());
        }
        try {
            $chat = Chats::find($request->chat_id);
            $messages = $chat->messages;
            foreach ($messages as $message) {
                // Delete all user_seens related to this message
                $message->getSeen()->delete();
                $message->delete();
            }
            $chat->chatparticipants()->delete();
            $chat->delete();
            return HelperResponse('success', 'Chat deleted', 200);
        } catch (\Throwable $th) {
            return HelperResponse('error', $th->getMessage(), 422);
        }
    }
    public function createFounderMessages()
    {

        try {
            $chat = $this->helperService->messageFromCreator();
            return $chat;
        } catch (\Throwable $th) {
            return $th->getMessage();
        }
    }
    public function findChatByParticipants(Request $request)
    {

        $validation = Validator::make($request->all(), [
            'p_id' => 'uuid|exists:users,id',
        ]);
        if ($validation->fails()) {
            return HelperResponse('error', $validation->errors()->first(), 422, $validation->errors()->messages());
        }
        try {

            $loggeduser = Auth::user();

            $participants_ids = [$loggeduser->id, $request->p_id];
            $chat =  Chats::where('is_group', 0)
                ->whereHas('chatparticipants', function ($query) use ($loggeduser) {
                    $query->where('user_id', $loggeduser->id);
                })
                ->whereHas('chatparticipants', function ($query) use ($request) {
                    $query->where('user_id', $request->p_id);
                })
                ->withCount('chatparticipants')
                ->having('chatparticipants_count', '=', 2)
                ->first();

            if ($chat != null) {
                $recipients = ChatParticipants::where('chat_id', $chat->id)
                    ->where('user_id', '!=', $loggeduser->id) // Exclude the logged-in user
                    ->with('users') // Load the related user for each participant
                    ->get()
                    ->map(function ($participant) {
                        return [
                            'id' => $participant->users->id,
                            'username' => $participant->users->username,
                            'name' => $participant->users->name,
                            'imageUrl' => $participant->users->imageUrl,
                            'status' => $participant->status,
                            'role' => $participant->role,
                        ];
                    });
                $unseenCount = $chat->messages()
                    ->where('sender_id', '!=', $loggeduser->id)
                    ->whereDoesntHave('getSeen', function ($query) use ($loggeduser) {
                        $query->where('user_id', $loggeduser->id);
                    })
                    ->count();
                $latestMessages = $chat
                    ->messages() // Get the messages for this chat
                    ->with('sender') // Load the sender for each message
                    ->orderBy('created_at', 'desc') // Sort messages by newest first
                    ->take(23) // Only get the latest 3 messages
                    ->get()
                    ->map(function ($message) use ($loggeduser) {
                        // Transform the message into a custom structure
                        $seenusers =  $message->getSeen()
                            // ->where('user_id', '!=', $loggeduser->id)
                            ->with(['user'])->get()->map(function ($seen) {
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
                            'sender' => [
                                'sender_id' => $message->sender->id, // Get the sender's ID
                                'username' => $message->sender->username, // Get the sender's username
                                'imageUrl' => $message->sender->imageUrl, // Get the sender's image URL
                            ],
                            'chat_id' => $message->chat_id,
                            'message' => $message->message,
                            'is_missed_call' => $message->is_missed_call,
                            'media_type' => $message->media_type,

                            // Get the content of the message

                            'created_at' => $message->created_at, // Format the time
                            'updated_at' => $message->updated_at, // Format the time
                            'seenusers' => $seenusers,
                        ];
                    });

                $chat = [
                    'recipients' => $recipients,
                    'id' => $chat->id,
                    'is_group' => $chat->is_group,
                    'groupIcon' => $chat->groupIcon,
                    'chat_name' => $chat->chat_name,
                    'unread_messages' =>  $unseenCount,
                    'created_at' => $chat->created_at,
                    'updated_at' => $chat->updated_at,
                    'lastMessages' => $latestMessages,
                ];
            }
            return HelperResponse('success', "chat search done", 200, [
                'chat'  => $chat,
            ]);
        } catch (\Throwable $th) {
            return HelperResponse('error', $th->getMessage(), 422);
        }
    }
    public function getRemainingMessages(Request $request)
    {
        $validation = Validator::make($request->all(), [
            'chat_id' => 'uuid|exists:chats,id',
        ]);
        if ($validation->fails()) {
            return HelperResponse('error', $validation->errors()->first(), 422, $validation->errors()->messages());
        }
        try {
            $messages = Messages::where('chat_id', $request->chat_id)
                ->orderBy('created_at', 'desc')
                ->paginate(23)
                ->through(function ($message) {
                    $seenusers =  $message->getSeen()
                        // ->where('user_id', '!=', $loggeduser->id)
                        ->with(['user'])->get()->map(function ($seen) {
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
                        'sender' => [
                            'sender_id' => $message->sender->id, // Get the sender's ID
                            'username' => $message->sender->username, // Get the sender's username
                            'imageUrl' => $message->sender->imageUrl, // Get the sender's image URL
                        ],
                        'chat_id' => $message->chat_id,
                        'message' => $message->message,
                        'is_missed_call' => $message->is_missed_call,
                        'media_type' => $message->media_type,

                        // Get the content of the message

                        'created_at' => $message->created_at, // Format the time
                        'updated_at' => $message->updated_at,
                        'seenusers' => $seenusers,
                    ];
                });
            return HelperResponse('success', 'Messages found', 200, [
                'current_page' => $messages->currentPage(),
                'last_page' => $messages->lastPage(),
                'per_page' => $messages->perPage(),
                'total' => $messages->total(),
                'data' => $messages->items(),
            ]);
        } catch (\Throwable $th) {
            return HelperResponse('error', $th->getMessage(), 422,);
        }
    }
}
