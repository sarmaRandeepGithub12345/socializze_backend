<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Services\HelperService;
use Carbon\Carbon;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class OTPController extends Controller
{
    protected $helperService;
    public function __construct(HelperService $helperService)
    {
        $this->helperService = $helperService;
    }
    public function generateOtp(Request $request)
    {

        //issue ,1-verify email,2- forgot password,3-password change 
        $validation = Validator::make($request->all(), [
            'email' => 'required|string|email',
            'issue' => 'required|integer|min:1',
        ]);

        if ($validation->fails()) {
            return HelperResponse('error', $validation->errors()->first(), 422, $validation->errors()->messages());
        }

        //OTP functionality
        $otp = strval(rand(100000, 999999));
        $otpString = Hash::make($otp);
        $user = User::where('email', $request->email)->first();



        if ($user) {
            if ($user->username != null) {
                return HelperResponse("error", "User found. Kindly login!", 422);
            }
            $user->otp = $otpString;
            $user->otp_expires_at = Carbon::now()->addMinutes(15);
            $user->issue = $request->issue;
            $user->save();
            $this->helperService->mailService($otp, $user);

            $token = $user->createToken('auth_token')->plainTextToken;
            //JWTAuth::customClaims(['id'=>$user->id,'email'=>$user->email])->fromUser($user);

            // $datetime = Carbon::parse($user->otp_expires_at); // Replace with your datetime

            // // Get time in HH:MM format
            // $currentTime = $datetime->format('H:i');

            // // Get time with seconds in HH:MM:SS format
            // $currentTimeWithSeconds = $datetime->format('H:i:s');


            return HelperResponse("success", "Your OTP is sent successfully and will be valid till 15 minutes. Kindly check your email.", 200, $token);
        }
        $newuser = User::create([
            'email' => $request->email,
            'otp' => $otpString,
            'otp_expires_at' => Carbon::now()->addMinutes(15),
            'issue' => $request->issue
        ]);
        $newuser->save();


        //Send the OTP via email
        $this->helperService->mailService($otp, $newuser);

        $token =  $newuser->createToken('auth_token')->plainTextToken;
        //JWTAuth::customClaims(['id'=>$newuser->id,'email'=>$newuser->email])->fromUser($newuser);

        return HelperResponse("success", "Your OTP is sent successfully and will be valid till 15 minutes. Kindly check your email.", 200, $token,);
    }
    public function verifyOtp(Request $request)
    {
        $validation = Validator::make($request->all(), [
            'email' => 'required|string|email',
            'otp' => 'required|string|regex:/^[0-9]{6}$/'
        ]);

        if ($validation->fails()) {
            return HelperResponse("error", $validation->errors()->first(), 422, $validation->errors()->messages());
        }
        $user = User::where('email', $request->email)->first();
        if (!$user) {
            return HelperResponse("error", "User not found", 422);
        }
        if ($user->otp == null) {
            return HelperResponse("error", "Registration timeout", 422);
        }
        //check if OTP has expired
        if (Carbon::now()->greaterThan($user->otp_expires_at)) {
            return HelperResponse("error", "Your OTP has expired", 422);
        }
        if (!Hash::check($request->otp, $user->otp)) {
            return HelperResponse("error", "Your OTP does not match", 422);
        }

        $user->otp = null;
        $user->otp_expires_at = null;
        $user->email_verified_at = Carbon::now();
        $user->issue = 0;
        $user->save();

        return HelperResponse('success', 'Your OTP has successfully been verified!', 200);
    }
}
