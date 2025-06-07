<?php

namespace App\Http\Controllers;

use App\Models\FollowerFollowing;
use App\Models\Notifications;
use App\Models\Post;
use App\Models\User;
use App\Services\FirebaseNotificationService;
use Illuminate\Support\Facades\Validator;
use App\Services\HelperService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class RandomSearchController extends Controller
{
    protected $helperService;
    protected $firebaseService;
    public function __construct(HelperService $helperService, FirebaseNotificationService $firebaseService)
    {
        $this->helperService = $helperService;
        $this->firebaseService = $firebaseService;
    }
    //username,name,imageUrl,posts,followers,following,
    public function searchUsers(Request $request)
    {
        $validation = Validator::make($request->all(), [
            'search' => 'required|string',
            'type' => 'required|integer',
        ]);
        $search = $request->search;
        if ($validation->fails()) {
            return HelperResponse('error', $validation->errors()->first(), 422, $validation->errors()->messages());
        }
        $loggeduser = Auth::user();
        $users = [];
        if ($request->type == 1) {
            $users = User::where('username', 'LIKE', "%$search%")
                ->orWhere('name', 'LIKE', "%$search%")
                ->paginate(3);
            $users->transform(function ($user) {
                return [
                    'id' => $user->id,
                    'username' => $user->username,
                    'name' => $user->name,

                    'imageUrl' => $user->imageUrl,
                ];
            });
        } else {
            $users = User::where('id', '!=', Auth::user()->id) // Exclude the authenticated user
                ->where(function ($query) use ($search) {
                    $query->where('username', 'LIKE', "%$search%")->orWhere('name', 'LIKE', "%$search%");
                })
                ->withCount(['followers', 'posts'])
                ->paginate(3);
            $users->transform(function ($user) use ($loggeduser) {
                $followStatus = FollowerFollowing::where('follower_id', $loggeduser->id)
                    ->where('following_id', $user->id)
                    ->first();
                $followedStatus = FollowerFollowing::where('following_id', $loggeduser->id)
                    ->where('follower_id', $user->id)
                    ->first();

                return [
                    'id' => $user->id,
                    'username' => $user->username,
                    'name' => $user->name,
                    'followers' => $user->followers_count,
                    'follow_since' => $followStatus != null ? $followStatus->created_at : null,
                    'followed_since' => $followedStatus != null ? $followedStatus->created_at : null,
                    'posts' => $user->posts_count,
                    'imageUrl' => $user->imageUrl,
                ];
            });
        }

        return HelperResponse('success', 'Users found', 200, $users);
    }
    public function followFunct(Request $request)
    {
        $validation = Validator::make($request->all(), [
            'to_be_followed' => 'required|exists:users,id',
        ]);
        if ($validation->fails()) {
            return HelperResponse('error', $validation->errors()->first(), 422, $validation->errors()->messages());
        }

        $followerId = Auth::user()->id;
        $followingId = $request->to_be_followed;
        $user = User::find($followingId);


        $deviceToken = $user->deviceToken;
        if ($followerId == $request->to_be_followed) {
            return HelperResponse('error', 'Follower and Following same', 422, [
                'to_be_followed' => $request->to_be_followed,
                'followerId' => Auth::user()->id,
            ]);
        }

        $follow = FollowerFollowing::where('follower_id', Auth::user()->id)
            ->where('following_id', $followingId)
            ->first();

        if ($follow) {
            Notifications::where('user_id', $followingId)->where('first_parent_id', $follow->id)->delete();
            FollowerFollowing::where('follower_id', $followerId)->where('following_id', $followingId)->delete();
            if (Auth::user()->id != $user->id) {

                if ($deviceToken != null) {

                    $parts = $this->helperService->breakDeviceToken($deviceToken);
                    $loggedUserPart = $this->helperService->breakDeviceToken(Auth::user()->deviceToken);

                    if ($parts[1] != Auth::user()->id && $parts[0] != $loggedUserPart[0]) {
                        $this->firebaseService->unfollowUserNotification($parts[0], $followerId,);
                    } else if (count($loggedUserPart) < 2 && count($parts) == 2) {
                        // $this->firebaseService->sendNotification('fF4DP8vxQ7isdZrgxwG8qJ:APA91bF-KmA-7cE4V0VEUJuFjfWdSfPSK4QlSUKFuROS03gVVr-STkEuLWMtT4PYs2txAbP2boMX65x0Tw_GxD4WoPF-BnrWUHM1f1Ba02fAR9MDeHZbKKU', $username, ' started following you', $profileImage);     

                        $this->firebaseService->unfollowUserNotification($parts[0], $followerId,);
                    }
                }
            }
            return HelperResponse('success', 'User unfollowed', 200, [
                'status' => false,
            ]);
        }

        $record = FollowerFollowing::create([
            'follower_id' => Auth::user()->id,
            'following_id'   => $followingId,
        ]);



        $authUser = Auth::user(); // For performance and clarity

        $username = $authUser->username;
        $profileImage = $authUser->imageUrl;
        $userId = $authUser->id;
        $name = $authUser->name;

        // Check if the user (you just followed) already follows the current user (mutual follow check)
        $isFollowed = FollowerFollowing::where('follower_id', $user->id)
            ->where('following_id', $authUser->id)
            ->exists();
        if (Auth::user()->id != $user->id) {

            if ($deviceToken != null) {

                $parts = $this->helperService->breakDeviceToken($deviceToken);
                $loggedUserPart = $this->helperService->breakDeviceToken(Auth::user()->deviceToken);

                if ($parts[1] != Auth::user()->id && $parts[0] != $loggedUserPart[0]) {
                    $this->firebaseService->followUserNotification($parts[0], $username, ' started following you', $profileImage, $userId, $name, $isFollowed);
                } else if (count($loggedUserPart) < 2 && count($parts) == 2) {
                    // $this->firebaseService->sendNotification('fF4DP8vxQ7isdZrgxwG8qJ:APA91bF-KmA-7cE4V0VEUJuFjfWdSfPSK4QlSUKFuROS03gVVr-STkEuLWMtT4PYs2txAbP2boMX65x0Tw_GxD4WoPF-BnrWUHM1f1Ba02fAR9MDeHZbKKU', $username, ' started following you', $profileImage);     

                    $this->firebaseService->sendNotification($parts[0], $username, ' started following you', $profileImage);
                }
            }
        }
        Notifications::create([
            'user_id' => $user->id,
            'first_parent_type' => get_class($record),
            'first_parent_id' => $record->id
        ]);
        return HelperResponse('success', 'User followed', 200, [
            'status' => true,
        ]);
    }
    public function test(Request $request)
    {
        $videoPosts = collect([11, 41, 71, 81, 41, 31]);
        $otherPosts = collect([17, 20, 349, 39, 384]);

        $result = [];
        $batchSize = 5;

        // Organize posts in batches of 5 with the first post being a video post
        while (count($videoPosts) > 0 || count($otherPosts) > 0) {
            $batch = [];

            // Add a video post as the first post in the batch if available
            if (count($videoPosts) > 0) {
                $batch[] = $videoPosts->shift();
            }

            // Shuffle remaining posts to randomize selection
            $remainingPosts = $otherPosts->merge($videoPosts)->shuffle();

            // Add other posts to fill the batch
            while (count($batch) < $batchSize && count($remainingPosts) > 0) {
                $batch[] = $remainingPosts->shift();
            }

            if (count($batch) == $batchSize) {
                $result = array_merge($result, $batch);
            } else {
                break; // Stop if we cannot fill a complete batch
            }
        }
        return [$result];
    }

    public function getRandom2(Request $request)
    {
        $authUser = Auth::user();
        if (!$authUser) {
            return HelperResponse('error', 'User not authenticated', 401);
        }

        $userId = $authUser->id;
        // Get following users' IDs
        $followingIds = FollowerFollowing::where('follower_id', $userId)->pluck('following_id')->toArray();

        $postuserIds = array_merge([$userId], $followingIds);

        // Posts of all users followed by postuserIds
        $followerInteractedAndUploadedPosts = Post::whereIn('user_id', $postuserIds)
            ->orWhereIn('id', function ($query) use ($followingIds) {
                $query
                    ->select('likeable_id')
                    ->from('likes')
                    ->where('likeable_type', Post::class)
                    ->whereIn('user_id', $followingIds)
                    ->union(DB::table('saved_posts')->select('post_id')->whereIn('user_id', $followingIds))
                    ->union(DB::table('shares')->select('post_id')->whereIn('user_id', $followingIds))
                    ->union(DB::table('comments')->select('post_id')->whereIn('user_id', $followingIds));
            })
            ->distinct() // Ensure distinct posts
            ->with(['user', 'likes', 'views', 'singleFile'])
            ->withCount(['likes', 'comments'])
            ->orderBy('created_at', 'desc')
            ->get();

        // Get all posts that were liked, saved, commented, shared by Auth->user on
        $interactedpostIds = Post::whereIn('id', function ($query) use ($authUser) {
            $query
                ->select('likeable_id')
                ->from('likes')
                ->where('likeable_type', Post::class)
                ->where('user_id', $authUser->id)
                ->union(
                    DB::table('saved_posts')
                        ->select('post_id')
                        ->where('user_id', $authUser->id),
                )
                ->union(
                    DB::table('shares')
                        ->select('post_id')
                        ->where('user_id', $authUser->id),
                )
                ->union(
                    DB::table('comments')
                        ->select('post_id')
                        ->where('user_id', $authUser->id),
                );
        })->pluck('id');

        // Get all users interacted with posts interacted by Auth->user
        $interacteduserIds = User::whereIn('id', function ($query) use ($interactedpostIds) {
            $query
                ->select('user_id')
                ->from('likes')
                ->where('likeable_type', Post::class)
                ->whereIn('likeable_id', $interactedpostIds)
                ->union(DB::table('shares')->select('user_id')->whereIn('post_id', $interactedpostIds))
                ->union(DB::table('saved_posts')->select('user_id')->whereIn('post_id', $interactedpostIds))
                ->union(DB::table('comments')->select('user_id')->whereIn('post_id', $interactedpostIds));
        })->pluck('id');

        $finalInteractedposts = Post::whereIn('id', function ($query) use ($interacteduserIds) {
            $query
                ->select('likeable_id')
                ->from('likes')
                ->where('likeable_type', Post::class)
                ->whereIn('user_id', $interacteduserIds)
                ->union(DB::table('saved_posts')->select('post_id')->whereIn('user_id', $interacteduserIds))
                ->union(DB::table('shares')->select('post_id')->whereIn('user_id', $interacteduserIds))
                ->union(DB::table('comments')->select('post_id')->whereIn('user_id', $interacteduserIds));
        })
            ->distinct()
            ->with(['user', 'views', 'singleFile'])
            ->withCount(['likes', 'comments'])
            ->orderBy('created_at', 'desc')
            ->get();

        $allPosts = $followerInteractedAndUploadedPosts->union($finalInteractedposts)->unique('id');

        // Separate video posts and other posts
        $videoPosts = $allPosts->where('isVideo', true)->values();
        $otherPosts = $allPosts->where('isVideo', false)->values();

        $result = [];
        $batchSize = 5;

        // Organize posts in batches of 5 with the first post being a video post
        while (count($videoPosts) > 0 || count($otherPosts) > 0) {
            $batch = [];

            // Add a video post as the first post in the batch if available
            if (count($videoPosts) > 0) {
                $batch[] = $videoPosts->shift();
            }

            // Shuffle remaining posts to randomize selection
            $remainingPosts = $otherPosts->merge($videoPosts)->shuffle();

            // Add other posts to fill the batch
            while (count($batch) < $batchSize && count($remainingPosts) > 0) {
                $batch[] = $remainingPosts->shift();
            }

            if (count($batch) == $batchSize) {
                $result = array_merge($result, $batch);
            } else {
                break; // Stop if we cannot fill a complete batch
            }
        }

        // Paginate the combined result set
        $posts = collect($result)->paginate(5);

        $posts->transform(function ($post) use ($userId) {
            $isFollowed = FollowerFollowing::where('follower_id', $userId)
                ->where('following_id', $post->user->id)
                ->exists();
            $hasLiked = $post->likes->contains('user_id', Auth::user()->id);
            $files = $post->singleFile->map(function ($file) {
                return [
                    'aws_link' => $file->aws_link,
                    'thumbnail' => $file->thumbnail,
                ];
            });
            return [
                'id' => $post->id,
                'description' => $post->description,
                'isVideo' => $post->isVideo,
                'postLinks' => $files,
                'user' => [
                    'user_id' => $post->user->id,
                    'username' => $post->user->username,
                    'imageUrl' => $post->user->imageUrl,
                    'isFollowed' => $isFollowed,
                ],
                'likes_count' => $post->likes_count,
                'comments_count' => $post->comments_count,
                'has_liked' => $hasLiked,
                'views' => $post->views->sum('view_count'),
                'created_at' => $post->created_at,
                'updated_at' => $post->updated_at,
            ];
        });

        return HelperResponse('success', 'Post', 200, $posts);
    }
    public function getRandom(Request $request)
    {
        $authUser = Auth::user();
        if (!$authUser) {
            return HelperResponse('error', 'User not authenticated', 401);
        }

        $userId = $authUser->id;

        // Get following users' IDs
        $followingIds = FollowerFollowing::where('follower_id', $userId)->pluck('following_id')->toArray();

        $postuserIds = array_merge([$userId], $followingIds);

        // Posts uploaded by followed users or interacted with by followed users
        $followerInteractedAndUploadedPosts = Post::whereIn('user_id', $postuserIds)
            ->orWhereIn('id', function ($query) use ($followingIds) {
                $query
                    ->select('likeable_id')
                    ->from('likes')
                    ->where('likeable_type', Post::class)
                    ->whereIn('user_id', $followingIds)
                    ->union(DB::table('saved_posts')->select('post_id')->whereIn('user_id', $followingIds))
                    ->union(DB::table('shares')->select('post_id')->whereIn('user_id', $followingIds))
                    ->union(DB::table('comments')->select('inceptor_id')->whereIn('user_id', $followingIds));
            })
            ->distinct()
            ->with(['user', 'likes', 'views', 'singleFile'])
            ->withCount(['likes', 'comments'])
            ->orderBy('created_at', 'desc')
            ->get();

        // Get all posts that were interacted with by the authenticated user
        $interactedPostIds = Post::whereIn('id', function ($query) use ($authUser) {
            $query
                ->select('likeable_id')
                ->from('likes')
                ->where('likeable_type', Post::class)
                ->where('user_id', $authUser->id)
                ->union(
                    DB::table('saved_posts')
                        ->select('post_id')
                        ->where('user_id', $authUser->id),
                )
                ->union(
                    DB::table('shares')
                        ->select('post_id')
                        ->where('user_id', $authUser->id),
                )
                ->union(
                    DB::table('comments')
                        ->select('inceptor_id')
                        ->where('user_id', $authUser->id),
                );
        })->pluck('id');

        // Get users who interacted with posts the authenticated user interacted with
        $interactedUserIds = User::whereIn('id', function ($query) use ($interactedPostIds) {
            $query
                ->select('user_id')
                ->from('likes')
                ->where('likeable_type', Post::class)
                ->whereIn('likeable_id', $interactedPostIds)
                ->union(DB::table('shares')->select('user_id')->whereIn('post_id', $interactedPostIds))
                ->union(DB::table('saved_posts')->select('user_id')->whereIn('post_id', $interactedPostIds))
                ->union(DB::table('comments')->select('user_id')->whereIn('inceptor_id', $interactedPostIds));
        })->pluck('id');

        // Posts that interacted users interacted with
        $finalInteractedPosts = Post::whereIn('id', function ($query) use ($interactedUserIds) {
            $query
                ->select('likeable_id')
                ->from('likes')
                ->where('likeable_type', Post::class)
                ->whereIn('user_id', $interactedUserIds)
                ->union(DB::table('saved_posts')->select('post_id')->whereIn('user_id', $interactedUserIds))
                ->union(DB::table('shares')->select('post_id')->whereIn('user_id', $interactedUserIds))
                ->union(DB::table('comments')->select('inceptor_id')->whereIn('user_id', $interactedUserIds));
        })
            ->distinct()
            ->with(['user', 'views', 'singleFile'])
            ->withCount(['likes', 'comments'])
            ->orderBy('created_at', 'desc')
            ->get();

        $allPosts = $followerInteractedAndUploadedPosts->merge($finalInteractedPosts)->unique('id');

        // Separate video and other posts
        $videoPosts = $allPosts->where('isVideo', true)->values();
        $otherPosts = $allPosts->where('isVideo', false)->values();

        // Shuffle the video and non-video posts separately
        $videoPosts = $videoPosts->shuffle();
        $otherPosts = $otherPosts->shuffle();

        $result = [];
        $batchSize = 5;

        // Organize posts in batches of 5, prioritizing video posts
        $val = true;
        while (count($videoPosts) > 0 || count($otherPosts) > 0) {
            $batch = [];

            // Ensure large post placement alternates every batch
            for ($i = 0; $i < $batchSize; $i++) {
                if ($i == 0 && count($videoPosts) > 0 && $val) {
                    // Insert a video post at the start
                    $batch[] = $videoPosts->shift();
                } elseif ($i == 2 && count($videoPosts) > 0 && !$val) {
                    // Insert a video post at position 3
                    $batch[] = $videoPosts->shift();
                } elseif (count($otherPosts) > 0) {
                    // Insert other posts if available
                    $batch[] = $otherPosts->shift();
                }
            }

            // Flip $val to alternate large post placement
            $val = !$val;

            // Merge the batch into the result
            $result = array_merge($result, $batch);
        }

        // Paginate the combined result set before fetching the posts
        $paginatedPosts = collect($result)->paginate(30);

        // Transform posts for the response
        $paginatedPosts->transform(function ($post) use ($userId) {
            $isFollowed = FollowerFollowing::where('follower_id', $userId)
                ->where('following_id', $post->user->id)
                ->exists();
            $hasLiked = $post->likes->contains('user_id', Auth::user()->id);
            $files = $post->singleFile->map(function ($file) {
                return [
                    'aws_link' =>  $this->helperService->fullUrlTransform($file->aws_link),
                    'thumbnail' =>  $this->helperService->fullUrlTransform($file->thumbnail),
                ];
            });

            return [
                'id' => $post->id,
                'description' => $post->description,
                'isVideo' => $post->isVideo,
                'postLinks' => $files,
                'user' => [
                    'user_id' => $post->user->id,
                    'username' => $post->user->username,
                    'imageUrl' => $post->user->imageUrl,
                    'isFollowed' => $isFollowed,
                ],
                'likes_count' => $post->likes_count,
                'comments_count' => $post->comments_count,
                'has_liked' => $hasLiked,
                'views_count' => $post->views->sum('view_count'),
                'created_at' => $post->created_at,
                'updated_at' => $post->updated_at,
            ];
        });
        if ($paginatedPosts->isEmpty()) {
            $NotVidoes =
                Post::where('isVideo', 0)
                ->with(['user', 'views', 'singleFile'])
                ->withCount(['likes', 'comments'])
                ->paginate(24)
                ->through(function ($post) use ($userId) {
                    $isFollowed = FollowerFollowing::where('follower_id', $userId)
                        ->where('following_id', $post->user->id)
                        ->exists();

                    $hasLiked = $post->likes->contains('user_id', Auth::user()->id);

                    $files = $post->singleFile->map(function ($file) {
                        return [
                            'aws_link' =>  $this->helperService->fullUrlTransform($file->aws_link),
                            'thumbnail' =>  $this->helperService->fullUrlTransform($file->thumbnail),
                        ];
                    });
                    return [
                        'id' => $post->id,
                        'description' => $post->description,
                        'isVideo' => $post->isVideo,
                        'postLinks' => $files,
                        'user' => [
                            'user_id' => $post->user->id,
                            'username' => $post->user->username,
                            'imageUrl' => $post->user->imageUrl,
                            'isFollowed' => $isFollowed,
                        ],
                        'likes_count' => $post->likes_count,
                        'comments_count' => $post->comments_count,
                        'has_liked' => $hasLiked,
                        'views_count' => $post->views->sum('view_count'),
                        'created_at' => $post->created_at,
                        'updated_at' => $post->updated_at,
                    ];
                });
            $AreVideos =
                Post::where('isVideo', 1)
                ->with(['user', 'views', 'singleFile'])
                ->withCount(['likes', 'comments'])
                ->paginate(6)
                ->through(function ($post) use ($userId) {
                    $isFollowed = FollowerFollowing::where('follower_id', $userId)
                        ->where('following_id', $post->user->id)
                        ->exists();

                    $hasLiked = $post->likes->contains('user_id', Auth::user()->id);

                    $files = $post->singleFile->map(function ($file) {
                        return [
                            'aws_link' =>  $this->helperService->fullUrlTransform($file->aws_link),
                            'thumbnail' =>  $this->helperService->fullUrlTransform($file->thumbnail),
                        ];
                    });
                    return [
                        'id' => $post->id,
                        'description' => $post->description,
                        'isVideo' => $post->isVideo,
                        'postLinks' => $files,
                        'user' => [
                            'user_id' => $post->user->id,
                            'username' => $post->user->username,
                            'imageUrl' => $post->user->imageUrl,
                            'isFollowed' => $isFollowed,
                        ],
                        'likes_count' => $post->likes_count,
                        'comments_count' => $post->comments_count,
                        'has_liked' => $hasLiked,
                        'views_count' => $post->views->sum('view_count'),
                        'created_at' => $post->created_at,
                        'updated_at' => $post->updated_at,
                    ];
                });

            $NotVidoesCount = count($NotVidoes->items());
            $AreVideosCount = count($AreVideos->items());

            $i = 0;
            $j = 0;
            $k = 0;
            $total = $NotVidoesCount + $AreVideosCount;
            $resultPost = [];
            while ($k < $total && $i < $NotVidoesCount && $j < $AreVideosCount) {
                if ($k % 10 == 0 || $k % 10 == 7) {
                    $resultPost[] = $AreVideos[$j];
                    $j++;
                } else {
                    $resultPost[] = $NotVidoes[$i];
                    $i++;
                }
                $k++;
            }
            while ($i < $NotVidoesCount) {
                $resultPost[] = $NotVidoes[$i++];
            }
            while ($j < $AreVideosCount) {
                $resultPost[] = $AreVideos[$j++];
            }
            $paginatedPosts = [
                'data' => $resultPost,
                'total' => $AreVideos->total() + $NotVidoes->total(),
                'per_page' => 30,

            ];
        }

        return HelperResponse('success', 'Post', 200, $paginatedPosts);
    }
}
