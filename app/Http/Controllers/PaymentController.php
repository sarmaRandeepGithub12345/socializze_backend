<?php

namespace App\Http\Controllers;

use App\Models\BankDetails;
use App\Models\Payment;
use App\Models\Payouts;
use App\Models\User;
use App\Services\PayoutService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Cashfree\Cashfree;
use Cashfree\Model\CreateOrderRequest;
use Cashfree\Model\CustomerDetails;
use Cashfree\Model\OrderMeta;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Intervention\Image\Colors\Rgb\Channels\Red;

class PaymentController extends Controller
{
    protected $payoutService;
    public function __construct(PayoutService $payoutService)
    {
        $this->payoutService = $payoutService;
    }
    public function createOrder(Request $request)
    {
        $validation = Validator::make($request->all(), [
            'seller_id' => 'required|uuid|exists:users,id',
            'currency' => 'required|string|min:3|max:5|alpha', // Ensures it's only alphabets and 3 to 5 characters
            // 'phone' => 'required|digits:10', // Exactly 10 digits
            'amount' => 'required|numeric|min:0.01', // Decimal amount, minimum 0.01
        ]);

        if ($validation->fails()) {
            return HelperResponse('error', $validation->errors()->first(), 422, $validation->errors()->messages());
        }

        try {
            $seller = User::find($request->seller_id);
            // $phoneCheck = $seller->phoneN->exists();
            // $bankAcc = $seller->bankAccount->exists();
            if ($seller->id == Auth::user()->id) {
                return HelperResponse('error', 'Customer and seller are same', 422);
            }
            //creating uuid to self self assign id
            $user = Auth::user();

            $customerID = $this->payoutService->convertToDash($user->id);

            Cashfree::$XClientId = env('CASHFREE_PAYMENT_APP_ID');
            Cashfree::$XClientSecret = env('CASHFREE_PAYMENT_SECRET');

            // Cashfree::$XEnvironment = Cashfree::$PRODUCTION;
            Cashfree::$XEnvironment = Cashfree::$SANDBOX;


            $cashfree = new Cashfree();

            $x_api_version = env('CASHFREE_PAYMENT_API_VERSION');
            //replacing - with _ for cashfree acceptability
            $uuid = (string) Str::uuid();

            $order_id = $uuid; //$this->payoutService->convertToDash($uuid); //'inv_' . date('YmdHis');
            $order_amount = $request->amount;

            // $customer_phone = "8876812115";

            $customer_email = $user->email;
            $customer_name = $user->name;

            // $return_url = 'http://127.0.0.1:8000/success/' . $order_id;

            $create_orders_request = new CreateOrderRequest();
            $create_orders_request->setOrderId($order_id);
            $create_orders_request->setOrderAmount($order_amount);
            $create_orders_request->setOrderCurrency($request->currency);
            $customer_details = new CustomerDetails();
            $customer_details->setCustomerId($customerID);
            //phone number 
            // $country_code = $user->phoneN->country_code;
            $phoneNumber = $user->phoneN->phone;
            $customer_details->setCustomerPhone(strval($phoneNumber));

            $customer_details->setCustomerEmail($customer_email);
            $customer_details->setCustomerName($customer_name);

            $create_orders_request->setCustomerDetails($customer_details);
            // $order_meta = new OrderMeta();
            // $order_meta->setReturnUrl($return_url);

            // $create_orders_request->setOrderMeta($order_meta);
            $result = $cashfree->PGCreateOrder($x_api_version, $create_orders_request);


            $order = Payment::create([
                'id' => $order_id,
                'customer_id' => Auth::user()->id,
                'seller_id' => $request->seller_id,
                //type =null since payment not complete
                'currency' => $result[0]["order_currency"],
                // 'order_id' => $result[0]["order_id"],
                'amount' => $result[0]["order_amount"],
                'expire_at' => $result[0]["order_expiry_time"],
                'session_id' => $result[0]["payment_session_id"],
                'status' => $result[0]["order_status"],

            ]);

            // dd($result);
            // $payment_session_id = $result[0]['payment_session_id'];

            return HelperResponse('success', 'Order Created', 201, [
                'Order' => $order,
            ]);
        } catch (\Throwable $th) {
            return HelperResponse('error', $th->getMessage(), 422, $th);
        }
    }

