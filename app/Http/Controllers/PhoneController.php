<?php

namespace App\Http\Controllers;

use App\Models\Phone;
use App\Models\User;
use App\Services\HelperService;
use Carbon\Carbon;
use Illuminate\Http\Request;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use App\Services\TwilioService;
use Twilio\Rest\Client;

class PhoneController extends Controller
{
    protected $helperService, $twilio;
    public function __construct(HelperService $helperService, TwilioService $twilio)
    {
        $this->helperService = $helperService;
        $this->twilio = $twilio;
    }
    public function finalUser($user)
    {
        try {
            $country_code = optional($user->phoneN)->country_code;
            $phone = optional($user->phoneN)->phone;

            return [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'imageUrl' => $user->imageUrl,
                'description' => $user->description,
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
                'posts_count' => $user->posts_count,
                'following_count' => $user->following_count,
                'followers_count' => $user->followers_count,
                'phone' => $country_code && $phone ? strval($country_code . $phone) : null,
                'bank_account' => $user->bankAccount,
            ];
        } catch (\Throwable $th) {
            return $th;
        }
    }

    public function getUserDetails($email)
    {
        try {
            return  User::where('email', $email)
                ->with(['bankAccount', 'phoneN'])
                ->withCount(['following', 'followers', 'posts'])

                ->first();
        } catch (\Throwable $th) {
            return $th;
        }
    }
    public function tempConfirmPhone(Request $request)
    {

        //issue ,1-verify email,2- forgot password,3-password change 

        $validation = Validator::make($request->all(), [
            'phone' => ['required', 'digits:10'], // Ensures exactly 10 digits
            'country_code' => ['required', 'regex:/^\+\d{1,7}$/'],
        ]);
        //TWILIO_PHONE_NUMBER
        //TWILIO_ACCOUNT_SID
        //TWILIO_AUTH_TOKEN
        if ($validation->fails()) {
            return HelperResponse('error', $validation->errors()->first(), 422, $validation->errors()->messages());
        }


        try {
            $checkNumba = Phone::where('user_id', Auth::user()->id)->exists();
            if ($checkNumba != null) {
                $record = Phone::where('user_id', Auth::user()->id)->first();
                $record->phone = $request->phone;
                $record->country_code = $request->country_code;
                $record->verified_at = Carbon::now();
                $record->save();
                $user = $this->getUserDetails(Auth::user()->email);

                return HelperResponse("success", "Your Phone Number is  verified", 200, [
                    'user' => $this->finalUser($user)
                ]);
            }


            $phone = Phone::create([
                'user_id' => Auth::user()->id,
                'phone' => strval($request->phone),
                'country_code' => strval($request->country_code),
                'verified_at' => Carbon::now(),
            ]);

            $user = $this->getUserDetails(Auth::user()->email);

            return HelperResponse("success", "Your Phone Number is verified", 200, [
                'user' => $this->finalUser($user)
            ]);
        } catch (\Throwable $th) {
            return HelperResponse("error", $th->getMessage(), 422,);
        }
    }
    public function generateOtp(Request $request)
    {

        //issue ,1-verify email,2- forgot password,3-password change 
        $validation = Validator::make($request->all(), [
            'phone' => ['required', 'digits:10'], // Ensures exactly 10 digits
            'country_code' => ['required', 'regex:/^\+\d{1,7}$/'],
        ]);
        //TWILIO_PHONE_NUMBER
        //TWILIO_ACCOUNT_SID
        //TWILIO_AUTH_TOKEN
        if ($validation->fails()) {
            return HelperResponse('error', $validation->errors()->first(), 422, $validation->errors()->messages());
        }


        try {
            //OTP functionality
            $otp = strval(rand(100000, 999999));
            $otpString = Hash::make($otp);
            //TWILIO COnfiguration
            $phone_num = $request->phone;
            $country_code = $request->country_code;


            // return gettype($phone);


            $checkNumba = Phone::where('user_id', Auth::user()->id)->first();
            //checking if phone record is present or not
            if ($checkNumba != null) {
                //checking if 15 minutes has exceeded or not
                if (Carbon::now()->lessThanOrEqualTo($checkNumba->otp_expires_at)) {
                    $now = Carbon::now();
                    $expiryTime = Carbon::parse($checkNumba->otp_expires_at);

                    $minutesDifference = intval($now->diffInMinutes($expiryTime));
                    return HelperResponse("error", "Please try again after " . $minutesDifference . ' minutes', 422);
                }
                $this->twilio->sendSms(
                    //$country_code . $phone_num,
                    strval($country_code . $phone_num),
                    ['Your Socializze OTP is ' . $otp]
                );
                $checkNumba->otp = $otpString;
                $checkNumba->otp_expires_at = Carbon::now()->addMinutes(15);
                $checkNumba->verified_at = null;

                return HelperResponse("success", "Your OTP is sent successfully and will be valid till 15 minutes. Kindly check your messages.", 200,);
            }



            // return [strval($country_code . $phone_num), "no"];
            $status = $this->twilio->verifyPhone(strval($country_code . $phone_num), "dd", $otp);
            // $status = $this->twilio->sendSms(strval($country_code . $phone_num), "dd",);
            //$this->twilio->verifyPhone(strval($phone_num), "dd", $otp);

            // return [$status, "so the number is "];
            return $status;
            Phone::create([
                'user_id' => Auth::user()->id,
                'phone' => $request->phone,
                'country_code' => $request->country_code,
                'otp' => $otpString,
                'otp_expires_at' => Carbon::now()->addMinutes(15),
            ]);
            $this->twilio->sendSms(
                //$country_code . $phone_num,
                strval($country_code . $phone_num),
                ['Your Socializze OTP is ' . $otp]
            );

            return HelperResponse("success", "Your OTP is sent successfully and will be valid till 15 minutes. Kindly check your messages.", 200,);
        } catch (\Throwable $th) {
            return HelperResponse("error", $th->getMessage(), 422,);
        }
    }
    public function verifyOtp(Request $request)
    {
        $validation = Validator::make($request->all(), [
            'otp' => 'required|string|regex:/^[0-9]{6}$/'
        ]);

        if ($validation->fails()) {
            return HelperResponse("error", $validation->errors()->first(), 422, $validation->errors()->messages());
        }
        $phoneRecord = Phone::where('user_id', Auth::user()->id)->first();
        if (!$phoneRecord) {
            return HelperResponse("error", "Phone number not found", 422);
        }

        //check if OTP has expired
        if (Carbon::now()->greaterThan($phoneRecord->otp_expires_at)) {
            return HelperResponse("error", "Your OTP has expired", 422);
        }
        if (!Hash::check($request->otp, $phoneRecord->otp)) {
            return HelperResponse("error", "Your OTP does not match", 422);
        }

        $phoneRecord->otp = null;
        $phoneRecord->otp_expires_at = null;
        $phoneRecord->verified_at = Carbon::now();
        $phoneRecord->save();

        return HelperResponse('success', 'Phone number successfully verified!', 200);
    }
    public function test()
    {

        $user = Auth::user();
        return $user->phoneN->verified_at;
    }
}
