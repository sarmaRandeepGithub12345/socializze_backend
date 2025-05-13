<?php

namespace App\Services;

use Twilio\Rest\Client;

class TwilioService
{
    protected $twilio;

    public function __construct()
    {
        $this->twilio = new Client(env('TWILIO_ACCOUNT_SID'), env('TWILIO_AUTH_TOKEN'));
    }
    public function sendSms($to, $message)
    {
        // $status = $this->twilio->messages->create(
        //     // The number you'd like to send the message to
        //     $to,
        //     [
        //         // A Twilio phone number you purchased at https://console.twilio.com
        //         'from' => env('TWILIO_PHONE_NUMBER'),
        //         // The body of the text message you'd like to send
        //         'body' => $message
        //     ]
        // );
        try {
            $status = $this->twilio->messages
                ->create(
                    $to,
                    array(
                        'from' => env('TWILIO_PHONE_NUMBER'),
                        "body" => $message
                    )
                );
            return $status;
        } catch (\Exception $e) {
            return $e->getMessage();
        }
        // return $this->twilio->messages->create($to, [
        //     'from' => env('TWILIO_PHONE_NUMBER'),
        //     'body' => $message
        // ]);
    }
    public function verifyPhone($to, $message, $otp)
    {

        try {
            // $this->twilio->verify->v2->services(env('TWILIO_VERIFICATION_SID'))
            //     ->verifications
            //     ->create($to, "sms");
            // return "Verification code sent successfully.";
            $verificationCheck =  $this->twilio->validationRequests->create(
                $to, // PhoneNumber
                ["friendlyName" => "one new number"],
                0,
                "+91",
                "http://www.example.com/callback",
                true
            );

            return $verificationCheck;
        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }
    public function sendMessages($to, $message, $otp)
    {

        try {
            // $this->twilio->verify->v2->services(env('TWILIO_VERIFICATION_SID'))
            //     ->verifications
            //     ->create($to, "sms");
            // return "Verification code sent successfully.";
            $verificationCheck =  $this->twilio->messages->create($to, []);

            return $verificationCheck;
        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }
}
