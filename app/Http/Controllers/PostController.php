<?php

namespace App\Http\Controllers;

use App\Events\NewPostPing;
use Illuminate\Http\Request;
use App\Models\FollowerFollowing;
use App\Models\Like;
use App\Models\Notifications;
use App\Models\Post;
use App\Models\User;
use App\Models\VideoViews;
use App\Services\FirebaseNotificationService;
use App\Services\HelperService;
use Faker\Extension\Helper;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class PostController extends Controller
{
    protected $helperService;
    protected $firebaseService;
    public function __construct(HelperService $helperService, FirebaseNotificationService $firebaseService)
    {
        $this->helperService = $helperService;
        $this->firebaseService = $firebaseService;
    }
    public function getUserDetails($id)
    {
        try {
            return  User::where('id', $id)
                ->with(['bankAccount', 'phoneN'])
                ->withCount(['following', 'followers', 'posts'])

                ->first();
        } catch (\Throwable $th) {
            return $th;
        }
    }
    // public function finalUser($user)
    // {
    //     try {
    //         $country_code = optional($user->phoneN)->country_code;
    //         $phone = optional($user->phoneN)->phone;

    //         return [
    //             'id' => $user->id,
    //             'name' => $user->name,
    //             'username' => $user->username,
    //             'email' => $user->email,
    //             'imageUrl' => $user->imageUrl,
    //             'description' => $user->description,
    //             'created_at' => $user->created_at,
    //             'updated_at' => $user->updated_at,
    //             'posts_count' => $user->posts_count,
    //             'following_count' => $user->following_count,
    //             'followers_count' => $user->followers_count,
    //             'phone' => $country_code && $phone ? strval($country_code . $phone) : null,
    //             'bank_account' => $user->bankAccount,
    //         ];
    //     } catch (\Throwable $th) {
    //         return $th;
    //     }
    // }

    public function updateViews(Request $request)
    {
        $validation = Validator::make($request->all(), [
            'post_id' => 'required|uuid|exists:posts,id',
            'views' => 'required|numeric|min:0',
        ]);

        if ($validation->fails()) {
            return HelperResponse('error', $validation->errors()->first(), 422, $validation->errors()->messages());
        }

        $checkView = VideoViews::where('post_id', $request->post_id)->where('user_id', Auth::user()->id)->first();
        // return $request->views;
        if ($checkView != null) {
            $checkView->view_count = $request->views;
            $checkView->save();
        } else {
            $checkView = VideoViews::create([
                'user_id' => Auth::user()->id,
                'post_id' => $request->post_id,
                'views_count' => $request->views,
            ]);
        }
        return HelperResponse('success', "Views updated", 200);
    }
    public function test(Request $request)
    {

        try {
            $directory = 'posts';
            $files = $request->file('files');
            $uploadedFiles = [];

            foreach ($files as $file) {
                // Create a unique filename
                $filename = uniqid() . '.' . $file->getClientOriginalExtension();

                // Store the file (adjust disk to 's3', 'public', or as needed)
                $path = $file->storeAs($directory, $filename, 's3'); // change 's3' to 'public' if using local

                // Get the file URL
                $url = Storage::url($path);

                $uploadedFiles[] = [
                    'aws_link' => $url,
                ];
            }

            return $uploadedFiles;
        } catch (\Throwable $th) {
            return response()->json([
                'error' => $th->getMessage()
            ], 500);
        }
    }
    public function getLikes(Request $request)
    {
        $validation = Validator::make($request->all(), [
            'post_id' => 'required|uuid|exists:posts,id',
        ]);
        // 'files' => 'required|array',
        // 'files.*' => 'file|max:512000', // Max 500MB per file (in kilobytes)

        // 'thumbnails' => 'required|array',
        // 'thumbnails.*' => 'file|max:10240', // Max 10MB per thumbnail (example)


        if ($validation->fails()) {
            return HelperResponse('error', $validation->errors()->first(), 422, $validation->errors()->messages());
        }

        try {
            $likes = Like::where('likeable_id', $request->post_id)->paginate(10);
            $likes->getCollection()->transform(function ($like) {

                return [
                    'id' => $like->users->id,
                    'username' => $like->users->username,
                    'imageUrl' => $like->users->imageUrl,
                    'name' => $like->users->name,
                ];
            });
            return HelperResponse("success", "likes", 200, [
                'likes' => $likes,
            ]);
        } catch (\Throwable $th) {
            return HelperResponse("error", $th->getMessage(), 422,);
        }
    }
    public function makeAPost(Request $request)
    {
        try {
            $validation = Validator::make($request->all(), [
                'description' => 'string|min:0|max:1000',
                'files' => 'required|array',
                'files.*' => 'file|max:204800',
                'thumbnails' => 'array',
                'thumbnails.*' => 'file|max:204800',
                'shorts' => 'required|integer',
            ]);
            // 'files' => 'required|array',
            // 'files.*' => 'file|max:512000', // Max 500MB per file (in kilobytes)

            // 'thumbnails' => 'required|array',
            // 'thumbnails.*' => 'file|max:10240', // Max 10MB per thumbnail (example)


            if ($validation->fails()) {
                return HelperResponse('error', $validation->errors()->first(), 422, $validation->errors()->messages());
            }

            $filesToUpload = $request->file('files');
            $thumbNails = $request->file('thumbnails');

            // if ($request->shorts == 1) {
            $paths = $this->helperService->uploadFilesAnThumbnail($filesToUpload, $thumbNails, 'posts');
            return $this->commonFileUpload($paths, $request->description, $request->shorts,);
            // } 
            // else {
            //     $paths = $this->helperService->awsAdd($filesToUpload, 'posts/');

            //     return $this->commonFileUpload($paths, $request->description, $request->shorts);
            // }
        } catch (\Throwable $th) {
            return HelperResponse('error', $th->getMessage(), 422, $th);
        }
    }
    public function commonFileUpload($paths, $description, $shorts)
    {
        try {
            if (!is_array($paths)) {
                return HelperResponse('error', 'File upload failed', 500, ['message:' . $paths->getMessage(), 'paths' => $paths,],);
            }
            if (empty($paths) || !isset($paths)) {
                return HelperResponse('error', 'File upload failed.', 500);
            }
            $authuser = Auth::user();

            $newPost = Post::create([
                'description' => $description,
                'user_id' => $authuser->id,
                'isVideo' => count($paths) == 1 && str_ends_with($paths[0]['aws_link'], '.mp4'),
            ]);

            foreach ($paths as $path) {

                $newPost->uploadFile($path['aws_link'], $path['thumbnail'] == '' ? null : $path['thumbnail'], 1);
            }
            $newPost
                ->singleFile()
                ->get()
                ->map(function ($file) {
                    return [
                        'aws_link' => $file['aws_link'],
                        'thumbnail' => $file['thumbnail'],
                        'mediaType' => 1,
                    ];
                });
            //new post ping    
            $followerIDs = $authuser->followers()->pluck('follower_id')->toArray();

            NewPostPing::dispatch($followerIDs);

            return HelperResponse(
                'success',
                'Post successfully created',
                201,
                // [
                //     'description' => $newPost->description,
                //     'postLinks' => $files,
                //     'user_id' => $newPost->user_id,
                // ]
            );
        } catch (\Throwable $th) {
            return HelperResponse('error', $th->getMessage(), 422, $th);
        }
    }

    public function getPosts(Request $request)
    {
        //api/post/get-posts?page=1
        //api/post/get-posts?page=2

        try {
            //code...


            $authUser = Auth::user();

            $userId = $authUser->id;
            //Get following users' IDs
            $followingIds = $authUser->following()->pluck('following_id')->toArray();
            $postuserIds = array_merge([$userId], $followingIds);
            $followingCommentedIds = Post::whereNotIn('user_id', $followingIds)
                ->with(['comments'])
                ->whereHas(
                    'comments',
                    function ($query) use ($followingIds) {
                        return $query->whereIn('user_id', $followingIds)->whereNull('replied_to_id');
                    }
                )
                ->pluck('user_id')
                ->toArray();
            $finalIds = array_merge($postuserIds, $followingCommentedIds);

            $posts = Post::whereIn('user_id', $finalIds)
                ->with(['user', 'likes', 'views', 'singleFile'])
                ->withCount(['likes', 'comments'])
                ->orderBy('created_at', 'desc')
                ->orderBy('id', 'desc')
                ->paginate(10)
                ->through(function ($post) use ($authUser) {
                    $isFollowed = FollowerFollowing::where('follower_id', $authUser->id)
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
            if (count($posts) == 0) {
                $posts = Post::with(['user', 'likes', 'views', 'singleFile',])
                    ->withCount(['likes', 'comments'])
                    // ->inRandomOrder()
                    ->orderBy('created_at', 'desc')
                    ->paginate(3)
                    ->through(function ($post) use ($authUser) {
                        // $isFollowed = FollowerFollowing::where('follower_id', $authUser->id)
                        //     ->where('following_id', $post->user->id)
                        //     ->exists();

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
                                'isFollowed' => false,
                            ],
                            'likes_count' => $post->likes_count,
                            'comments_count' => $post->comments_count,
                            'has_liked' => $hasLiked,
                            'views_count' => $post->views->sum('view_count'),
                            'created_at' => $post->created_at,
                            'updated_at' => $post->updated_at,
                        ];
                    });
            }

            return HelperResponse('success', 'Post', 200, $posts);
        } catch (\Throwable $th) {
            return HelperResponse('error', $th->getMessage(), 422);
        }
    }
    public function deletePost(Request $request)
    {
        $validation = Validator::make($request->all(), [
            'id' => 'required|uuid|exists:posts,id',
        ]);
        if ($validation->fails()) {
            return HelperResponse('error', $validation->errors()->first(), 422, $validation->errors()->messages());
        }
        $post = Post::find($request->id);
        if ($post != null) {
            $this->helperService->awsDelete(json_decode($post->postLinks));
            $post->delete();

            return HelperResponse('success', 'Post deleted successfully', 200);
        }
        return HelperResponse('error', 'Post not found', 422);
    }
    public function clickPostProfile(Request $request)
    {
        $validation = Validator::make($request->all(), [
            'id' => 'required|uuid|exists:users,id',
            'logged_id' => 'uuid|exists:users,id',
        ]);

        if ($validation->fails()) {
            return HelperResponse('error', $validation->errors()->first(), 422, $validation->errors()->messages());
        }
        $user = $this->getUserDetails($request->id);
        if ($request->logged_id) {
            $checkFollow = FollowerFollowing::where('follower_id', $request->logged_id)
                ->where('following_id', $request->id)
                ->exists();
        }

        return HelperResponse('success', 'User found', 200, [
            'userData' => $this->helperService->finalUser($user),

            'isFollowed' => $request->logged_id && $checkFollow ? true : false,
        ]);
    }
    public function getPostsUser(Request $request)
    {
        $validation = Validator::make($request->all(), [
            'id' => 'required|uuid|exists:users,id',
            'logged_id' => 'uuid|exists:users,id',
        ]);

        if ($validation->fails()) {
            return HelperResponse('error', $validation->errors()->first(), 422, $validation->errors()->messages());
        }
        $posts = Post::where('user_id', $request->id)
            ->with(['views'])
            ->withCount(['likes', 'comments'])
            ->orderBy('created_at', 'desc')
            ->paginate(12);

        $posts->transform(function ($post) use ($request) {
            $files = $post->singleFile->map(function ($file) {
                return [
                    'aws_link' =>  $this->helperService->fullUrlTransform($file->aws_link),
                    'thumbnail' =>  $this->helperService->fullUrlTransform($file->thumbnail),
                ];
            });

            return [
                'id' => $post->id,
                'isVideo' => $post->isVideo,

                'description' => $post->description,
                'postLinks' => $files,
                'created_at' => $post->created_at,
                'updated_at' => $post->updated_at,
                'has_liked' => $request->logged_id != '' ? $post->likes->contains('user_id', $request->logged_id) : false,
                'views_count' => $post->views->sum('view_count'),
                'likes_count' => $post->likes_count,
                'comments_count' => $post->comments_count,
            ];
        });
        return HelperResponse('success', 'Logged user posts', 200, $posts);
    }
    public function likeAPost(Request $request)
    {
        $validation = Validator::make($request->all(), [
            'post_id' => 'required|uuid|exists:posts,id',
        ]);

        if ($validation->fails()) {
            return HelperResponse('error', $validation->errors()->first(), 422, $validation->errors()->messages());
        }
        try {
            $user = Auth::user();
            $post = Post::find($request->post_id);
            $check = $post->isLiked();

            if ($check) {

                $newlike = $post->unlike();

                $post->loadCount('likes');
                //if like maker and post maker are same return 
                if ($newlike->user_id == $post->user_id) {
                    return HelperResponse('success', 'Post unliked', 200, [
                        'like_status' => false,
                        'likes' => $post->likes_count,
                    ]);
                }

                $likes = $post->likes_count;
                if ($likes == 0 || ($likes == 1 && $post->likes[0]->user_id == $post->user_id)) {
                    Notifications::where('first_parent_id', $post->id)->where('second_parent_type', get_class($newlike))->delete();
                }


                return HelperResponse('success', 'Post unliked', 200, [
                    'like_status' => false,
                    'likes' => $post->likes_count,
                ]);
            }

            $newlike = $post->like();
            $post->loadCount('likes');
            //liker and postmaker not same
            if ($user->id != $post->user_id) {
                $postOwner = $post->user;
                $text = $post->description;
                $desc = ": " . ($text != null && strlen($text) > 20) ? (substr($text, 0, 20) . '...') : $text;

                if ($postOwner->deviceToken != null) {
                    $deviceToken = $postOwner->deviceToken;
                    $parts = $this->helperService->breakDeviceToken($deviceToken);
                    $loggedUserPart = $this->helperService->breakDeviceToken(Auth::user()->deviceToken);

                    if ($parts[1] != Auth::user()->id && $parts[0] != $loggedUserPart[0]) {
                        $presentPostLike = $post->likes()->where('user_id', '!=', $user->id)->get();

                        $files = $post->singleFile;
                        $thumbnail = $files[0]['thumbnail'];
                        $awsLink = $files[0]['aws_link'];
                        $isVideo = $post->isVideo;
                        $postPic = $isVideo
                            ? $thumbnail
                            : (!empty($thumbnail) ? $thumbnail : $awsLink);

                        $countP = $presentPostLike->count();
                        $this->firebaseService->likeCommentNotification(
                            $parts[0],
                            'Socializze',
                            $countP . ' people liked your post' . ($text == null ? "" : $desc),
                            $this->helperService->fullUrlTransform($postPic),
                        );
                    }
                }
                $findNotification = Notifications::where('first_parent_id', $post->id)->where('second_parent_type', 'App/Models/Like')->first();

                if ($findNotification == null) {

                    Notifications::create([
                        'user_id' => $post->user_id,
                        'first_parent_id' => $post->id,
                        'first_parent_type' => get_class($post),
                        'second_parent_id' => $newlike->id,
                        'second_parent_type' => get_class($newlike),
                        'seen' => false,
                    ]);
                } else {

                    $findNotification->second_parent_id = $newlike->id;
                    $findNotification->seen = false;
                    $findNotification->save();
                }
            }
            return HelperResponse('success', 'Post liked', 200, [
                'like_status' => true,
                'likes' => $post->likes_count,
            ]);
        } catch (\Throwable $th) {
            return HelperResponse('error', $th->getMessage(), 422, $th);
        }
    }
    public function getPostsAdvance(Request $request)
    {
        //api/post/get-posts?page=1
        //api/post/get-posts?page=2

        $authUser = Auth::user();
        if (!$authUser) {
            return HelperResponse('error', 'User not authenticated', 401);
        }

        $userId = $authUser->id;
        //Get following users' IDs
        $followingIds = FollowerFollowing::where('follower_id', $userId)->pluck('following_id')->toArray();

        $postuserIds = array_merge([$userId], $followingIds);

        // posts of all users followed by postuserids
        $followerInteractedAndUploadedPosts = Post::whereIn('user_id', $postuserIds)
            ->orWhereIn('id', function ($query) use ($followingIds) {
                //get all post ids liked by followingIds
                //select method is used to specify the columns you want to retrieve from a database table
                $query
                    ->select('likeable_id')
                    ->from('likes')
                    ->where('likeable_type', Post::class)
                    ->whereIn('user_id', $followingIds)
                    ->union(
                        //get all post ids saved by followingIds
                        DB::table('saved_posts')->select('post_id')->whereIn('user_id', $followingIds),
                    )
                    ->union(
                        //get all post ids shared by followingIds
                        DB::table('shares')->select('post_id')->whereIn('user_id', $followingIds),
                    )
                    ->union(
                        //get all post ids commented by followingIds
                        DB::table('comments')->select('inceptor_id')->whereIn('user_id', $followingIds),
                    );
            })
            ->distinct() // Ensure distinct posts
            ->with(['user', 'likes', 'views', 'singleFile'])
            ->withCount(['likes', 'comments'])
            ->orderBy('created_at', 'desc')
            ->get();

        //1)get all posts that were liked,saved,commented,saved by Auth->user on
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
                        ->select('inceptor_id')
                        ->where('user_id', $authUser->id),
                );
        })->pluck('id');

        //get all users interacted with posts interacted by Auth->user
        $interacteduserIds = User::whereIn('id', function ($query) use ($interactedpostIds) {
            $query
                ->select('user_id')
                ->from('likes')
                ->where('likeable_type', Post::class)
                ->whereIn('likeable_id', $interactedpostIds)
                ->union(DB::table('shares')->select('user_id')->whereIn('post_id', $interactedpostIds))
                ->union(DB::table('saved_posts')->select('user_id')->whereIn('post_id', $interactedpostIds))
                ->union(DB::table('comments')->select('user_id')->whereIn('inceptor_id', $interactedpostIds));
        })->pluck('id');
        $finalInteractedposts = Post::whereIn('id', function ($query) use ($interacteduserIds) {
            //give me a post that has been liked/saved/shared/commented by $interacteduserIds
            $query
                ->select('likeable_id')
                ->from('likes')
                ->where('likeable_type', Post::class)
                ->whereIn('user_id', $interacteduserIds)
                ->union(DB::table('saved_posts')->select('post_id')->whereIn('user_id', $interacteduserIds))
                ->union(DB::table('shares')->select('post_id')->whereIn('user_id', $interacteduserIds))
                ->union(DB::table('comments')->select('inceptor_id')->whereIn('user_id', $interacteduserIds));
        })
            ->distinct()
            ->with(['user', 'views', 'singleFile'])
            ->withCount(['likes', 'comments'])
            ->orderBy('created_at', 'desc')
            ->get();

        $allPosts = $followerInteractedAndUploadedPosts->union($finalInteractedposts)->unique('id');

        // Paginate the combined result set
        $posts = $allPosts->paginate(2);

        $posts->transform(function ($post) use ($userId) {
            $isFollowed = FollowerFollowing::where('follower_id', $userId)
                ->where('following_id', $post->user->id)
                ->exists();

            $hasLiked = $post->likes->contains('user_id', Auth::user()->id);
            $files = $post->singleFile->map(function ($file) {
                return [
                    'aws_link' => $this->helperService->fullUrlTransform($file->aws_link),
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
        // echo 'Hello from Laravel Artisan Command!';
        return HelperResponse('success', 'Post', 200, $posts);
    }

    public function getReels(Request $request)
    {
        // Ensure user is authenticated
        $authUser = Auth::user();
        if (!$authUser) {
            return HelperResponse('error', 'User not authenticated', 401);
        }
        $userId = $authUser->id;
        // Get following users' IDs
        $followingIds = FollowerFollowing::where('follower_id', $userId)->pluck('following_id')->toArray();
        $postUserIds = array_merge([$userId], $followingIds);
        // Posts of users followed by postUserIds
        $followerInteractedAndUploadedPosts = Post::where('isVideo', 1)
            ->whereIn('user_id', $postUserIds)
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
            //->distinct() // Distinct posts
            ->inRandomOrder() // Apply random order
            ->with(['user', 'likes', 'views', 'singleFile'])
            ->withCount(['likes', 'comments'])
            ->get(); // Get all posts that were interacted with by the authenticated user
        $interactedPostIds = Post::where('isVideo', 1)
            ->whereIn('id', function ($query) use ($authUser) {
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
            })
            ->pluck('id');

        // Get all users interacted with posts by the authenticated user
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
        $finalInteractedPosts = Post::where('isVideo', 1)
            ->whereIn('id', function ($query) use ($interactedUserIds) {
                $query
                    ->select('likeable_id')
                    ->from('likes')
                    ->where('likeable_type', Post::class)
                    ->whereIn('user_id', $interactedUserIds)
                    ->union(DB::table('saved_posts')->select('post_id')->whereIn('user_id', $interactedUserIds))
                    ->union(DB::table('shares')->select('post_id')->whereIn('user_id', $interactedUserIds))
                    ->union(DB::table('comments')->select('inceptor_id')->whereIn('user_id', $interactedUserIds));
            })
            //->distinct()
            ->inRandomOrder() // Apply random order before getting the posts
            ->with(['user', 'views', 'singleFile'])
            ->withCount(['likes', 'comments'])
            ->get();

        $allPosts = $followerInteractedAndUploadedPosts->merge($finalInteractedPosts)->unique('id');
        $posts = $allPosts->paginate(2);
        $posts->getCollection()->transform(function ($post) use ($userId) {
            $isFollowed = FollowerFollowing::where('follower_id', $userId)
                ->where('following_id', $post->user->id)
                ->exists();
            $hasLiked = $post->likes->contains('user_id', $userId);
            $files = $post->singleFile->map(function ($file) {
                return [
                    'aws_link' =>  $this->helperService->fullUrlTransform($file->aws_link),
                    'thumbnail' =>   $this->helperService->fullUrlTransform($file->thumbnail),
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
                'total_view_count' => $post->views->sum('view_count'),
                'created_at' => $post->created_at,
                'updated_at' => $post->updated_at,
            ];
        });
        return HelperResponse('success', 'Post', 200, $posts);
    }
}
