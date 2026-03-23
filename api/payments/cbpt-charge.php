<?php
/**
 * POST /api/payments/cbpt-charge.php
 * CCBill Charge By Previous Transaction ID API
 * 
 * Charges a fan's stored payment method using their previous subscription ID
 * No FlexForm needed for repeat purchases
 */

require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/database.php';
require_once __DIR__ . '/../../.secrets.php';

setCorsHeaders();

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

// Get request body
$input = getRequestBody();

// Validate input
$fanUserId = intval($input['fan_user_id'] ?? 0);
$amount = floatval($input['amount'] ?? 0);
$description = $input['description'] ?? 'One-time charge';

if (!$fanUserId || $amount <= 0) {
    jsonResponse(['error' => 'Invalid fan_user_id or amount'], 400);
}

if ($amount > 500) {
    jsonResponse(['error' => 'Amount cannot exceed $500'], 400);
}

// Check authentication (logged-in user OR secret for testing)
$currentUser = getCurrentUser();
$secretProvided = $input['secret'] ?? '';

if (!$currentUser && $secretProvided !== CHAT_CRON_SECRET) {
    jsonResponse(['error' => 'Authentication required'], 401);
}

$db = getDB();

try {
    // 1. Look up the fan's stored subscription ID
    $stmt = $db->prepare("SELECT ccbill_subscription_id, card_last_four, card_type FROM fan_payment_methods WHERE fan_user_id = :uid");
    $stmt->bindValue(':uid', $fanUserId, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $paymentMethod = $result->fetchArray(SQLITE3_ASSOC);

    if (!$paymentMethod || !$paymentMethod['ccbill_subscription_id']) {
        jsonResponse(['error' => 'No stored payment method found for this fan'], 404);
    }

    $storedSubId = $paymentMethod['ccbill_subscription_id'];
    $cardLastFour = $paymentMethod['card_last_four'];

    // 2. Determine if using FAK (test) or live account
    $isFAK = strpos($storedSubId, '9487') === 0 || $currentUser['role'] === 'admin';
    
    if ($isFAK) {
        $clientAccnum = CCBILL_FAK_ACCOUNT;
        $clientSubacc = CCBILL_FAK_SUB_ONETIME;
        $newClientAccnum = CCBILL_FAK_ACCOUNT;
        $newClientSubacc = CCBILL_FAK_SUB_ONETIME;
        $username = 'testuser'; // FAK test credentials
        $password = 'testpass';
    } else {
        $clientAccnum = CCBILL_ACCOUNT;
        $clientSubacc = CCBILL_SUB_ONETIME;
        $newClientAccnum = CCBILL_ACCOUNT;
        $newClientSubacc = CCBILL_SUB_ONETIME;
        
        // TODO: Add production DataLink credentials to .secrets.php
        // For now, use test credentials as placeholder
        $username = 'testuser';
        $password = 'testpass';
    }

    // 3. Build CCBill CBPT API request
    $apiParams = [
        'action' => 'chargeByPreviousTransactionId',
        'subscriptionId' => $storedSubId,
        'clientAccnum' => $clientAccnum,
        'clientSubacc' => $clientSubacc,
        'username' => $username,
        'password' => $password,
        'newClientAccnum' => $newClientAccnum,
        'newClientSubacc' => $newClientSubacc,
        'sharedAuthentication' => '1',
        'currencyCode' => '840', // USD
        'initialPrice' => number_format($amount, 2, '.', ''),
        'initialPeriod' => '30',
        'recurringPrice' => '0.00', // One-time charge
        'recurringPeriod' => '0',
        'rebills' => '0',
        'returnXML' => '1' // Get XML response for easier parsing
    ];

    // 4. Call CCBill's CBPT API
    $apiUrl = 'https://bill.ccbill.com/jpost/billingApi.cgi?' . http_build_query($apiParams);
    
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 30,
            'user_agent' => 'Breyya CBPT Client/1.0'
        ]
    ]);

    $response = file_get_contents($apiUrl, false, $context);
    
    if ($response === false) {
        throw new Exception('Failed to connect to CCBill API');
    }

    // Parse XML response
    $xml = simplexml_load_string($response);
    
    if (!$xml) {
        // Try CSV format fallback
        $lines = explode("\n", trim($response));
        if (count($lines) >= 2) {
            $fields = str_getcsv($lines[0]);
            $values = str_getcsv($lines[1]);
            $csvData = array_combine($fields, $values);
            
            $approved = $csvData['approved'] ?? '0';
            $newSubscriptionId = $csvData['subscriptionId'] ?? '';
            $declineCode = $csvData['declineCode'] ?? '';
            $declineText = $csvData['declineText'] ?? '';
        } else {
            throw new Exception('Invalid API response format');
        }
    } else {
        $approved = (string)$xml->approved;
        $newSubscriptionId = (string)$xml->subscriptionId;
        $declineCode = (string)$xml->declineCode;
        $declineText = (string)$xml->declineText;
    }

    // 5. Process the result
    if ($approved === '1') {
        // Success! Record the transaction
        $amountCents = intval($amount * 100);
        
        $stmt = $db->prepare("INSERT INTO transactions (user_id, type, amount_cents, description, reference_id, status) VALUES (:uid, 'tip', :amount, :desc, :ref, 'completed')");
        $stmt->bindValue(':uid', $fanUserId, SQLITE3_INTEGER);
        $stmt->bindValue(':amount', $amountCents, SQLITE3_INTEGER);
        $stmt->bindValue(':desc', $description, SQLITE3_TEXT);
        $stmt->bindValue(':ref', $newSubscriptionId, SQLITE3_TEXT);
        $stmt->execute();

        // Insert into tip_events
        $stmt = $db->prepare("INSERT INTO tip_events (fan_user_id, amount_cents, message) VALUES (:uid, :amount, :msg)");
        $stmt->bindValue(':uid', $fanUserId, SQLITE3_INTEGER);
        $stmt->bindValue(':amount', $amountCents, SQLITE3_INTEGER);
        $stmt->bindValue(':msg', $description, SQLITE3_TEXT);
        $stmt->execute();

        // Log successful payment
        error_log("CBPT Success: Fan $fanUserId charged $" . number_format($amount, 2) . " - $description");

        jsonResponse([
            'success' => true,
            'amount' => $amount,
            'description' => $description,
            'subscription_id' => $newSubscriptionId,
            'card_last_four' => $cardLastFour
        ]);

    } else {
        // Payment failed
        $errorMsg = $declineText ?: "Payment declined (Code: $declineCode)";
        
        // Log the failure
        error_log("CBPT Failed: Fan $fanUserId - $errorMsg");
        
        jsonResponse([
            'success' => false,
            'error' => $errorMsg,
            'decline_code' => $declineCode
        ], 402); // 402 Payment Required
    }

} catch (Exception $e) {
    error_log("CBPT Exception: " . $e->getMessage());
    jsonResponse(['error' => 'Payment processing failed'], 500);
} finally {
    $db->close();
}
?>