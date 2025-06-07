<?php

namespace App\Http\Controllers;

use App\Events\SeeStory;
use App\Models\FollowerFollowing;
use App\Models\Post;
use App\Models\Story;
use App\Models\User;
use App\Models\UserSeen;
use App\Services\FirebaseNotificationService;
use App\Services\HelperService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class StoryController extends Controller
{
    protected $helperService;
    protected $firebaseService;
    public function __construct(HelperService $helperService, FirebaseNotificationService $firebaseService)
    {
        $this->helperService = $helperService;
        $this->firebaseService = $firebaseService;
    }
    public function makeAStory(Request $request)
    {

        $validation = Validator::make($request->all(), [
            'files' => 'required|array',
            'files.*' => 'file|max:204800', //20MB
            // 'thumbnails' => 'required|array',
            'thumbnails' => 'array',

            'thumbnails.*' => 'file|max:204800',
            'shorts' => 'required|integer',
        ]);
        if ($validation->fails()) {
            return HelperResponse('error', $validation->errors()->first(), 422, $validation->errors()->messages());
        }

        try {
            $filesToUpload = $request->file('files');
            $thumbNails = $request->file('thumbnails');
            $paths = $this->helperService->uploadFilesAnThumbnail($filesToUpload, $thumbNails, 'posts');
            return $this->commonFUpload($paths, $request->description, $request->shorts,);

            // if ($request->shorts == 1) {

            //     $paths = $this->helperService->onlyUpload($filesToUpload, 'stories');
            //     return $this->commonFUpload($paths, 1);
            // } else {
            //     $paths = $this->helperService->awsAdd($filesToUpload, 'stories/');

            //     return  $this->commonFUpload($paths, 0);
            // }
        } catch (\Throwable $th) {
            return HelperResponse('error', $th->getMessage(), 422, $th);
        }
    }
    public function commonFUpload($paths, $shorts)
    {

        try {
            if (!is_array($paths)) {
                return HelperResponse('error', 'File upload failed', 500, ['message:' . $paths->getMessage(), 'paths' => $paths,],);
            }
            if (empty($paths) || !isset($paths)) {
                return HelperResponse('error', 'File upload failed.', 500);
            }
            $authuser = Auth::user();

            $newStory = Story::create([
                'user_id' => $authuser->id,
            ]);
            $uploaded = $paths[0];

            $newStory->uploadStory($uploaded['aws_link'], $uploaded['thumbnail'] == '' ? null : $uploaded['thumbnail'], 1);


            return HelperResponse('success', 'Story uploaded', 201, [
                'newStory' => [
                    'userData' => [
                        'sender_id' => $authuser->id,
                        'username' => $authuser->username,
                        'imageUrl' => $authuser->imageUrl,
                    ],
                    'file_info' => [
                        'aws_link' => $newStory->singleFileOne->aws_link,
                        'thumbnail' => $newStory->singleFileOne->thumbnail,

                    ],
                    'created_at' => $newStory->created_at,
                    'updated_at' => $newStory->updated_at,
                ],
            ]);
        } catch (\Throwable $th) {
            return HelperResponse('error', $th->getMessage(), 422, $th);
        }
    }
    public function getStory()
    {
        $authUser = Auth::user();
        $presentDate = Carbon::now();
        $twentyFourHoursAgo = $presentDate->subHours(24);
        try {
            //objectives
            //1)get users followed by logged user
            //2)arrange them according to the latest story uploaded by them in last 24 hours
            //3)include all stories uploaded in the last 24 hours
            //4)paginate the results
            //5)results should also contain media data


            // EAGER LOADING is a strategy to retrieve related data along with the primary models in a single query, rather than executing separate queries for each related model as they are accessed.
            $followingIds = $authUser->following()
                //with:    
                //This is for eager loading the stories — it controls what stories are actually loaded and attached to the user.
                // It does not filter out the user itself — it just affects the related data you pull.
                //it will also return users with 0 stories
                ->with(['story' => function ($query) use ($twentyFourHoursAgo) {
                    $query->with([
                        'singleFileOne:id,parent_id,aws_link,thumbnail',
                        'getSeen' => function ($q) {
                            $q->where('user_id', Auth::id()); // Only eager load the current user's UserSeen if it exists
                        },
                    ])
                        ->where('created_at', '>=', $twentyFourHoursAgo)
                        ->orderBy('created_at', 'asc');
                }])
                //whereHas:Without this, you’d still get users who have no stories at all in the last 24 hours — and that would break your logic/UI.
                ->whereHas('story', function ($query) use ($twentyFourHoursAgo) {
                    $query->where('created_at', '>=', $twentyFourHoursAgo);
                })
                //orderByDesc:If you don't add this, you’ll get users in a random order (maybe based on ID), not by freshness of their stories.
                ->orderByDesc(function ($query) use ($twentyFourHoursAgo) {
                    $query->select('created_at')
                        ->from('stories')
                        ->whereColumn('user_id', 'users.id')
                        ->where('created_at', '>=', $twentyFourHoursAgo)
                        ->orderBy('created_at', 'desc')
                        ->limit(1); // Only use the newest story for sorting
                })
                ->paginate(3);


            if (count($followingIds) == 0) {
                $followingIds = User::where('id', '!=', $authUser->id)
                    ->with(['story' => function ($query) use ($twentyFourHoursAgo) {
                        $query->with([
                            'singleFileOne:id,parent_id,aws_link,thumbnail',
                            'getSeen' => function ($q) {
                                $q->where('user_id', Auth::id()); // Only eager load the current user's UserSeen if it exists
                            }
                        ])
                            ->where('created_at', '>=', $twentyFourHoursAgo)
                            ->orderBy('created_at', 'asc');
                    }])
                    ->whereHas('story', function ($query) use ($twentyFourHoursAgo) {
                        $query->where('created_at', '>=', $twentyFourHoursAgo);
                    })
                    ->inRandomOrder()
                    ->orderBy('created_at', 'desc')
                    ->paginate(3);
            }
            $followingIds->getCollection()->transform(function ($user) {
                $lastSeenIndex = -1;

                foreach ($user->story as $index => $story) {
                    // Format AWS links
                    $story->singleFileOne->aws_link =  $this->helperService->fullUrlTransform($story->singleFileOne->aws_link);
                    if ($story->singleFileOne->thumbnail) {
                        $story->singleFileOne->thumbnail =  $this->helperService->fullUrlTransform($story->singleFileOne->thumbnail);
                    }

                    // Check if the logged user has seen this story
                    if ($story->getSeen && $story->getSeen->isNotEmpty()) {
                        $lastSeenIndex = $index;
                    }

                    // Remove getSeen relationship before returning
                    unset($story->getSeen);
                }


                return [
                    'userData' => [
                        'id' => $user->id,
                        'username' => $user->username,
                        'imageUrl' => $user->imageUrl,
                    ],
                    'lastSeenIndex' => $lastSeenIndex, // ✅ Added here
                    'lastSeen' => $user->getSeen,
                    'stories' => $user->story,
                ];
            });
            $lastIndex = -1;
            $loggedUserStories = $authUser->story()
                ->with([
                    'singleFileOne:id,parent_id,aws_link,thumbnail',
                    'getSeen.user:id,username,imageUrl'
                ]) // ensure user relation for each seen record is loaded
                ->where('created_at', '>=', $twentyFourHoursAgo)
                ->orderBy('created_at', 'asc')
                ->get()
                ->map(function ($story, $index) use (&$lastIndex, $authUser) {

                    $seenUsers = collect($story->getSeen)->map(function ($seen) {
                        return [
                            'id' => $seen->id,
                            'userData' => [
                                'id' => $seen->user->id,
                                'username' => $seen->user->username,
                                'imageUrl' => $seen->user->imageUrl,
                            ],
                            'updated_at' => $seen->updated_at,
                        ];
                    });

                    // Check if the current story was seen by the logged-in user
                    if ($story->getSeen->where('user_id', $authUser->id)->isNotEmpty()) {
                        $lastIndex = $index;
                    }

                    unset($story->getSeen);
                    $story->userseen = $seenUsers;

                    // Format AWS links
                    $aws = $story->singleFileOne->aws_link;
                    $thumbnail = $story->singleFileOne->thumbnail;
                    $story->singleFileOne->aws_link =  $this->helperService->fullUrlTransform($aws);
                    if ($thumbnail != '') {
                        $story->singleFileOne->thumbnail =  $this->helperService->fullUrlTransform($thumbnail);
                    }

                    return $story;
                });

            return HelperResponse("success", "Stories found", 200, [
                'OtherStories' => $followingIds,
                'loggedUserStories' => $loggedUserStories,
                'lastSeenSelfIndex' => $lastIndex,
            ],);
        } catch (\Throwable $th) {
            return HelperResponse('error', $th->getMessage(), 422, $th);
        }
    }
    public function seeAStory(Request $request)
    {
        $validation = Validator::make($request->all(), [
            'story_id' => 'required|uuid|exists:stories,id',
        ]);
        if ($validation->fails()) {
            return HelperResponse('error', $validation->errors()->first(), 422, $validation->errors()->messages());
        }
        try {
            $story = Story::find($request->story_id);

            $result = $story->makeSeen();

            SeeStory::dispatch($story, $result);
            return HelperResponse("success", "See granted", 200, ['data' => $result,],);
        } catch (\Throwable $th) {
            return HelperResponse('error', $th->getMessage(), 422, $th);
        }
    }
}
