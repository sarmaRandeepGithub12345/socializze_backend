<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\FollowerFollowing;
use App\Models\Post;
use App\Models\User;
use App\Models\VideoView;
use App\Models\VideoViews;
use App\Services\HelperService;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ReelsController extends Controller
{
    protected $helperService;
    public function __construct(HelperService $helperService)
    {
        $this->helperService = $helperService;
    }
    // public function updateView(Request $request){
    //     $validation = Validator::make($request->all(), [
    //         'post_id' => 'required|uuid|exists:posts,id',
    //     ]);
    //     if($validation->fails()){
    //         return HelperResponse('error',$validation->errors()->first(),422,$validation->errors()->messages());
    //     }
    //     $postId = $request->post_id;
    //     $userId = Auth::user()->id;
    //     $view= VideoViews::where('post_id',$postId)->where('user_id',$userId)->first();
    //     if($view){
    //         $view->increment('view_count');
    //         $count = VideoViews::where('post_id',$postId)->sum('view_count');
    //         return HelperResponse("success","View updated",201,[
    //             'total_view_count'=>$count
    //         ]);
    //     }
    //     VideoViews::create([
    //         'post_id'=>$request->post_id,
    //         'user_id'=>$userId,
    //         'view_count'=>1
    //     ]);
    //     $count = VideoViews::where('post_id',$postId)->sum('view_count');
    //     return HelperResponse("success","View created",200,[
    //         'total_view_count'=>$count
    //     ]);
    // }

    public function getReels()
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
            ->where(function ($query) use ($postUserIds, $followingIds) {
                $query->whereIn('user_id', $postUserIds)
                    ->orWhereIn('id', function ($subQuery) use ($followingIds) {
                        $subQuery->select('likeable_id')
                            ->from('likes')
                            ->where('likeable_type', Post::class)
                            ->whereIn('user_id', $followingIds)
                            ->union(
                                DB::table('saved_posts')
                                    ->select('post_id')
                                    ->whereIn('user_id', $followingIds)
                            )
                            ->union(
                                DB::table('shares')
                                    ->select('post_id')
                                    ->whereIn('user_id', $followingIds)
                            )
                            ->union(
                                DB::table('comments')
                                    ->select('inceptor_id')
                                    ->whereIn('user_id', $followingIds)
                            );
                    });
            })
            ->distinct()
            //->inRandomOrder()
            ->with(['user', 'likes', 'views', 'singleFile'])
            ->withCount(['likes', 'comments'])
            ->get();
        //return $followerInteractedAndUploadedPosts;
        // Get all posts that were interacted with by the authenticated user
        $interactedPostIds = Post::where('isVideo', 1)
            ->whereIn('id', function ($query) use ($authUser) {
                $query->select('likeable_id')
                    ->from('likes')
                    ->where('likeable_type', Post::class)
                    ->where('user_id', $authUser->id)
                    ->union(
                        DB::table('saved_posts')
                            ->select('post_id')
                            ->where('user_id', $authUser->id)
                    )
                    ->union(
                        DB::table('shares')
                            ->select('post_id')
                            ->where('user_id', $authUser->id)
                    )
                    ->union(
                        DB::table('comments')
                            ->select('inceptor_id')
                            ->where('user_id', $authUser->id)
                    );
            })->pluck('id');

        // Get all users interacted with posts by the authenticated user
        $interactedUserIds = User::whereIn('id', function ($query) use ($interactedPostIds) {
            $query->select('user_id')
                ->from('likes')
                ->where('likeable_type', Post::class)
                ->whereIn('likeable_id', $interactedPostIds)
                ->union(
                    DB::table('shares')
                        ->select('user_id')
                        ->whereIn('post_id', $interactedPostIds)
                )
                ->union(
                    DB::table('saved_posts')
                        ->select('user_id')
                        ->whereIn('post_id', $interactedPostIds)
                )
                ->union(
                    DB::table('comments')
                        ->select('user_id')
                        ->whereIn('inceptor_id', $interactedPostIds)
                );
        })->pluck('id');
        $finalInteractedPosts = Post::where('isVideo', 1)
            ->whereIn('id', function ($query) use ($interactedUserIds) {
                $query->select('likeable_id')
                    ->from('likes')
                    ->where('likeable_type', Post::class)
                    ->whereIn('user_id', $interactedUserIds)
                    ->union(
                        DB::table('saved_posts')
                            ->select('post_id')
                            ->whereIn('user_id', $interactedUserIds)
                    )
                    ->union(
                        DB::table('shares')
                            ->select('post_id')
                            ->whereIn('user_id', $interactedUserIds)
                    )
                    ->union(
                        DB::table('comments')
                            ->select('inceptor_id')
                            ->whereIn('user_id', $interactedUserIds)
                    );
            })
            ->distinct()
            //->inRandomOrder()
            ->with(['user', 'likes', 'views', 'singleFile'])
            ->withCount(['likes', 'comments'])

            ->get();

        $allPosts = $followerInteractedAndUploadedPosts->merge($finalInteractedPosts)->unique('id');

        $posts = $allPosts->paginate(2);
        $posts->transform(function ($post) use ($userId) {
            $isFollowed = FollowerFollowing::where('follower_id', $userId)->where('following_id', $post->user->id)->exists();
            $hasLiked = $post->likes->contains('user_id', $userId);
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
            $posts = Post::where('isVideo', 1)
                ->with(['user', 'likes', 'views', 'singleFile',])
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
        return HelperResponse('success', "Post", 200, $posts);
    }

    public function getLoggedUserReels(Request $request)
    {


        $validation = Validator::make($request->all(), [
            'id' => 'required|uuid|exists:users,id',
            'logged_id' => 'uuid|exists:users,id'
        ]);

        if ($validation->fails()) {
            return HelperResponse('error', $validation->errors()->first(), 422, $validation->errors()->messages());
        }
        $posts = Post::where('user_id', $request->id)->where('isVideo', 1)->with(['views'])->withCount(['likes', 'comments'])->orderBy('created_at', 'desc')
            ->paginate(9);


        $posts->transform(function ($post) use ($request) {
            $files = $post->singleFile->map(function ($file) {
                return [
                    'aws_link' =>  env('AWS_URL') . $file['aws_link'],
                    'thumbnail' =>  env('AWS_URL') . $file['thumbnail'],
                ];
            });
            return [
                "id" => $post->id,
                "isVideo" => $post->isVideo,

                "description" => $post->description,
                "postLinks" => $files,
                "created_at" => $post->created_at,
                "updated_at" => $post->updated_at,
                'has_liked' => $request->logged_id != "" ? $post->likes->contains('user_id', $request->logged_id) : false,
                'views_count' => $post->views->sum('view_count'),
                "likes_count" => $post->likes_count,
                "comments_count" => $post->comments_count,

            ];
        });
        return HelperResponse("success", "Logged user posts", 200, $posts);
    }

    // public function getReels(Request $request)
    // {
    //     // Ensure user is authenticated
    //     $authUser = Auth::user();
    //     if (!$authUser) {
    //         return HelperResponse('error', 'User not authenticated', 401);
    //     }

    //     $userId = $authUser->id;
    //     // Get following users' IDs
    //     $followingIds = FollowerFollowing::where('follower_id', $userId)->pluck('following_id')->toArray();

    //     $postUserIds = array_merge([$userId], $followingIds);

    //     // Posts of users followed by postUserIds
    //     $followerInteractedAndUploadedPosts = Post::where('isVideo', 1)
    //         ->whereIn('user_id', $postUserIds)
    //         ->orWhereIn('id', function ($query) use ($followingIds) {
    //             $query->select('likeable_id')
    //                 ->from('likes')
    //                 ->where('likeable_type', Post::class)
    //                 ->whereIn('user_id', $followingIds)
    //                 ->union(
    //                     DB::table('saved_posts')
    //                         ->select('post_id')
    //                         ->whereIn('user_id', $followingIds)
    //                 )
    //                 ->union(
    //                     DB::table('shares')
    //                         ->select('post_id')
    //                         ->whereIn('user_id', $followingIds)
    //                 )
    //                 ->union(
    //                     DB::table('comments')
    //                         ->select('post_id')
    //                         ->whereIn('user_id', $followingIds)
    //                 );
    //         })
    //         ->distinct()
    //         ->with(['user', 'likes'])
    //         ->withCount(['likes', 'comments'])
    //         ->orderBy('created_at', 'desc')
    //         ->get();

    //     // Get all posts that were interacted with by the authenticated user
    //     $interactedPostIds = Post::where('isVideo', 1)
    //         ->whereIn('id', function ($query) use ($authUser) {
    //             $query->select('likeable_id')
    //                 ->from('likes')
    //                 ->where('likeable_type', Post::class)
    //                 ->where('user_id', $authUser->id)
    //                 ->union(
    //                     DB::table('saved_posts')
    //                         ->select('post_id')
    //                         ->where('user_id', $authUser->id)
    //                 )
    //                 ->union(
    //                     DB::table('shares')
    //                         ->select('post_id')
    //                         ->where('user_id', $authUser->id)
    //                 )
    //                 ->union(
    //                     DB::table('comments')
    //                         ->select('post_id')
    //                         ->where('user_id', $authUser->id)
    //                 );
    //         })->pluck('id');

    //     // Get all users interacted with posts by the authenticated user
    //     $interactedUserIds = User::whereIn('id', function ($query) use ($interactedPostIds) {
    //         $query->select('user_id')
    //             ->from('likes')
    //             ->where('likeable_type', Post::class)
    //             ->whereIn('likeable_id', $interactedPostIds)
    //             ->union(
    //                 DB::table('shares')
    //                     ->select('user_id')
    //                     ->whereIn('post_id', $interactedPostIds)
    //             )
    //             ->union(
    //                 DB::table('saved_posts')
    //                     ->select('user_id')
    //                     ->whereIn('post_id', $interactedPostIds)
    //             )
    //             ->union(
    //                 DB::table('comments')
    //                     ->select('user_id')
    //                     ->whereIn('post_id', $interactedPostIds)
    //             );
    //     })->pluck('id');

    //     $finalInteractedPosts = Post::where('isVideo', 1)
    //         ->whereIn('id', function ($query) use ($interactedUserIds) {
    //             $query->select('likeable_id')
    //                 ->from('likes')
    //                 ->where('likeable_type', Post::class)
    //                 ->whereIn('user_id', $interactedUserIds)
    //                 ->union(
    //                     DB::table('saved_posts')
    //                         ->select('post_id')
    //                         ->whereIn('user_id', $interactedUserIds)
    //                 )
    //                 ->union(
    //                     DB::table('shares')
    //                         ->select('post_id')
    //                         ->whereIn('user_id', $interactedUserIds)
    //                 )
    //                 ->union(
    //                     DB::table('comments')
    //                         ->select('post_id')
    //                         ->whereIn('user_id', $interactedUserIds)
    //                 );
    //         })
    //         ->distinct()
    //         ->with(['user', 'likes'])
    //         ->withCount(['likes', 'comments'])
    //         ->orderBy('created_at', 'desc')
    //         ->get();

    //     $allPosts = $followerInteractedAndUploadedPosts->merge($finalInteractedPosts)->unique('id')->values();

    //     $currentPage = $request->input('page', 1); // Get current page from request or default to 1
    //     $perPage = 4; // Set per page to 4 if that's what you prefer

    //     // Paginate the combined result set
    //     $posts = new LengthAwarePaginator(
    //         $allPosts->forPage($currentPage, $perPage),
    //         $allPosts->count(),
    //         $perPage,
    //         $currentPage,
    //         ['path' => Paginator::resolveCurrentPath()]
    //     );

    //     $posts->transform(function ($post) use ($userId) {
    //         $isFollowed = FollowerFollowing::where('follower_id', $userId)->where('following_id', $post->user->id)->exists();
    //         $hasLiked = $post->likes->contains('user_id', $userId);

    //         return [
    //             'id' => $post->id,
    //             'description' => $post->description,
    //             'isVideo' => $post->isVideo,
    //             'postLinks' => json_decode($post->postLinks),
    //             'user' => [
    //                 'user_id' => $post->user->id,
    //                 'username' => $post->user->username,
    //                 'imageUrl' => $post->user->imageUrl,
    //                 'isFollowed' => $isFollowed,
    //             ],
    //             'likes_count' => $post->likes_count,
    //             'comments_count' => $post->comments_count,
    //             'has_liked' => $hasLiked,
    //             'created_at' => $post->created_at,
    //             'updated_at' => $post->updated_at,
    //         ];
    //     });

    //     return HelperResponse('success', "Post", 200, $posts);
    // }


}