    public function completeOrder(Request $request)
    {
        $validation = Validator::make($request->all(), [
            'order_id' => 'required',
        ]);

        if ($validation->fails()) {
            return HelperResponse('error', $validation->errors()->first(), 422, $validation->errors()->messages());
        }

        try {
            // $order = Payment::where("order_id", $request->order_id)->first();
            $order = Payment::find($request->order_id);
            $orderID = $order->id;
            if ($order != null) {
                $response = Http::withHeaders([
                    'x-client-id' => env('CASHFREE_PAYMENT_APP_ID'),
                    'x-client-secret' => env('CASHFREE_PAYMENT_SECRET'),
                    'x-api-version' => env('CASHFREE_PAYMENT_API_VERSION'),
                ])->get(env('CASHFREE_BASE_URL') . '/pg/orders/' . $orderID . '/payments', [
                    // 'order_id' => $order_id,
                ]);
                $resp = $response->json();



                $status = $resp[0]["payment_status"];
                //update last three
                $transaction_id = $resp[0]["cf_payment_id"];
                $type = $resp[0]["payment_group"];
                $message = $resp[0]["payment_message"];

                //changing null values after status
                $order->payment_message = $message;

                $order->status = $status;

                $order->type = $type;
                $order->transaction_id = $transaction_id;
                $order->save();
                // if ($status == "SUCCESS") {
                // }

                // Cashfree::$XEnvironment = Cashfree::$PRODUCTION;
                // dd($result);
                // $payment_session_id = $result[0]['payment_session_id'];

                return HelperResponse('success', 'Payment Record', 201, [
                    'Order' => [
                        'id' => $order->id,
                        'seller_id' => $order->seller_id,
                        'customer_id' => $order->customer_id,
                        'currency' => $order->currency,
                        'amount' => $order->amount,
                        //expire_at 
                        'session_id' => $order->session_id,
                        'status' => $order->status,
                        'payment_message' => $order->payment_message,
                        'type' => $order->type,
                        'transaction_id' => $order->transaction_id,
                        'created_at' => $order->created_at,
                        'updated_at' => $order->updated_at,
                        'name_seller' => $order->seller->name,
                        'name_customer' => $order->customer->name,
                    ],
                ]);
            }
            return HelperResponse('Failure', 'Not record found', 400,);

            // return env('CASHFREE_BASE_URL') . '/pg/orders/' . $order_id . '/payments';

        } catch (\Throwable $th) {
            return HelperResponse('error', $th->getMessage(), 422, $th);
        }
    }
    public function getPaymentsReceived()
    {
        try {
            $payments = Payment::where('seller_id', Auth::user()->id)->paginate(3);
            $payments->transform(function ($payment) {
                return [
                    'id' => $payment->id,
                    'seller_id' => $payment->seller_id,
                    'customer_id' => $payment->customer_id,
                    'currency' => $payment->currency,
                    'amount' => $payment->amount,
                    //expire_at 
                    'session_id' => $payment->session_id,
                    'status' => $payment->status,
                    'payment_message' => $payment->payment_message,
                    'type' => $payment->type,
                    'transaction_id' => $payment->transaction_id,
                    'created_at' => $payment->created_at,
                    'updated_at' => $payment->updated_at,
                    'name_seller' => $payment->seller->name,
                    'name_customer' => $payment->customer->name,
                ];
            });
            return HelperResponse('success', 'Money received', 200, [
                'received' => $payments
            ]);
        } catch (\Throwable $th) {
            return HelperResponse('error', $th->getMessage(), 422, $th);
        }
    }
    public function getPaymentsSent()
    {
        try {
            $payments = Payment::where('customer_id', Auth::user()->id)
                // ->whereNotIn('status', ['ACTIVE'])
                ->paginate(3);
            $payments->transform(function ($payment) {
                return [
                    'id' => $payment->id,
                    'seller_id' => $payment->seller_id,
                    'customer_id' => $payment->customer_id,
                    'currency' => $payment->currency,
                    'amount' => $payment->amount,
                    //expire_at 
                    'session_id' => $payment->session_id,
                    'status' => $payment->status,
                    'payment_message' => $payment->payment_message,
                    'type' => $payment->type,
                    'transaction_id' => $payment->transaction_id,
                    'created_at' => $payment->created_at,
                    'updated_at' => $payment->updated_at,
                    'name_seller' => $payment->seller->name,
                    'name_customer' => $payment->customer->name,
                ];
            });

            return HelperResponse('success', 'Money received', 200, [
                'sent' => $payments
            ]);
        } catch (\Throwable $th) {
            return HelperResponse('error', $th->getMessage(), 422, $th);
        }
    }
    // public function getHook(Request $request)
    // {
    //     // Log request for debugging
    //     Log::info('Cashfree Webhook Received:', $request->all());

