<?php

namespace App\Http\Controllers;

use App\Models\Notifications;
use App\Models\User;
use App\Services\HelperService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    protected $helperService;
    public function __construct(HelperService $helperService)
    {
        $this->helperService = $helperService;
    }
    public function test()
    {

        try {
            return [Auth::user()];
        } catch (\Throwable $th) {
            return $th;
        }
    }
    public function getUserDetails($email)
    {
        try {
            $user =  User::where('email', $email)
                ->with(['bankAccount', 'phoneN'])
                ->withCount(['following', 'followers', 'posts'])
                ->first();
            return $user;
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

    public function createPassword(Request $request)
    {
        $validation = Validator::make($request->all(), [
            //'username'=>'required|string|min:3',
            'email' => [
                'required',
                'string',
                'email',
                'max:100',
                // Rule::unique('users', 'email')->ignore($request->id),
            ],
            'password' => ['required', 'confirmed', 'string', 'min:8'],
        ]);

        if ($validation->fails()) {
            return HelperResponse('error', $validation->errors()->first(), 422, $validation->errors()->messages());
        }
        $user = User::where('email', $request->email)->first();
        if (!$user) {
            return HelperResponse('error', 'User does not exist', 422);
        }
        if ($user->email_verified_at == null) {
            return HelperResponse('error', 'Please, verify your email.', 422);
        }
        $user->password = Hash::make($request->password);
        $user->save();

        return HelperResponse('success', 'Password created successfully', 200);
    }
    public function createName(Request $request)
    {
        $validation = Validator::make($request->all(), [
            'name' => [
                'required',
                'regex:/^[a-zA-Z0-9]+$/',
                'min:6'
            ],
            'email' => [
                'required',
                'string',
                'email',
                'max:100',
                // Rule::unique('users', 'email')->ignore($request->id),
            ],
        ]);

        if ($validation->fails()) {
            return HelperResponse('error', $validation->errors()->first(), 422, $validation->errors()->messages());
        }
        $user = User::where('email', $request->email)->first();
        if (!$user) {
            return HelperResponse('error', 'User does not exist', 422);
        }
        $user->name = $request->name;
        $user->save();

        return HelperResponse('success', 'Name successfully updated', 200);
    }

    public function usernameGetToken(Request $request)
    {
        $validation = Validator::make($request->all(), [
            'username' => ['required', 'string', 'min:6', Rule::unique('users', 'username')->ignore($request->id)],
            'email' => [
                'required',
                'string',
                'email',
                'max:100',
                // Rule::unique('users', 'email')->ignore($request->id),
            ],
        ]);

        if ($validation->fails()) {
            return HelperResponse('error', $validation->errors()->first(), 422, $validation->errors()->messages());
        }

        $accessToken = $request->bearerToken();

        // Get access token from database
        $token = PersonalAccessToken::findToken($accessToken);

        // Revoke token
        $token->delete();

        $user = $this->getUserDetails($request->email);
        // $user->makeHidden(['phoneN']);
        if (!$user) {
            return HelperResponse('error', 'User does not exist', 422);
        }
        $user->username = $request->username;
        $user->save();

        //if username is empty
        $token = $user->createToken('auth_token')->plainTextToken;
        //JWTAuth::customClaims(['id' => $user->id, 'email' => $user->email, 'username' => $user->username])->fromUser($user);

        if (!$token) {
            return HelperResponse('error', 'Unauthorized attempt', 401);
        }
        $unseenNotificationCount = Notifications::where('user_id', $user->id)->where('seen', 0)->count();
        $res = $this->helperService->messageFromCreator();
        // $phone_number = ($user->phoneN && !is_null($user->phoneN->verified_at)) ? $user->phoneN->country_code . $user->phoneN->phone : null;
        return HelperResponse('success', 'Username successfully made', 200, [
            'user' => $this->helperService->finalUser($user),
            'unseenNotif' => $unseenNotificationCount,
            'unseenMessageCount' => $this->helperService->getMessageCount($user->id,),

        ], $token,);
    }
    public function login(Request $request)
    {
        $validation = Validator::make($request->all(), [
            'email' => ['required', 'string', 'email', 'max:100'],
            'password' => 'required|string|min:8',
        ]);

        if ($validation->fails()) {
            return HelperResponse('error', $validation->errors()->first(), 422, $validation->errors()->messages());
        }
        $user = $this->getUserDetails($request->email);
        // User::where('email', $request->email)
        //     ->withCount(['following', 'followers', 'posts'])
        //     ->first();
        if (!$user) {
            return HelperResponse('error', 'User does not exist', 422);
        }

        if ($user->username == null) {
            return HelperResponse('error', 'User not found', 422);
        }
        if (!Hash::check($request->password, $user->password)) {
            return HelperResponse('error', 'Password mismatch', 422);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        if (!$token) {
            return HelperResponse('error', 'Unauthorized attempt', 401);
        }
        $unseenNotificationCount = Notifications::where('user_id', $user->id)->where('seen', 0)->count();
        // $user->makeHidden(['phoneN']);

        return HelperResponse('success', 'Login Success', 200, [
            'user' => $this->helperService->finalUser($user),
            'unseenNotif' => $unseenNotificationCount,
            'unseenMessageCount' => $this->helperService->getMessageCount($user->id,),
        ], $token,);
    }
    public function logout(Request $request)
    {
        $user = User::find(Auth::user()->id);
        $user->deviceToken = null;

        $user->save();
        $accessToken = $request->bearerToken();

        // Get access token from database
        $token = PersonalAccessToken::findToken($accessToken);

        // Revoke token
        $token->delete();
        return HelperResponse('success', 'User Logout Successfull', 200);
    }
    public function refresh()
    {
        return response()->json([
            'status' => 'success',
            'user' => Auth::user(),
            'authorisation' => [
                'token' => Auth::refresh(),
                'type' => 'bearer',
            ],
        ]);
    }
    public function setNameUsername(Request $request)
    {
        $validation = Validator::make($request->all(), [
            'email' => 'required|string|email',
            'name' => 'required|string|email',
            'username' => ['string', 'max:100', Rule::unique('users', 'username')->ignore($request->id)],
        ]);
        if ($validation->fails()) {
            return HelperResponse('error', $validation->errors()->first(), 422, $validation->errors()->messages());
        }
        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return HelperResponse('error', 'User does not exist', 422);
        }

        $user->name = $request->name;
        $user->username = $request->username;

        $user->save();

        $credentials = $request->only($user->id, $user->email, $user->username);

        $token = $user->createToken('auth_token')->plainTextToken;
        //JWTAuth::attempt($credentials);

        if ($token == null || $token == '') {
            return HelperResponse('error', 'Unauthorized attempt', 401);
        }
        return HelperResponse('success', 'Registration Success', 201, $user, $token);
    }
    public function changeProfilePicture(Request $request)
    {
        $validation = Validator::make($request->all(), [
            'file' => 'required|file',
        ]);
        if ($validation->fails()) {
            return HelperResponse('error', $validation->errors()->first(), 422, $validation->errors()->messages());
        }
        $user = Auth::user();
        $tempuser = User::where('email', $user->email)->first();
        $path = '';
        if ($tempuser->imageUrl != null) {
            $path = $this->helperService->awsDelete([$tempuser->imageUrl]);
        }

        $uploadedFiles = $this->helperService->awsAdd([$request->file('file')], 'profilepictures/');

        if (empty($uploadedFiles) || !isset($uploadedFiles[0])) {
            return HelperResponse('error', 'File upload failed.', 500);
        }

        $tempuser->imageUrl = $uploadedFiles[0];
        $tempuser->save();

        return HelperResponse('success', 'Profile picture changed successfully!!', 200, $path);
    }

    public function verifyFacebookToken(Request $request)
    {
        $validation = Validator::make($request->all(), [
            'access_token' => 'required|string',
        ]);

        if ($validation->fails()) {
            return HelperResponse('error', $validation->errors()->first(), 422, $validation->errors()->messages());
        }
        // Retrieve the access token from the request
        $accessToken = $request->input('access_token');
        // Step 1: Verify the token with Facebook Graph API

        $response = Http::get('https://graph.facebook.com/me', [
            'fields' => 'id,name,email,picture',
            'access_token' => $accessToken,
        ]);
        // Step 2: Handle the case where the token is invalid
        if ($response->failed()) {
            return HelperResponse('error', 'Invalid Facebook token', 422);
            //return response()->json(['error' => 'Invalid Facebook token'], 401);
        }
        // Step 3: Extract user data from the Facebook API response
        $facebookUser = $response->json();

        // Step 4: Check if the user exists in the database
        $user = User::where('email', $facebookUser['email'])->first();
        // $user->makeHidden(['phoneN']);

        $check = 0;

        // Step 5: If user exists, log them in; otherwise, create a new user
        if ($user) {
            if ($user->facebook_id == null) {
                $user->facebook_id = $facebookUser['id'];
                $user->save();
            }

            // make a token
            $user = $this->getUserDetails($user->email);

            $token = $user->createToken('auth_token')->plainTextToken;

            // User::where('facebook_id', $facebookUser['id'])
            //     ->withCount(['following', 'followers', 'posts'])
            //     ->first();
            // $user->makeHidden(['phoneN']);


            if ($user->username == null || $user->password == null) {
                $check = 1;
            }

            $unseenNotificationCount = Notifications::where('user_id', $user->id)->where('seen', 0)->count();
            return HelperResponse('success', 'User logged successfully', 200, [
                'unseenNotif' => $unseenNotificationCount,
                'user' => $this->helperService->finalUser($user),
                'check' => $check,
                'email' => $user->email,
                'unseenMessageCount' => $this->helperService->getMessageCount($user->id,),


            ], $token);
            //Auth::login($user);
        }

        $check = 1;
        // Create a new user

        $newuser = User::create([
            'name' => $facebookUser['name'],
            'email' => $facebookUser['email'] ?? null,
            'imageUrl' => $facebookUser['picture']['data']['url'],
            'facebook_id' => $facebookUser['id'],
            'otp' => null,
            'otp_expires_at' => null,
            'email_verified_at' => Carbon::now(),
            'issue' => 0,
            // 'password' => bcrypt('randompassword'),
        ]);
        $newuser = $this->getUserDetails($newuser->email);
        $token = $newuser->createToken('auth_token')->plainTextToken;

        // Step 6: Return success response with user data
        // $phone_number = ($user->phoneN && !is_null($user->phoneN->verified_at)) ? $user->phoneN->country_code . $user->phoneN->phone : null;
        return HelperResponse('success', 'Welcome Aboard', 200, [
            'unseenNotif' => 0,
            'email' => $newuser->email,
            'check' => $check,
            'unseenMessageCount' => $this->helperService->getMessageCount($newuser->id,),


            // 'phone' => $phone_number,
        ], $token);
    }
    public function verifyGoogleToken(Request $request)
    {
        // Validate the incoming request to ensure 'access_token' is provided
        $validation = Validator::make($request->all(), [
            'access_token' => 'required|string',
        ]);
        if ($validation->fails()) {
            return HelperResponse('error', $validation->errors()->first(), 422, $validation->errors()->messages());
        }

        try {
            // Retrieve the access token from the request
            $accessToken = $request->input('access_token');

            // Make a request to Google's token info endpoint using the access token
            $response = Http::get('https://oauth2.googleapis.com/tokeninfo?id_token=' . $accessToken);

            if ($response->failed()) {
                return HelperResponse('error', 'Invalid Facebook token', 422);
            }

            $googleUser = $response->json();
            $user = User::where('email', $googleUser['email'])->first();
            // $user->makeHidden(['phoneN']);

            $check = 0;
            if ($user) {
                if ($user->google_id == null) {
                    $user->google_id = $googleUser['sub'];
                    $user->save();
                }

                $user = $this->getUserDetails($user->email);

                // make a token
                $token = $user->createToken('auth_token')->plainTextToken;


                // $user = User::where('google_id', $googleUser['sub'])
                //     ->withCount(['following', 'followers', 'posts'])
                //     ->first();

                if ($user->username == null || $user->password == null) {
                    $check = 1;
                }
                // $user->makeHidden(['phoneN']);

                $unseenNotificationCount = Notifications::where('user_id', $user->id)->where('seen', 0)->count();

                // $phone_number = ($user->phoneN && !is_null($user->phoneN->verified_at)) ? $user->phoneN->country_code . $user->phoneN->phone : null;
                return HelperResponse('success', 'User logged successfully', 200, [
                    'unseenNotif' => $unseenNotificationCount,
                    'user' => $this->helperService->finalUser($user),
                    'check' => $check,
                    'email' => $user->email,
                    'unseenMessageCount' => $this->helperService->getMessageCount($user->id,),
                ], $token);
            }
            $check = 1;

            $newuser = User::create([
                'imageUrl' => $googleUser['picture'] ?? null,
                'name' => $googleUser['name'] ?? null,
                'email' => $googleUser['email'],
                'google_id' => $googleUser['sub'],
                'otp' => null,
                'otp_expires_at' => null,
                'email_verified_at' => Carbon::now(),
                'issue' => 0,
            ]);
            $newuser = $this->getUserDetails($newuser->email);

            $token = $newuser->createToken('auth_token')->plainTextToken;


            return HelperResponse('success', 'Welcome Aboard', 200, [
                'unseenNotif' => 0,
                'email' => $newuser->email,
                'check' => $check,
                'unseenMessageCount' => $this->helperService->getMessageCount($newuser->id,),
            ], $token);
        } catch (\Throwable $th) {
            return HelperResponse('error', $th->getMessage(), 422);
        }
    }

    public function requestToken(Request $request)
    {
        // Validate request inputs
        $validation = Validator::make($request->all(), [
            'email' => ['required', 'string', 'email', 'max:100'],
            'password' => 'required|string|min:8',
        ]);

        // Handle validation failure
        if ($validation->fails()) {
            return HelperResponse('error', $validation->errors()->first(), 422, $validation->errors()->messages());
        }

        // Attempt to find the user by email
        $user = User::where('email', $request->email)
            ->withCount(['following', 'followers', 'posts'])
            ->first();

        if (!$user) {
            return HelperResponse('error', 'User does not exist', 422);
        }

        // Check if user has a username
        if (is_null($user->username)) {
            return HelperResponse('error', 'User not found', 422);
        }

        // Verify the password
        if (!Hash::check($request->password, $user->password)) {
            return HelperResponse('error', 'Password mismatch', 422);
        }

        // Generate the token
        $token = $user->createToken('auth_token')->plainTextToken;

        // Return successful response with the token
        $unseenNotificationCount = Notifications::where('user_id', $user->id)->where('seen', 0)->count();

        return HelperResponse('success', 'Login Success', 200, [
            'unseenNotif' => $unseenNotificationCount,
            'user' => $user,
            'token' => $token,
        ]);
    }
}
