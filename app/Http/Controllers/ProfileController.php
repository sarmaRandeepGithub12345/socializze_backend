<?php

namespace App\Http\Controllers;

use App\Models\FollowerFollowing;
use App\Models\User;
use App\Services\HelperService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class ProfileController extends Controller
{
    protected $helperService;
    public function __construct(HelperService $helperService)
    {
        $this->helperService = $helperService;
    }
    public function getFollowers(Request $request)
    {

        $validation = Validator::make($request->all(), [
            'user_id' => 'required|uuid|exists:users,id',
        ]);

        if ($validation->fails()) {
            return HelperResponse('error', $validation->errors()->first(), 422, $validation->errors()->messages());
        }
        $authUserId = Auth::user()->id;;

        $followerInformation = User::find($request->user_id)
            ->followers()
            ->select('users.id', 'users.username', 'users.name', 'users.imageUrl')
            ->paginate(12);
        // Modify the data without losing pagination
        $followerInformation->getCollection()->transform(function ($follower) use ($authUserId) {
            $check = FollowerFollowing::where('follower_id', $authUserId)
                ->where('following_id', $follower->id)
                ->exists();
            $follower->isFollowed = $authUserId == $follower->id ? false : $check;
            return $follower;
        });

        // Return the follower information
        return HelperResponse("success", "User found", 200, $followerInformation);
    }
    public function getFollowings(Request $request)
    {

        $validation = Validator::make($request->all(), [
            'user_id' => 'required|uuid|exists:users,id',
        ]);

        if ($validation->fails()) {
            return HelperResponse('error', $validation->errors()->first(), 422, $validation->errors()->messages());
        }
        $authUserId = Auth::user()->id;
        $followingsInformation = User::find($request->user_id)
            ->following()
            ->select('users.id', 'users.name', 'users.username', 'users.imageUrl')
            ->paginate(12);
        // Modify the data without losing pagination
        $followingsInformation->getCollection()->transform(function ($follower) use ($authUserId) {
            $check = FollowerFollowing::where('follower_id', $authUserId)
                ->where('following_id', $follower->id)
                ->exists();
            $follower->isFollowed = $authUserId == $follower->id ? false : $check;
            return $follower;
        });


        // Return the follower information
        return HelperResponse("success", "User found", 200, $followingsInformation);
    }
}