    //     // Verify Cashfree signature (optional, recommended)
    //     $secretKey = env('CASHFREE_SECRET_KEY');
    //     $calculatedSignature = hash_hmac('sha256', json_encode($request->all()), $secretKey);

    //     if ($request->header('x-webhook-signature') !== $calculatedSignature) {
    //         return response()->json(['error' => 'Invalid Signature'], 403);
    //     }

    //     // Extract payment details
    //     $orderId = $request->input('order.order_id');
    //     $paymentStatus = $request->input('order.payment_status');

    //     // Find the order in your database
    //     $order = Order::where('order_id', $orderId)->first();

    //     if (!$order) {
    //         return response()->json(['error' => 'Order not found'], 404);
    //     }

    //     // Update order status based on payment result
    //     if ($paymentStatus === 'SUCCESS') {
    //         $order->status = 'paid';
    //     } elseif ($paymentStatus === 'FAILED') {
    //         $order->status = 'failed';
    //     } else {
    //         $order->status = 'pending';
    //     }

    //     $order->save();

    //     return response()->json(['message' => 'Webhook processed successfully']);
    // }

    public function getAmount()
    {
        try {
            $details = $this->payoutService->getAccountDetails();
            return HelperResponse('success', 'Transaction details found', 200, $details,);
        } catch (\Throwable $th) {
            return HelperResponse('error', $th->getMessage(), 422, $th);
        }
    }
    public function payout(Request $request)
    {

        $validation = Validator::make($request->all(), [
            'amount' => [
                'required',
                'numeric',
                'regex:/^\d+(\.\d{1,2})?$/',
                'min:1.00',
            ],
        ]);
        if ($validation->fails()) {
            return HelperResponse('error', $validation->errors()->first(), 422, $validation->errors()->messages());
        }

        try {
            $seller = Auth::user();
            $sellerId = $seller->id;

            $user = User::find($sellerId);

            $respOne = $this->payoutService->authorizePayout();

            if ($respOne['subCode'] != 200 ||  $respOne["status"] != "SUCCESS") {
                return HelperResponse('error', $respOne['message'], 422);
            }


            $token = $respOne["data"]["token"];

            $respTwo = $this->payoutService->verifyToken($token);

            //if token has failed
            if ($respTwo['subCode'] != 200) {

                return HelperResponse('error', $respTwo['message'], 422);
            }
            //to find beneficiary ,either 1)id or 1)bankAcc/IFSC is needed


            $respThree = $this->payoutService->getBeneficiaryUsingBenID($sellerId);

            $code = array_key_exists("code", $respThree) ? $respThree["code"] : null;
            // $status = $respThree["message"];
            if ($code == "beneficiary_not_found") {
                //to create beneficiary ,we need 1)(bank account number ,bank ifsc number) or 1)(upi id) and 2)beneficiary name and 3)id.
                $check = $user->bankAccount->exists();
                //Bank account details not present
                if ($check == null) {
                    return HelperResponse('error', 'Bank details not available', 422);
                }

                if ($user->bankAccount->account_number == null) {
                    $newBeneFiStatus =  $this->payoutService->createBeneficiaryWithUPI();
                } else {
                    $newBeneFiStatus = $this->payoutService->createBeneficiaryWithBankDetails();
                }
                //if error exists in creating beneficiary account
                $newcode = array_key_exists("type", $newBeneFiStatus) ? $newBeneFiStatus["type"] : null;
                if ($newcode != null) {
                    $message = explode(':', $newBeneFiStatus["message"])[1];
                    return HelperResponse('error', $message, 422);
                }
            }

            $amt = $request->amount;
            $details = $this->payoutService->getAccountDetails();
            //check amount left
            if ($details["message"] != "success") {
                return HelperResponse('error', $details['message'], 422);
            }
            //checking if money left in account is more than amount sought
            if ($details['amountLeftAfterWithdrawal'] < $amt) {
                return HelperResponse('error', 'Insufficient funds', 422);
            }


            $respFour = $this->payoutService->transferPayout($amt);

            $payoutRecord = Payouts::create([
                'id' => $respFour["transfer_id"],
                'beneficiary_id' => $sellerId,
                'amount' => $amt,
                'reference_id' => $respFour["cf_transfer_id"],
                'status' => $respFour["status_code"] == "RECEIVED" ? 'accepted' : ($respFour["status_code"] == "COMPLETED" ? 'accepted' : "rejected")
            ]);
            $status =  $respFour["status_code"] == "RECEIVED" ? 'success' : ($respFour["status_code"] == "COMPLETED" ? 'success' : "error");
            //202
            $statusCode =  $respFour["status_code"] == "RECEIVED" ? 201 : ($respFour["status_code"] == "COMPLETED" ? 201 : 422);
            $message = $respFour["status_description"];
            $amount = $this->payoutService->getAccountDetails();

            return HelperResponse($status, $message, $statusCode, [
                'payout' => $payoutRecord,
                'amount' => $amount,
                // 'CASHFREE' => $respFour,
            ]);

            //2nd
            // {
            //     "beneficiary_id": "9e523210_133a_431a_9a6e_dd4cae3fd57b",
            //     "beneficiary_name": "John Doe",
            //     "beneficiary_instrument_details": {
            //         "bank_account_number": "00011020001772",
            //         "bank_ifsc": "HDFC0000001"
            //     },
            //     "beneficiary_status": "VERIFIED",
            //     "added_on": "2025-03-28T22:42:24"
            // }
            //1st
            // {
            //     "beneficiary_id": "9e523210_133a_431a_9a6e_dd4cae3fd57b",
            //     "beneficiary_name": "John Doe",
            //     "beneficiary_instrument_details": {
            //         "bank_account_number": "00011020001772",
            //         "bank_ifsc": "HDFC0000001"
            //     },
            //     "beneficiary_status": "VERIFIED",
            //     "added_on": "2025-03-28T22:42:25"
            // }

            //After beneficiary verification is done


        } catch (\Throwable $th) {
            return HelperResponse('error', $th->getMessage(), 422, $th);
        }
    }

