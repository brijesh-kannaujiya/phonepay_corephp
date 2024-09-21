<?php

// // Change these details with your actual PhonePe API credentials
// $merchantID = 'PGTESTPAYUAT'; // Sandbox or Live MerchantID
// $apiKey = '099eb0cd-02cf-4e2a-8aca-3e6c6aff0399'; // Sandbox or Live APIKEY

$merchantID = 'PGTESTPAYUAT86'; // Sandbox or Live MerchantID
$apiKey = '96434309-7796-489d-8924-ab56988a6076'; // Sandbox or Live APIKEY



// Create Redirect URL so that after payment it will redirect to success page
$redirectUrl = 'success.php';

// Retrieve form data
$order_id = uniqid();
$name = $_POST['fullname'];
$email = $_POST['email'];
$mobile = $_POST['mobile'];
$amount = $_POST['amount']; // amount in INR
$description = 'Payment for Product/Service';

// Add transaction details
$paymentData = array(
    'merchantId' => $merchantID,
    'merchantTransactionId' => $order_id,
    'amount' => $amount * 100, // Amount should be in paise
    'redirectUrl' => $redirectUrl,
    'redirectMode' => "POST",
    'callbackUrl' => $redirectUrl,
    'mobileNumber' => $mobile,
    'message' => $description,
    'email' => $email,
    'shortName' => $name,
    'paymentInstrument' => array(
        'type' => 'PAY_PAGE',
    )
);

$jsonencode = json_encode($paymentData);
$payloadMain = base64_encode($jsonencode);

// Prepare payload for API request
$salt_index = 1; // key index
$payload = $payloadMain . "/pg/v1/pay" . $apiKey;
$sha256 = hash("sha256", $payload);
$final_x_header = $sha256 . '###' . $salt_index;
$request = json_encode(array('request' => $payloadMain));

function sendRequest($request, $final_x_header)
{
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => "https://api-preprod.phonepe.com/apis/pg-sandbox/pg/v1/pay",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "POST",
        CURLOPT_POSTFIELDS => $request,
        CURLOPT_HTTPHEADER => [
            "Content-Type: application/json",
            "X-VERIFY: " . $final_x_header,
            "accept: application/json"
        ],
    ]);

    $response = curl_exec($curl);
    $error = curl_error($curl);
    curl_close($curl);

    if ($error) {
        return ['success' => false, 'error' => $error];
    }

    return ['success' => true, 'response' => $response];
}

// Retry mechanism
$retryCount = 0;
$maxRetries = 5;
$response = null;

while ($retryCount < $maxRetries) {
    $result = sendRequest($request, $final_x_header);

    if ($result['success']) {
        $response = json_decode($result['response']);
        if (isset($response->success) && $response->success == '1') {
            $payUrl = $response->data->instrumentResponse->redirectInfo->url;
            header('Location: ' . $payUrl);
            exit();
        } else {
            echo "Payment request failed: " . $response->message;
            break;
        }
    } else {
        echo "CURL Error #:" . $result['error'];
        $retryCount++;
        sleep(2); // Exponential backoff could be added here
    }
}
