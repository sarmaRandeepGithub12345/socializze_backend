<?php

namespace App\Http\Controllers;

use App\Models\FollowerFollowing;
use App\Models\Notifications;
use App\Models\Post;
use App\Models\User;
use App\Services\FirebaseNotificationService;
use App\Services\HelperService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class NotificationController extends Controller
{
    protected $helperService, $firebaseService;
    public function __construct(HelperService $helperService, FirebaseNotificationService $firebaseService)
    {
        $this->helperService = $helperService;
        $this->firebaseService = $firebaseService;
    }
    public function markAllSeen()
    {
        $userId = Auth::id();
        try {
            Notifications::where('user_id', $userId)
                ->where('seen', false)
                ->update(['seen' => true]);
            return HelperResponse('success', 'seen', 200);
        } catch (\Throwable $th) {
            return HelperResponse('error', $th->getMessage(), 500);
        }

        // Update all unseen notifications of the user
    }
    public function getNotifications()
    {
        try {
            $notifications = Notifications::where('user_id', Auth::user()->id)
                ->orderBy('updated_at', 'desc')
                ->paginate(10);
            $user = Auth::user();

            $notifications->transform(function ($notif) use ($user) {
                if ($notif->first_parent_type == 'App\\Models\\Post' && $notif->second_parent_type == 'App\\Models\\Like') {
                    $post = $notif->firstParent;
                    $presentPostLike = $post->likes()->where('user_id', '!=', $user->id)->get();

                    $countP = $presentPostLike->count();

                    $post_items = $post->singleFile->first();
                    if ($countP == 1) {
                        $userOne = $presentPostLike[0]->users;
                        return [
                            'id' => $notif->id,
                            'type' => 'post/like',
                            'users' => [
                                [
                                    'id' => $userOne->id,
                                    'username' => $userOne->username,
                                    'profile_picture' => $userOne->imageUrl,
                                ],
                            ],
                            'description' => ' liked your post: ' . $post->description,
                            'seen' => $notif->seen,
                            'post_picture' => $this->helperService->fullUrlTransform($post->isVideo == 0 ? $post_items->aws_link : $post_items->thumbnail),
                            'createdAt' => $notif->updated_at,
                        ];
                    } elseif ($countP >= 2) {
                        $userOne = $presentPostLike[0]->users;

                        $userTwo = $presentPostLike[1]->users;

                        $endDesc = ' liked your post: ';
                        $description = $countP == 2 ? $endDesc : ($countP == 3 ? $countP . ' other' . $endDesc : $countP . ' others' . $endDesc);

                        $post_items = $post->singleFile->first();
                        return [
                            'id' => $notif->id,

                            'type' => 'post/like',
                            'first_parent_id' => $notif->first_parent_id,
                            'second_parent_id' => $notif->second_parent_id,
                            'users' => [
                                [
                                    'id' => $userOne->id,
                                    'username' => $userOne->username,
                                    'profile_picture' => $userOne->imageUrl,
                                ],
                                [
                                    'id' => $userTwo->id,
                                    'username' => $userTwo->username,
                                    'profile_picture' => $userTwo->imageUrl,
                                ],
                            ],
                            'description' => $description . $post->description,
                            'seen' => $notif->seen,
                            'post_picture' => env('AWS_URL') . ($post->isVideo == 0 ? $post_items->aws_link : $post_items->thumbnail),
                            'createdAt' => $notif->updated_at,
                        ];
                    }
                } elseif ($notif->first_parent_type == 'App\\Models\\Post' && $notif->second_parent_type == 'App\\Models\\Comment') {
                    $post = $notif->firstParent;
                    $comment = $notif->secondParent;
                    $userInfo = $comment->user;
                    $post_items = $post->singleFile->first();
                    return [
                        'id' => $notif->id,

                        'type' => 'post/comment',
                        'first_parent_id' => $notif->first_parent_id,
                        'second_parent_id' => $notif->second_parent_id,
                        'users' => [
                            [
                                'id' => $userInfo->id,
                                'username' => $userInfo->username,
                                'profile_picture' => $userInfo->imageUrl,
                            ],
                        ],
                        'description' => ' commented on your post: ' . $comment->content,
                        'seen' => $notif->seen,
                        'post_picture' => env('AWS_URL') . ($post->isVideo == 0 ? $post_items->aws_link : $post_items->thumbnail),
                        'createdAt' => $notif->created_at,
                    ];
                } elseif ($notif->first_parent_type == 'App\\Models\\FollowerFollowing') {
                    //A is auth user
                    //B follows A
                    //B is follower and A if followed(ing)
                    $follower = User::find($notif->firstParent->follower_id);
                    $isFollowed = FollowerFollowing::where('follower_id', Auth::user()->id)
                        ->where('following_id', $notif->firstParent->follower_id)
                        ->exists();
                    return [
                        'id' => $notif->id,
                        'first_parent_id' => $notif->first_parent_id,
                        'second_parent_id' => $notif->second_parent_id,

                        'type' => 'follow',
                        'users' => [
                            [
                                'id' => $notif->firstParent->follower_id, //$follower,
                                'username' => $follower->username, //$follower->username,
                                'profile_picture' => $follower->imageUrl, //$follower->imageUrl,
                                'name' => $follower->name,
                                'isFollowed' => $isFollowed,
                            ],
                        ],
                        'description' => ' has started following you',
                        'seen' => $notif->seen,
                        'post_picture' => null,
                        'createdAt' => $notif->created_at,
                    ];
                } elseif ($notif->first_parent_type == 'App\\Models\\Comment' && $notif->second_parent_type == 'App\\Models\\Like') {
                    $comment = $notif->firstParent;
                    $presentCommmentLike = $comment->likes()->where('user_id', '!=', $user->id)->get();

                    $countP = $presentCommmentLike->count();

                    $post_items = $comment->inceptor->singleFile->first();
                    if ($countP == 1) {
                        $userOne = $presentCommmentLike[0]->users;
                        return [
                            'id' => $notif->id,
                            'type' => 'post/comment/like',
                            'users' => [
                                [
                                    'id' => $userOne->id,
                                    'username' => $userOne->username,
                                    'profile_picture' => $userOne->imageUrl,
                                ],
                            ],
                            'description' => ' liked your comment: ' . $comment->content,
                            'seen' => $notif->seen,
                            'post_picture' => env('AWS_URL') . ($comment->inceptor->isVideo == 0 ? $post_items->aws_link : $post_items->thumbnail),
                            'createdAt' => $notif->updated_at,
                        ];
                    } elseif ($countP >= 2) {
                        $userOne = $presentCommmentLike[0]->users;

                        $userTwo = $presentCommmentLike[1]->users;

                        $endDesc = ' liked your comment: ';
                        $description = $countP == 2 ? $endDesc : ($countP == 3 ? $countP . ' other' . $endDesc : $countP . ' others' . $endDesc);

                        $post_items = $comment->inceptor->singleFile->first();
                        return [
                            'id' => $notif->id,

                            'type' => 'post/comment/like',
                            'first_parent_id' => $notif->first_parent_id,
                            'second_parent_id' => $notif->second_parent_id,
                            'users' => [
                                [
                                    'id' => $userOne->id,
                                    'username' => $userOne->username,
                                    'profile_picture' => $userOne->imageUrl,
                                ],
                                [
                                    'id' => $userTwo->id,
                                    'username' => $userTwo->username,
                                    'profile_picture' => $userTwo->imageUrl,
                                ],
                            ],
                            'description' => $description . $comment->content,
                            'seen' => $notif->seen,
                            'post_picture' => env('AWS_URL') . ($comment->inceptor->isVideo == 0 ? $post_items->aws_link : $post_items->thumbnail),
                            'createdAt' => $notif->updated_at,
                        ];
                    }
                } elseif ($notif->first_parent_type == 'App\\Models\\Comment' && $notif->second_parent_type == 'App\\Models\\Comment') {
                    $comment = $notif->secondParent;
                    $userInfo = $comment->user;
                    $post = $comment->inceptor;
                    $post_items = $post->singleFile[0];

                    return [
                        'id' => $notif->id,

                        'type' => 'comment/comment',
                        'first_parent_id' => $notif->first_parent_id,
                        'second_parent_id' => $notif->second_parent_id,
                        'users' => [
                            [
                                'id' => $userInfo->id,
                                'username' => $userInfo->username,
                                'profile_picture' => $userInfo->imageUrl,
                            ],
                        ],
                        'description' => ' replied to your comment: ' . $comment->content,
                        'seen' => $notif->seen,
                        'post_picture' => env('AWS_URL') . ($post->isVideo == 0 ? $post_items->aws_link : $post_items->thumbnail),
                        'createdAt' => $notif->created_at,
                    ];
                }
            });
            return HelperResponse('success', 'Notification', 200, $notifications);
        } catch (\Throwable $th) {
            return HelperResponse('error', $th->getMessage(), 422, $th);
        }
    }
    public function test()
    {
        $notifications = [];
        $user = Auth::user(); // Get the full user object, not just the ID
        $posts = Post::where('user_id', Auth::user()->id)->get();
        $countOfPosts = count($posts);
        $i = 0;

        //handling likes and comments of a posts

        while ($i < $countOfPosts) {
            $post = $posts[$i];
            $presentPostLike = $post->likes()->where('user_id', '!=', $user->id)->get();

            $countP = $presentPostLike->count();

            $post_items = $post->singleFile->first();

            //likesCount=1 OR
            if ($countP == 1) {
                $userOne = $presentPostLike[0]->users;

                $notifications[] = [
                    // 'likes_count' => count($presentPostLike),
                    'type' => 'like/post',
                    'users' => [
                        [
                            'id' => $userOne->id,
                            'username' => $userOne->username,
                            'profile_picture' => $userOne->imageUrl,
                        ],
                    ],
                    'description' => ' liked your post: ' . $post->description,
                    'post_picture' => $post->isVideo == 0 ? $post_items->aws_link : $post_items->thumbnail,
                    'createdAt' => $presentPostLike[0]->created_at,
                ];
            } elseif ($countP >= 2) {
                $userOne = $presentPostLike[0]->users;

                $userTwo = $presentPostLike[1]->users;

                $endDesc = ' liked your post: ';
                $description = $countP == 2 ? $endDesc : ($countP == 3 ? $countP . ' other' . $endDesc : $countP . ' others' . $endDesc);

                $post_items = $post->singleFile->first();

                $notifications[] = [
                    'type' => 'like/post',
                    // 'likes_count' => count($presentPostLike),
                    'users' => [
                        [
                            'id' => $userOne->id,
                            'username' => $userOne->username,
                            'profile_picture' => $userOne->imageUrl,
                        ],
                        [
                            'id' => $userTwo->id,
                            'username' => $userTwo->username,
                            'profile_picture' => $userTwo->imageUrl,
                        ],
                    ],
                    'description' => $description . $post->description,

                    'post_picture' => $post->isVideo == 0 ? $post_items->aws_link : $post_items->thumbnail,
                    'createdAt' => $presentPostLike[0]->created_at,
                ];
            }

            $comments = $post
                ->comments()
                ->where('user_id', '!=', Auth::user()->id)
                ->get();
            $commentCount = count($comments);

            $j = 0;

            while ($j < $commentCount) {
                $comment = $comments[$j];

                $userInfo = $comment->users;

                $notifications[] = [
                    'type' => 'comment',
                    'users' => [
                        [
                            'id' => $userInfo->id,
                            'username' => $userInfo->username,
                            'profile_picture' => $userInfo->imageUrl,
                        ],
                    ],
                    'description' => ' commented on your post: ' . $comment->content,
                    'post_picture' => $post->isVideo == 0 ? $post_items->aws_link : $post_items->thumbnail,
                    'createdAt' => $comment->created_at,
                ];
                $j++;
            }
            $i++;
        }

        $followers = $user->followers;
        $i = 0;
        $followerCount = count($followers);
        while ($i < $followerCount) {
            $follower = $followers[$i];
            $isFollowed = FollowerFollowing::where('follower_id', Auth::user()->id)
                ->where('following_id', $follower->id)
                ->exists();
            $notifications[] = [
                'type' => 'follow',
                'users' => [
                    [
                        'id' => $follower->id,
                        'username' => $follower->username,
                        'profile_picture' => $follower->imageUrl,
                        'isFollowed' => $isFollowed,
                    ],
                ],
                'description' => ' has started following you',
                'post_picture' => null,
                'createdAt' => $follower->created_at,
            ];
            $i++;
        }
        $comments = $user->comments;
        $j = 0;
        while ($j < count($comments)) {
            $comment = $comments[$j];
            $post = $comment->posts;
            $presentCommentLike = $comment->likes()->where('user_id', '!=', $user->id)->get();
            //like count of comment
            $countP = $presentCommentLike->count();
            if ($countP == 1) {
                $userOne = $presentCommentLike[0]->users;
                $notifications[] = [
                    'type' => 'like/comment',
                    'users' => [
                        [
                            'id' => $userOne->id,
                            'username' => $userOne->username,
                            'profile_picture' => $userOne->imageUrl,
                        ],
                    ],
                    'description' => ' liked your comment: ' . $comment->content,
                    'post_picture' => $post->isVideo == 0 ? $post->singleFile[0]->aws_link : $post->singleFile[0]->thumbnail,
                    'createdAt' => $presentCommentLike[0]->created_at,
                ];
            } elseif ($countP >= 2) {
                $userOne = $presentCommentLike[0]->users;
                $userTwo = $presentCommentLike[1]->users;
                $endDesc = ' liked your comment: ' . $comment->content;
                $description = $countP == 2 ? $endDesc : ($countP == 3 ? $countP . ' other' . $endDesc : $countP . ' others' . $endDesc);
                $notifications[] = [
                    'type' => 'like/comment',
                    // 'likes_count' => count($presentPostLike),
                    'users' => [
                        [
                            'id' => $userOne->id,
                            'username' => $userOne->username,
                            'profile_picture' => $userOne->imageUrl,
                        ],
                        [
                            'id' => $userTwo->id,
                            'username' => $userTwo->username,
                            'profile_picture' => $userTwo->imageUrl,
                        ],
                    ],
                    'description' => $description,

                    'post_picture' => $post->isVideo == 0 ? $post->singleFile[0]->aws_link : $post->singleFile[0]->thumbnail,
                    'createdAt' => $presentCommentLike[0]->created_at,
                ];
            }
            $j += 1;
        }
        $notifications = collect($notifications)->sortByDesc('createdAt')->values()->paginate(10);
        return HelperResponse('success', 'Comment', 200, $notifications);
    }
    public function saveDeviceToken(Request $request)
    {
        try {
            $validation = Validator::make($request->all(), [
                'device_token' => 'required|String',
                //  'reply_id'=>'required|uuid|exists:users,id',
            ]);
            if ($validation->fails()) {
                return HelperResponse('error', $validation->errors()->first(), 422, $validation->errors()->messages());
            }
            $user = User::where('email', Auth::user()->email)->first();

            $user->deviceToken = $request->device_token . ' br ' . $user->id;
            $user->save();
            $notifications = Notifications::where('user_id', $user->id)->where('seen', false)->get();
            $notifications->each(function ($notif) use ($user) {
                if ($notif->first_parent_type == 'App\\Models\\Post' && $notif->second_parent_type == 'App\\Models\\Like') {
                    $post = $notif->firstParent;
                    $presentPostLike = $post->likes()->where('user_id', '!=', $user->id)->get();

                    $countP = $presentPostLike->count();

                    $post_items = $post->singleFile->first();
                    if ($countP == 1) {
                        $userOne = $presentPostLike[0]->users;

                        if ($notif->seen == false && $user->deviceToken != null) {
                            $text = $post->description;
                            $desc = ': ' . ($text != null && strlen($text) > 20) ? substr($text, 0, 20) . '...' : $text;
                            $files = $post->singleFile;
                            $thumbnail = $files[0]['thumbnail'];
                            $awsLink = $files[0]['aws_link'];
                            $isVideo = $post->isVideo;
                            $postPic = $isVideo
                                ? $thumbnail
                                : (!empty($thumbnail) ? $thumbnail : $awsLink);

                            $parts = $this->helperService->breakDeviceToken($user->deviceToken);
                            $this->firebaseService->likeCommentNotification($parts[0], 'Socializze', $countP . ' person liked your post' . ($text == null ? '' : $desc), $this->helperService->fullUrlTransform($postPic),);
                        }
                    } elseif ($countP >= 2) {
                        $userOne = $presentPostLike[0]->users;

                        $userTwo = $presentPostLike[1]->users;

                        $endDesc = ' liked your post: ';
                        $description = $countP == 2 ? $endDesc : ($countP == 3 ? $countP . ' other' . $endDesc : $countP . ' others' . $endDesc);

                        $post_items = $post->singleFile->first();

                        if ($notif->seen == false && $user->deviceToken != null) {
                            $text = $post->description;
                            $desc = ': ' . ($text != null && strlen($text) > 20) ? substr($text, 0, 20) . '...' : $text;
                            $files = $post->singleFile;
                            $thumbnail = $files[0]['thumbnail'];
                            $awsLink = $files[0]['aws_link'];
                            $isVideo = $post->isVideo;
                            $postPic = $isVideo
                                ? $thumbnail
                                : (!empty($thumbnail) ? $thumbnail : $awsLink);

                            $parts = $this->helperService->breakDeviceToken($user->deviceToken);
                            $this->firebaseService->likeCommentNotification($parts[0], 'Socializze', $countP . ' people liked your post' . ($text == null ? '' : $desc),  $this->helperService->fullUrlTransform($postPic));
                        }
                    }
                } elseif ($notif->first_parent_type == 'App\\Models\\Post' && $notif->second_parent_type == 'App\\Models\\Comment') {
                    $post = $notif->firstParent;
                    $comment = $notif->secondParent;
                    $userInfo = $comment->user;
                    $post_items = $post->singleFile->first();

                    if ($notif->seen == false && $user->deviceToken != null) {
                        $files = $post->singleFile;
                        $thumbnail = $files[0]['thumbnail'];
                        $awsLink = $files[0]['aws_link'];
                        $isVideo = $post->isVideo;
                        $postPic = $isVideo
                            ? $thumbnail
                            : (!empty($thumbnail) ? $thumbnail : $awsLink);
                        // $postPic = $post->isVideo == 1 ?  $post->singleFile[0]['thumbnail'] : $post->singleFile[0]['aws_link'];
                        $username = $comment->user->username;
                        $profileImage = $comment->user->imageUrl;

                        $parts = $this->helperService->breakDeviceToken($user->deviceToken);
                        $this->firebaseService->madeCommentNotification($parts[0], $username, ' commented on your post: ' . $comment->content, $this->helperService->fullUrlTransform($postPic), $profileImage);
                    }
                } elseif ($notif->first_parent_type == 'App\\Models\\FollowerFollowing') {
                    //A is auth user
                    //B follows A
                    //B is follower and A if followed(ing)
                    $follower = User::find($notif->firstParent->follower_id);
                    $isFollowed = FollowerFollowing::where('follower_id', Auth::user()->id)
                        ->where('following_id', $notif->firstParent->follower_id)
                        ->exists();

                    if ($notif->seen == false && $user->deviceToken != null) {
                        $username = $follower->username;
                        $profileImage = $follower->imageUrl;

                        $parts = $this->helperService->breakDeviceToken($user->deviceToken);
                        $this->firebaseService->likeCommentNotification($parts[0], $username, ' started following you', $profileImage);
                    }
                } elseif ($notif->first_parent_type == 'App\\Models\\Comment' && $notif->second_parent_type == 'App\\Models\\Like') {
                    $comment = $notif->firstParent;
                    $presentCommmentLike = $comment->likes()->where('user_id', '!=', $user->id)->get();

                    $countP = $presentCommmentLike->count();

                    $post_items = $comment->inceptor->singleFile->first();
                    if ($countP == 1) {
                        $userOne = $presentCommmentLike[0]->users;

                        if ($notif->seen == false && $user->deviceToken != null) {
                            $post = $comment->inceptor;
                            $files = $post->singleFile;
                            $thumbnail = $files[0]['thumbnail'];
                            $awsLink = $files[0]['aws_link'];
                            $isVideo = $post->isVideo;
                            $postPic = $isVideo
                                ? $thumbnail
                                : (!empty($thumbnail) ? $thumbnail : $awsLink);


                            $picture =
                                $text = $comment->content;
                            $desc = ': ' . ($text != null && strlen($text) > 20) ? substr($text, 0, 20) . '...' : $text;

                            $parts = $this->helperService->breakDeviceToken($user->deviceToken);
                            $this->firebaseService->likeCommentNotification($parts[0], 'Socializze', $countP . ' person liked your comment: ' . ($text == null ? '' : $desc),  $this->helperService->fullUrlTransform($postPic));
                        }
                    } elseif ($countP >= 2) {
                        $userOne = $presentCommmentLike[0]->users;

                        $userTwo = $presentCommmentLike[1]->users;

                        $endDesc = ' liked your comment: ';
                        $description = $countP == 2 ? $endDesc : ($countP == 3 ? $countP . ' other' . $endDesc : $countP . ' others' . $endDesc);

                        $post_items = $comment->inceptor->singleFile->first();

                        if ($notif->seen == false && $user->deviceToken != null) {
                            $post = $comment->inceptor;
                            $files = $post->singleFile;
                            $thumbnail = $files[0]['thumbnail'];
                            $awsLink = $files[0]['aws_link'];
                            $isVideo = $post->isVideo;
                            $postPic = $isVideo
                                ? $thumbnail
                                : (!empty($thumbnail) ? $thumbnail : $awsLink);;

                            $text = $comment->content;
                            $desc = ': ' . ($text != null && strlen($text) > 20) ? substr($text, 0, 20) . '...' : $text;

                            $parts = $this->helperService->breakDeviceToken($user->deviceToken);
                            $this->firebaseService->likeCommentNotification($parts[0], 'Socializze', $countP . ' people liked your comment: ' . ($text == null ? '' : $desc),  $this->helperService->fullUrlTransform($postPic));
                        }
                    }
                } elseif ($notif->first_parent_type == 'App\\Models\\Comment' && $notif->second_parent_type == 'App\\Models\\Comment') {
                    // $pComment = $notif->firstParent;
                    $comment = $notif->secondParent;
                    $userInfo = $comment->user;
                    $post = $comment->inceptor;

                    if ($notif->seen == false && $user->deviceToken != null) {

                        $files = $post->singleFile;
                        $thumbnail = $files[0]['thumbnail'];
                        $awsLink = $files[0]['aws_link'];
                        $isVideo = $post->isVideo;
                        $postPic = $isVideo
                            ? $thumbnail
                            : (!empty($thumbnail) ? $thumbnail : $awsLink);
                        $username = $userInfo->username;
                        $profileImage = $userInfo->imageUrl;


                        $parts = $this->helperService->breakDeviceToken($user->deviceToken);
                        $this->firebaseService->madeCommentNotification($parts[0], $username, ' replied on your comment: ' . $comment->content, $this->helperService->fullUrlTransform($postPic), $profileImage);
                    }
                }
            });

            return HelperResponse('success', 'Device token saved', 201);
        } catch (\Throwable $th) {
            return HelperResponse('error', $th->getMessage(), 422, $th);
        }
    }
}
