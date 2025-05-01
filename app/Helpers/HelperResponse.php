<?php

function HelperResponse($result, $message, $statusCode = 200, $data = [], $token = null)
{
    $response = [
        'result' => $result,
        'message' => $message,
        'data' => $data,
    ];
    if ($token) {
        $response['token'] = $token;
    }
    if ($result === 'error') {
        $response['error'] = true;
        if ($message instanceof Throwable) {
            $response['message'] = $message->getMessage();
            $response['debug'] = [
                'file' => $message->getFile(),
                'line' => $message->getLine(),
            ];
        }
    }

    return response()->json($response, $statusCode);
    // if ($token) {
    //     return response()->json([
    //         'result' => $result,
    //         'message' => $message,
    //         'data' => $data,
    //         'token' => $token
    //     ], $statusCode);
    // } else {
    //     return response()->json([
    //         'result' => $result,
    //         'message' => $message,
    //         'data' => $data,
    //     ], $statusCode);
    // }
}