    public function getWithdrawals()
    {
        try {
            $withdrawals = Payouts::where('beneficiary_id', Auth::user()->id)->orderBy('updated_at', 'desc')->paginate(2);

            return HelperResponse("success", "Withdrawals", 200, [
                'withdrawals' => $withdrawals,
            ]);
        } catch (\Throwable $th) {
            return HelperResponse('error', $th->getMessage(), 422, $th);
        }
    }

    public function verifyBankAcc(Request $request)
    {
        $validation = Validator::make($request->all(), [

            'bank_account' => [
                'required',
                'string',
                'regex:/^\d{9,18}$/', // Bank account numbers are typically 9 to 18 digits
            ],

            // IFSC Code Validation
            'ifsc' => [
                'required',
                'string',
                'regex:/^[A-Z]{4}0[A-Z0-9]{6}$/', // IFSC: 4 letters, 0, and 6 alphanumeric characters
            ],


        ]);

        if ($validation->fails()) {
            return HelperResponse('error', $validation->errors()->first(), 422, $validation->errors()->messages());
        }

        // try {

        $user = Auth::user();
        // https: //sandbox.cashfree.com/verification/bank-account/sync

        $responseOne = $this->payoutService->verifyAccount($request->bank_account, $request->ifsc);
        $code = array_key_exists("code", $responseOne) ? $responseOne["code"] : null;


        if ($code != null) {
            return HelperResponse('error', $responseOne["message"], 422);
        }

        $bankDetailCheck = BankDetails::where('user_id', $user->id)->exists();
        //bank details update

        if ($bankDetailCheck != null) {
            $bankDetail = BankDetails::where('user_id', $user->id)->first();

            $bankDetail->account_number = $request->bank_account;
            $bankDetail->ifsc = $request->ifsc;
            $bankDetail->save();
            return HelperResponse('success', 'Account found', 200, [
                'bankDetail' => $bankDetail,
            ]);
        }
        $bankDetail = BankDetails::create([
            'user_id' => $user->id,
            'type' => 0,
            'account_number' => $request->bank_account,
            'ifsc' => $request->ifsc,
        ]);
        return HelperResponse('success', 'Account made', 200, [
            'bankDetail' => $bankDetail,
        ]);
        // } catch (\Throwable $th) {
        //     return HelperResponse('error', $th->getMessage(), 422, $th);
        //  }
    }
    public function verifyUpiID(Request $request)
    {

        $validation = Validator::make($request->all(), [
            'upi_id' =>  [
                'required',
                'string',
                'regex:/^[\w.-]+@[\w.-]+$/', // Bank account numbers are typically 9 to 18 digits
            ],

        ]);
        if ($validation->fails()) {
            return HelperResponse('error', $validation->errors()->first(), 422, $validation->errors()->messages());
        }
        try {
            $user = Auth::user();
            $respOne = $this->payoutService->authorizePayout();

            if ($respOne['subCode'] != 200 ||  $respOne["status"] != "SUCCESS") {
                return HelperResponse('error', $respOne['message'], 422);
            }


            $token = $respOne["data"]["token"];
            return 'Bearer ' . $token;
            $responseOne = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',

            ])->get(
                env('CASHFREE_BASE_URL') . env('VERIFY_UPI_ID'),
                http_build_query([
                    'name' => $user->name, // 'John Doe',
                    'vpa' => $request->upi_id, //$user->phoneN->phone,
                ]),
                []
            );
            return HelperResponse('success', 'UPI found', 200, [
                'results' => $responseOne->json(),
            ]);
        } catch (\Throwable $th) {
            return HelperResponse('error', $th->getMessage(), 422, $th);
        }
    }
    public function test()
    {




        $response = Http::withHeaders($this->payoutService->getHeaders())->post(env('CASHFREE_BASE') . env('CASHFREE_PAYOUT_GET_BENEFICIARY'), [
            "beneficiary_id" => "rcrfcrfrfcrfrf",
            "beneficiary_name" => "tgfvtgvtgtgv",
            "beneficiary_instrument_details" => [
                "bank_account_number" => "rfcrfcrfc",
                "bank_ifsc" => "rcrdcrdc",
            ]
        ]);
        return $response->json();
        // return $this->payoutService->getBeneficiaryUsingBenID();
    }
}
