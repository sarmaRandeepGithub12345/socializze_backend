<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use App\Models\Notifications;
use App\Models\Post;
use App\Services\FirebaseNotificationService;
use App\Services\HelperService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

use Illuminate\Http\Request;

class CommentController extends Controller
{
    protected $helperService;
    protected $firebaseService;
    public function __construct(HelperService $helperService, FirebaseNotificationService $firebaseService)
    {
        $this->helperService = $helperService;
        $this->firebaseService = $firebaseService;
    }
    public function makeAParentComment(Request $request)
    {
        $validation = Validator::make($request->all(), [
            'content' => 'required|min:1',
            'post_id' => 'required|uuid|exists:posts,id',
            //  'reply_id'=>'required|uuid|exists:users,id',
        ]);

        //get reply_id through token
        if ($validation->fails()) {
            return HelperResponse('error', $validation->errors()->first(), 422, $validation->errors()->messages());
        }
        // $user_id = Auth::user()->id;

        try {
            $post = Post::find($request->post_id);
            //comment created
            $comment = $post->createComment($request->content);
            //loading likes of comment
            // $comment->loadCount(['likes', 'commentParent']);

            //checking to see if person who made comment is not the person who made the post
            //creating notifications when Auth->user->id != ppost->user_id
            if (Auth::user()->id != $comment->inceptor->user_id) {
                //device token of receiver

                $deviceToken = $comment->inceptor->user->deviceToken;
                $username = Auth::user()->username;
                $profileImage = Auth::user()->imageUrl;

                $files = $comment->inceptor->singleFile;
                $thumbnail = $files[0]['thumbnail'];
                $awsLink = $files[0]['aws_link'];
                $isVideo = $comment->inceptor->isVideo;
                $postPic = $isVideo
                    ? $thumbnail
                    : (!empty($thumbnail) ? $thumbnail : $awsLink);
                // $postPic = $comment->inceptor->singleFile[0]['aws_link'];
                $parts = $this->helperService->breakDeviceToken($deviceToken);
                $loggedUserPart = $this->helperService->breakDeviceToken(Auth::user()->deviceToken);
                if (count($parts) == 2 && $parts[1] != Auth::user()->id && $parts[0] != $loggedUserPart[0]) {
                    $this->firebaseService->madeCommentNotification($parts[0], $username, ' commented on your post: ' . $request->content, $this->helperService->fullUrlTransform($postPic), $profileImage);
                }
                Notifications::create([
                    'user_id' => $comment->inceptor->user_id,
                    'first_parent_id' => $request->post_id,
                    'first_parent_type' => get_class($comment->inceptor),
                    'second_parent_id' => $comment->id,
                    'second_parent_type' => get_class($comment),
                    'seen' => false,
                ]);
            }

            return HelperResponse('success', 'Comment made', 201, [
                'id' => $comment->id,
                'content' => $comment->content,
                'inceptor_id' => $comment->inceptor_id,
                'child_comments' => 0,
                'likes' => 0,
                'user' => [
                    'user_id' => Auth::user()->id,
                    'username' => Auth::user()->username,
                    'imageUrl' => Auth::user()->imageUrl,
                ],
                'hasLiked' => false,
                'created_at' => $comment->created_at,
                'updated_at' => $comment->updated_at,
                'replies' => [],
            ]);
        } catch (\Throwable $th) {
            return HelperResponse('error', $th->getMessage(), 422, $th);
        }
    }
    public function getParentComments(Request $request)
    {
        try {

            $validation = Validator::make($request->all(), [
                'post_id' => 'required|uuid|exists:posts,id',
            ]);

            if ($validation->fails()) {
                return HelperResponse('error', $validation->errors()->first(), 422, $validation->errors()->messages());
            }
            $comments = Comment::where('inceptor_id', $request->post_id)
                //no older parent present
                ->whereNull('closest_parentComment_id')
                ->with('user')
                ->withCount(['likes', 'childComments'])
                ->orderBy('created_at', 'desc')
                ->paginate(7);

            // $comments = Comment::where('post_id', $request->post_id)
            // ->whereNull('parent_id')
            // ->with('users')
            // ->withCount(['likes', 'commentParent'])
            // ->orderByRaw('user_id = ? DESC', [$request->user_id]) // Prioritize user's comments
            // ->orderBy('created_at', 'desc') // Then sort by date
            // ->paginate(9);

            $auth = Auth::user();
            $comments->transform(function ($comment) use ($auth) {
                $hasLiked = $comment->likes->contains('user_id', $auth->id);
                return [
                    'id' => $comment->id, //
                    'content' => $comment->content, //
                    'inceptor_id' => $comment->inceptor_id, //
                    'child_comments' => $comment->child_comments_count, //
                    'likes' => $comment->likes_count, //
                    'user' => [
                        'user_id' => $comment->user->id,
                        'username' => $comment->user->username,
                        'imageUrl' => $comment->user->imageUrl,
                    ],
                    'created_at' => $comment->created_at, //
                    'updated_at' => $comment->updated_at, //
                    'hasLiked' => $hasLiked, //
                    'replies' => [], //
                ];
            });
            return HelperResponse('success', 'Parent comments found', 200, $comments);
        } catch (\Throwable $th) {
            return HelperResponse('error', $th->getMessage(), 422, $th);
        }
    }
    public function likeAComment(Request $request)
    {

        $validation = Validator::make($request->all(), [
            'comment_id' => 'required|uuid|exists:comments,id',
        ]);
        if ($validation->fails()) {
            return HelperResponse('error', $validation->errors()->first(), 422, $validation->errors()->messages());
        }
        $user = Auth::user();
        $comment = Comment::find($request->comment_id);
        $check = $comment->isLiked();
        if ($check) {
            $newlike = $comment->unlike();

            $comment->loadCount('likes');

            if ($newlike->user_id == $comment->user_id) {
                return HelperResponse('success', 'Comment unliked', 200, [
                    'like_status' => false,
                    'likes' => $comment->likes_count,
                ]);
            }
            $likes = $comment->likes_count;
            if ($likes == 0 || ($likes == 1 && $comment->likes[0]->user_id == $comment->user_id)) {
                Notifications::where('first_parent_id', $comment->id)->where('second_parent_type', get_class($newlike))->delete();
            }

            return HelperResponse('success', 'Comment has been unliked', 200, [
                'status' => false,
                'likes' => $comment->likes_count,
            ]);
        }

        $newlike = $comment->like();
        $comment->loadCount('likes');
        //for like notification

        if ($user->id != $comment->user_id) {
            $commentOwner = $comment->user;
            $text = $comment->content;
            $desc = ': ' . ($text != null && strlen($text) > 20) ? substr($text, 0, 20) . '...' : $text;

            if ($commentOwner->deviceToken != null) {
                $deviceToken = $commentOwner->deviceToken;
                $parts = $this->helperService->breakDeviceToken($deviceToken);
                $loggedUserPart = $this->helperService->breakDeviceToken(Auth::user()->deviceToken);

                if ($parts[1] != Auth::user()->id && $parts[0] != $loggedUserPart[0]) {
                    $presentPostLike = $comment->likes()->where('user_id', '!=', $user->id)->get();

                    $files = $comment->inceptor->singleFile;
                    $thumbnail = $files[0]['thumbnail'];
                    $awsLink = $files[0]['aws_link'];
                    $isVideo = $comment->inceptor->isVideo;
                    $postPic = $isVideo
                        ? $thumbnail
                        : (!empty($thumbnail) ? $thumbnail : $awsLink);

                    $countP = $presentPostLike->count();
                    $this->firebaseService->likeCommentNotification(
                        $parts[0],
                        'Socializze',
                        $countP . ' people liked your comment' . ($text == null ? "" : $desc),
                        $this->helperService->fullUrlTransform($postPic),
                    );
                }
            }
            $findNotification = Notifications::where('first_parent_id', $comment->id)->where('second_parent_type', 'App/Models/Like')->first();

            if ($findNotification == null) {

                Notifications::create([
                    'user_id' => $comment->user_id,
                    'first_parent_id' => $comment->id,
                    'first_parent_type' => get_class($comment),
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
        return HelperResponse('success', 'Comment has been liked', 200, [
            'status' => true,
            'likes' => $comment->likes_count,
        ]);
    }
    public function getChildComments(Request $request)
    {
        try {
            $validation = Validator::make($request->all(), [
                'post_id' => 'required|uuid|exists:posts,id',
                'parent_id' => 'required|uuid|exists:comments,id',
            ]);

            if ($validation->fails()) {
                return HelperResponse('error', $validation->errors()->first(), 422, $validation->errors()->messages());
            }
            $comments = Comment::where('inceptor_id', $request->post_id)->where('closest_parentComment_id', $request->parent_id)->with('user')->withCount('likes')->paginate(3);
            $comments->transform(function ($comment) {
                $hasLiked = $comment->likes->contains('user_id', Auth::user()->id);
                return [
                    'id' => $comment->id,
                    'content' => $comment->content,
                    'inceptor_id' => $comment->inceptor->id,
                    'likes' => $comment->likes_count,
                    'user' => [
                        'user_id' => $comment->user->id,
                        'username' => $comment->user->username,
                        'imageUrl' => $comment->user->imageUrl,
                    ],
                    'created_at' => $comment->created_at,
                    'updated_at' => $comment->updated_at,
                    'hasLiked' => $hasLiked,
                ];
            });
            return HelperResponse('success', 'Parent comments found', 200, $comments);
        } catch (\Throwable $th) {
            return HelperResponse('error', $th->getMessage(), 422, $th);
        }
    }
    public function makeAChildComment(Request $request)
    {
        $validation = Validator::make($request->all(), [
            'content' => 'required|min:1',
            'post_id' => 'required|uuid|exists:posts,id',
            'parent_id' => 'required|uuid|exists:comments,id',
            'replyTo_id' => 'required|uuid|exists:comments,id',
        ]);

        //reply_id for notification
        if ($validation->fails()) {
            return HelperResponse('error', $validation->errors()->first(), 422, $validation->errors()->messages());
        }
        $user_id = Auth::user()->id;
        try {
            $inceptor = Post::find($request->post_id);

            $comment = Comment::create([
                'content' => $request->content,
                'inceptor_id' => $request->post_id,
                'inceptor_type' => get_class($inceptor),
                'user_id' => $user_id,
                'closest_parentComment_id' => $request->parent_id,
                'replied_to_id' => $request->replyTo_id,
            ]);
            // $comment->loadCount(['likes', 'commentParent']);
            //commentmaker not equal to parent comment

            if (Auth::user()->id != $comment->repliedToF->user_id) {
                //get deviceTOken of parent comment

                $deviceToken = $comment->repliedToF->user->deviceToken;
                $username = Auth::user()->username;
                $profileImage = Auth::user()->imageUrl;

                $files = $comment->inceptor->singleFile;
                $thumbnail = $files[0]['thumbnail'];
                $awsLink = $files[0]['aws_link'];
                $isVideo = $comment->inceptor->isVideo;
                $postPic = $isVideo
                    ? $thumbnail
                    : (!empty($thumbnail) ? $thumbnail : $awsLink);

                $parts = $this->helperService->breakDeviceToken($deviceToken);
                $loggedUserPart = $this->helperService->breakDeviceToken(Auth::user()->deviceToken);

                if (count($parts) == 2 && $parts[1] != Auth::user()->id && $parts[0] != $loggedUserPart[0]) {
                    $this->firebaseService->madeCommentNotification($parts[0], $username, ' replied on your comment: ' . $request->content, $this->helperService->fullUrlTransform($postPic), $profileImage);
                }
                //parent comment
                Notifications::create([
                    'user_id' => $comment->repliedToF->user_id,
                    'first_parent_id' => $comment->replied_to_id,
                    'first_parent_type' => get_class($comment->repliedToF),
                    'second_parent_id' => $comment->id,
                    'second_parent_type' => get_class($comment),
                    'seen' => false,
                ]);
            }
            return HelperResponse('success', 'Comment made', 201, [
                'id' => $comment->id, //
                'content' => $comment->content, //
                'inceptor_id' => $comment->inceptor_id, //
                //'child_comments'=>$comment->comment_parent_count,
                'likes' => $comment->likes_count, //
                'user' => [
                    'user_id' => Auth::user()->id,
                    'username' => Auth::user()->username,
                    'imageUrl' => Auth::user()->imageUrl,
                ],
                'closest_parentComment_id' => $request->parent_id, //
                'hasLiked' => false, //
                'created_at' => $comment->created_at, //
                'updated_at' => $comment->updated_at, //
            ]);
        } catch (\Throwable $th) {
            return HelperResponse('error', $th->getMessage(), 422, $th);
        }
    }
    public function deleteAComment(Request $request)
    {
        $validation = Validator::make($request->all(), [
            'comment_id' => 'required|uuid|exists:comments,id',
        ]);
        if ($validation->fails()) {
            return HelperResponse('error', $validation->errors()->first(), 422, $validation->errors()->messages());
        }
    }
}
