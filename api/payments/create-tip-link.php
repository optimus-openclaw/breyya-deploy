<?php
/**
 * CCBill Tip Payment Link Generator
 * Generates one-time tip payment URLs for CCBill FlexForm
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// CCBill Configuration
define('CCBILL_FLEX_ID', 'd6c111d7-3565-4d8a-a3d7-211539a585f3');
define('CCBILL_SALT', 'XXDzs2W4u9JXgNnXNQ4FyUgk');
define('TIP_SUBACCOUNT', '0001'); // Sub-account for one-time tips
define('TIP_PERIOD', '30'); // Required period for one-time purchases
define('CURRENCY_CODE', '840'); // USD

function generateTipURL($amount) {
    // Validate amount
    $amount = floatval($amount);
    if ($amount <= 0 || $amount > 500) {
        throw new InvalidArgumentException('Amount must be between $0.01 and $500.00');
    }
    
    // Format amount to 2 decimal places
    $formattedAmount = number_format($amount, 2, '.', '');
    
    // Generate form digest for one-time payment
    // formDigest = MD5(initialPrice + initialPeriod + currencyCode + salt)
    $digestString = $formattedAmount . TIP_PERIOD . CURRENCY_CODE . CCBILL_SALT;
    $formDigest = md5($digestString);
    
    // Build URL parameters
    $params = [
        'clientSubacc' => TIP_SUBACCOUNT,
        'initialPrice' => $formattedAmount,
        'initialPeriod' => TIP_PERIOD,
        'currencyCode' => CURRENCY_CODE,
        'formDigest' => $formDigest
    ];
    
    // Build complete URL
    $baseURL = 'https://api.ccbill.com/wap-frontflex/flexforms/' . CCBILL_FLEX_ID;
    $queryString = http_build_query($params);
    
    return $baseURL . '?' . $queryString;
}

function sendResponse($success, $data = null, $error = null) {
    $response = ['success' => $success];
    
    if ($success && $data !== null) {
        $response['data'] = $data;
    }
    
    if (!$success && $error !== null) {
        $response['error'] = $error;
    }
    
    echo json_encode($response);
    exit;
}

try {
    // Get amount parameter
    $amount = null;
    
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $amount = $_GET['amount'] ?? null;
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $amount = $input['amount'] ?? $_POST['amount'] ?? null;
    }
    
    if (!$amount) {
        sendResponse(false, null, 'Amount parameter is required');
    }
    
    // Generate the CCBill URL
    $tipURL = generateTipURL($amount);
    
    // Return successful response
    sendResponse(true, [
        'amount' => floatval($amount),
        'url' => $tipURL,
        'expires_at' => date('Y-m-d H:i:s', strtotime('+1 hour')) // URLs typically expire after some time
    ]);
    
} catch (InvalidArgumentException $e) {
    sendResponse(false, null, $e->getMessage());
} catch (Exception $e) {
    error_log('CCBill tip link generation error: ' . $e->getMessage());
    sendResponse(false, null, 'Failed to generate payment link');
}
?>