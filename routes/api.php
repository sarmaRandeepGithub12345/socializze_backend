<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\DiskController;
use App\Http\Controllers\GeneralController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OTPController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PhoneController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RandomSearchController;
use App\Http\Controllers\ReelsController;
use App\Http\Controllers\StoryController;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/hello', function () {
    return response()->json(['message' => 'Hello, world!']);
});
Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');
Route::post('/disk', [DiskController::class, 'uploadFile']);

Route::post('/tokens/create', function (Request $request) {
    $user = User::where('email', $request->email)->first();

    $token = $user->createToken('auth_token')->plainTextToken;
    return ['token' => $token];
});
Route::post('/orders', function () {
    return 'hi';
    // Token has both "check-status" and "place-orders" abilities...
})->middleware(['auth:sanctum']);

Route::middleware(['auth:sanctum'])->group(function () {
    Route::prefix('auth')
        ->controller(AuthController::class)
        ->group(function () {
            Route::post('change-profile-picture', 'changeProfilePicture');
            Route::post('create-username', 'usernameGetToken');
            Route::post('create-password', 'createPassword');
            Route::post('create-name', 'createName');
            Route::post('logout', 'logout');
            Route::post('refresh', 'refresh');
            Route::post('test', 'test');
        });
    Route::prefix('search')
        ->controller(GeneralController::class)
        ->group(function () {
            Route::post('find-user', 'searchUser');
        });
    Route::prefix('otp')
        ->controller(OTPController::class)
        ->group(function () {
            Route::post('verify-otp', 'verifyOtp');
        });
    Route::prefix('post')
        ->controller(PostController::class)
        ->group(function () {
            Route::post('update-views', 'updateViews');
            Route::post('make-a-post', 'makeAPost');
            Route::post('get-posts', 'getPosts');
            Route::post('delete-post', 'deletePost');
            Route::post('test', 'test');
            Route::post('like-a-post', 'likeAPost');
        });
    Route::prefix('reel')
        ->controller(ReelsController::class)
        ->group(function () {
            Route::post('get-reels', 'getReels');
        });
    Route::prefix('follow')
        ->controller(ProfileController::class)
        ->group(function () {
            Route::post('get-followers', 'getFollowers');
            Route::post('get-following', 'getFollowings');

            // Route::post('get-logged-user-reels','getLoggedUserReels');
        });
    Route::prefix('random-search')
        ->controller(RandomSearchController::class)
        ->group(function () {
            Route::post('search-users', 'searchUsers');
            Route::post('random-posts', 'getRandom');
            Route::post('follow', 'followFunct');
        });
    Route::prefix('message')
        ->controller(MessageController::class)
        ->group(function () {
            Route::post('send-message', 'sendInitialMessage');
            Route::post('get-chats-variable', 'getChatsVariable');
            Route::post('get-all-chats', 'getAllChats');
            Route::post('accept-chat-request', 'acceptChatRequest');
            Route::post('search-inbox', 'searchuser');
            Route::post('test', 'test');
            Route::post('fetch-by-chat-id', 'fetchChatsByID');
            Route::post('delete-by-chat-id', 'deleteParticularChat');
            Route::post('message-from-creator', 'createFounderMessages');
            Route::post('find-chat-by-participant', 'findChatByParticipants');
            Route::post('get-remaining-messages', 'getRemainingMessages');
            Route::post('see-messages', 'seeMessages');
        });
    Route::prefix('comment')
        ->controller(CommentController::class)
        ->group(function () {
            // Route::post('test', function(){
            //     return "Hi";
            // });
            //parent comment
            Route::post('make-a-comment', 'makeAParentComment');
            Route::post('get-parent-comments', 'getParentComments');
            Route::post('like-a-comment', 'likeAComment');

            //child comment
            Route::post('make-a-child-comment', 'makeAChildComment');
            Route::post('get-child-comments', 'getChildComments');
        });
    Route::prefix('notification')
        ->controller(NotificationController::class)
        ->group(function () {
            Route::post('save-device-token', 'saveDeviceToken');
            Route::post('mark-all-seen', 'markAllSeen');
            Route::post('get-all-notifications', 'getNotifications');
            Route::post('test', 'test');
        });
    Route::prefix('order')
        ->controller(PaymentController::class)
        ->group(function () {
            Route::post('create-order', 'createOrder');
            Route::post('update-order', 'completeOrder');
            Route::post('payout', 'payout');
            // Route::post('get-signature', 'getSignature');
            Route::post('verify-bank-account', 'verifyBankAcc');
            Route::post('verify-upi-id', 'verifyUpiID');
            Route::post('get-amount', 'getAmount');
            Route::post('get-payments-received', 'getPaymentsReceived');
            Route::post('get-payments-sent', 'getPaymentsSent');
            Route::post('get-withdrawals', 'getWithdrawals');

            // Route::post('test', 'test');
        });
    Route::prefix('phone')
        ->controller(PhoneController::class)
        ->group(function () {
            Route::post('generate-otp', 'generateOtp');
            Route::post('verify-otp', 'verifyOtp');
            Route::post('temp-verify', 'tempConfirmPhone');
        });
    Route::prefix('story')
        ->controller(StoryController::class)
        ->group(function () {
            // Route::post('update-views', 'updateViews');
            Route::post('make-a-story', 'makeAStory');
            Route::post('get-story', 'getStory');
            // Route::post('delete-post', 'deletePost');
            Route::post('see-a-story', 'seeAStory');
            Route::post('like-a-story', 'likeAStory');
        });
});

Route::prefix('auth')
    ->controller(AuthController::class)
    ->group(function () {
        // Single route
        Route::post('login', 'login');
        // Token verification routes
        Route::post('verify-facebook-token', 'verifyFacebookToken');
        Route::post('verify-google-token', 'verifyGoogleToken');
    });
Route::prefix('otp')
    ->controller(OTPController::class)
    ->group(function () {
        Route::post('generate-otp', 'generateOtp');
    });
Route::prefix('post')
    ->controller(PostController::class)
    ->group(function () {

        Route::post('get-user', 'clickPostProfile');
        Route::post('get-user-posts', 'getPostsUser');
        // Route::post('test', 'test');
    });
Route::prefix('reel')
    ->controller(ReelsController::class)
    ->group(function () {
        Route::post('get-logged-user-reels', 'getLoggedUserReels');
    });
Route::prefix('order')
    ->controller(PaymentController::class)
    ->group(function () {
        Route::post('test', 'test');
        // Route::post('payout', 'payout');
    });
