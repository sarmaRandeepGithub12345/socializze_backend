<?php

namespace App\Services;

use App\Models\Notifications;
use App\Models\User;
use Carbon\Carbon;
use Exception;
use FFMpeg\FFMpeg;
use FFMpeg\Coordinate\Dimension;
use FFMpeg\Coordinate\TimeCode;
use FFMpeg\Format\Video\X264;
use GuzzleHttp\Psr7\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
use Illuminate\Support\Str;

class PayoutService
{
    public function __construct() {}
    public function convertToDash($st)
    {
        return str_replace('-', '_', $st);
    }
    public function convertToSeparator($st)
    {
        return str_replace('_', '-', $st);
    }
    public  function getSignature()
    {
        $clientId = env('CASHFREE_PAYOUT_APP_ID');
        $publicKey = env('CASHFREE_PAYOUT_PUBLIC_KEY');
        $encodedData = $clientId . "." . strtotime("now");
        return $this->encrypt_RSA($encodedData, $publicKey);
    }
    private  function encrypt_RSA($plainData, $publicKey)
    {
        if (openssl_public_encrypt(
            $plainData,
            $encrypted,
            $publicKey,
            OPENSSL_PKCS1_OAEP_PADDING
        ))
            $encryptedData = base64_encode($encrypted);
        else return "Hea";
        return $encryptedData;
    }
    public function authorizePayout()
    {
        try {
            $signature = $this->getSignature();
            $responseOne = Http::withHeaders([
                'X-Cf-Signature' => $signature,
                'X-Client-Id' => env('CASHFREE_PAYOUT_APP_ID'),
                'X-Client-Secret' => env('CASHFREE_PAYOUT_SECRET'),
                // 'x-api-version' => env('CASHFREE_PAYMENT_API_VERSION'),
            ])->post(env('CASHFREE_BASE_URL') . env('CASHFREE_PAYOUT_AUTHORIZE'), []);
            return $responseOne->json();
        } catch (\Throwable $th) {
            return $th;
        }
    }
    public function verifyToken($token)
    {
        try {
            $responseTwo = Http::withHeaders([
                'Authorization' => "Bearer " . $token,
                // 'x-api-version' => env('CASHFREE_PAYMENT_API_VERSION'),
            ])->post(env('CASHFREE_BASE_URL') . env('CASHFREE_PAYOUT_VERIFY_TOKEN'), []);
            return $responseTwo->json();
        } catch (\Throwable $th) {
            return $th;
        }
    }
    public function getHeaders()
    {
        try {
            $signature = $this->getSignature();

            return   [
                'X-cf-signature' => $signature,
                'x-client-id' => env('CASHFREE_PAYOUT_APP_ID'),
                'x-client-secret' => env('CASHFREE_PAYOUT_SECRET'),
                'x-api-version' => env('CASHFREE_PAYOUT_API_VERSION'),
            ];
        } catch (\Throwable $th) {
            return $th;
        }
    }
    public function getBeneficiaryUsingBenID($id)
    {
        try {
            $sellerID = str_replace('-', '_', $id);


            $response = Http::withHeaders($this->getHeaders())->get(
                env('CASHFREE_BASE') . env('CASHFREE_PAYOUT_GET_BENEFICIARY'),
                http_build_query([
                    "beneficiary_id" =>  $sellerID,
                ]),
                []
            );
            return $response->json();
        } catch (\Throwable $th) {
            return $th;
        }
    }
    public function getBeneficiaryUsingAccDetails()
    {

        try {
            // $user = User::find($id);
            $user = Auth::user();

            $sellerID = str_replace('-', '_', $user->id);

            $response = Http::withHeaders($this->getHeaders())->get(
                env('CASHFREE_BASE') . env('CASHFREE_PAYOUT_GET_BENEFICIARY'),
                http_build_query([
                    "bank_account_number" => $user->bankAccount->account_number,
                    "bank_ifsc" => $user->bankAccount->ifsc,
                ]),
                []
            );
            return $response->json();
        } catch (\Throwable $th) {
            return $th;
        }
    }
    public function createBeneficiaryWithBankDetails()
    {
        try {
            $user = Auth::user();

            $sellerID = str_replace('-', '_', $user->id);

            $response = Http::withHeaders($this->getHeaders())->post(env('CASHFREE_BASE') . env('CASHFREE_PAYOUT_GET_BENEFICIARY'), [
                "beneficiary_id" => $sellerID,
                "beneficiary_name" => $user->name,
                "beneficiary_instrument_details" => [
                    "bank_account_number" => $user->bankAccount->account_number,
                    "bank_ifsc" => $user->bankAccount->ifsc,
                ]
            ]);
            return $response->json();
        } catch (\Throwable $th) {
            return $th->getMessage();
        }
    }
    public function createBeneficiaryWithUPI()
    {
        try {
            // $user = User::find($id);
            $user = Auth::user();

            $sellerID = str_replace('-', '_', $user->id);


            $response = Http::withHeaders($this->getHeaders())->post(env('CASHFREE_BASE') . env('CASHFREE_PAYOUT_GET_BENEFICIARY'), [
                "beneficiary_id" => $sellerID,
                "beneficiary_name" => $user->name,
                "beneficiary_instrument_details" => [
                    "vpa" => $user->bankAccount->upi_id,
                ]
            ]);
            return $response->json();
        } catch (\Throwable $th) {
            return $th;
        }
    }
    public function getTransferStatusUsingRef($referenceID)
    {
        //incomplete
        try {

            //https://sandbox.cashfree.com/payout/transfers
            $response = Http::withHeaders($this->getHeaders())->get(env('CASHFREE_BASE') . env('CASHFREE_PAYOUT_TRANSFER'), http_build_query([
                // 'transfer_id' => '9e523210_133a_431a_9a6e_dd4cae3fd53w',
                'cf_transfer_id' => $referenceID,
            ]));
            return $response->json();
        } catch (\Throwable $th) {
            return $th;
        }
    }
    public function getTransferStatusUsingTransfer($transferID)
    {
        //incomplete
        try {

            //https://sandbox.cashfree.com/payout/transfers
            $response = Http::withHeaders($this->getHeaders())->get(env('CASHFREE_BASE') . env('CASHFREE_PAYOUT_TRANSFER'), http_build_query([
                // 'transfer_id' => '9e523210_133a_431a_9a6e_dd4cae3fd53w',
                'transfer_id' => $transferID,
            ]));
            return $response->json();
        } catch (\Throwable $th) {
            return $th;
        }
    }
    public function transferPayout($amount)
    {
        //incomplete
        try {
            $uuid = (string) Str::uuid();
            $sellerID = str_replace('-', '_', Auth::user()->id);
            //https://sandbox.cashfree.com/payout/transfers
            $response = Http::withHeaders(
                $this->getHeaders()
            )->post(env('CASHFREE_BASE') . env('CASHFREE_PAYOUT_TRANSFER'), [
                'transfer_id' => $uuid,
                'transfer_amount' => $amount,
                'beneficiary_details' => [
                    "beneficiary_id" => $sellerID,
                    "beneficiary_name" => Auth::user()->name,
                ]
            ]);
            return $response->json();
        } catch (\Throwable $th) {
            return $th;
        }
    }
    public function getAccountDetails()
    {
        try {
            $user = Auth::user();
            $totalTransactionValue = $user->paymentsAsSeller->sum('amount');
            $amountAfterDeduction = round(0.7 * $totalTransactionValue, 2);
            $amountWithdrawn = $user->payout->sum('amount');
            $amountLeftAfterWithdrawal = $amountAfterDeduction - $amountWithdrawn;
            return [
                'message' => 'success',
                'totalTransactionValue' => $totalTransactionValue,
                'amountAfterDeduction' => $amountAfterDeduction,
                'amountWithdrawn' => $amountWithdrawn,
                'amountLeftAfterWithdrawal' => $amountLeftAfterWithdrawal,
            ];
        } catch (\Throwable $th) {
            return ['message' => $th->getMessage()];
        }
    }
    public function verifyAccount($bankAcc, $ifsc)
    {

        $user = Auth::user();
        $responseOne = Http::withHeaders([
            'x-cf-signature' => $this->getSignature(),
            'x-client-id' => env('CASHFREE_PAYOUT_APP_ID'),
            'x-client-secret' => env('CASHFREE_PAYOUT_SECRET'),
            'x-api-version' => env('CASHFREE_PAYMENT_API_VERSION'),
        ])->post(env('CASHFREE_BASE') . env('VERIFY_BANK_ENDPOINT'), [
            'bank_account' => $bankAcc, //  '26291800001191',
            'ifsc' => $ifsc, // 'YESB0000001'
            'phone' => $user->phoneN->phone,
        ]);
        $result = $responseOne->json();

        return $result;
        // return ['status' => 'success', 'message' => 'Account verified', 'result' => $result,];
    }
}
