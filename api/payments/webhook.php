<?php
/**
 * POST /api/payments/webhook
 * CCBill webhook handler for subscription and one-time payment events
 *
 * CCBill sends POST data with these events:
 * - NewSaleSuccess: New subscription or one-time purchase
 * - NewSaleFailure: Failed payment
 * - Cancellation: Subscription cancelled
 * - Chargeback: Payment disputed
 * - RenewalSuccess: Subscription renewed
 * - RenewalFailure: Renewal failed
 */

require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../../.secrets.php';

// Always return 200 for CCBill (they retry on non-200)
function respond($message = 'OK') {
    http_response_code(200);
    echo $message;
    exit;
}

// Log all webhook data for debugging and audit trail
$rawBody = file_get_contents('php://input');
$logDir = __DIR__ . '/../../data/webhooks';
if (!is_dir($logDir)) mkdir($logDir, 0755, true);
file_put_contents("$logDir/" . date('Y-m-d_His') . '_' . uniqid() . '.json', $rawBody);

// CCBill sends form-encoded data
$data = $_POST ?: json_decode($rawBody, true) ?: [];

// Extract key CCBill fields
$eventType = $data['eventType'] ?? $data['event_type'] ?? '';
$subscriptionId = $data['subscriptionId'] ?? '';
$clientAccnum = $data['clientAccnum'] ?? '';
$clientSubacc = $data['clientSubacc'] ?? '';
$email = strtolower($data['email'] ?? '');
$username = $data['username'] ?? '';
$firstName = $data['firstName'] ?? '';
$lastName = $data['lastName'] ?? '';
$initialPrice = $data['initialPrice'] ?? 0;
$initialPeriod = $data['initialPeriod'] ?? 0;
$recurringPrice = $data['recurringPrice'] ?? 0;
$recurringPeriod = $data['recurringPeriod'] ?? 0;
$numRebills = $data['numRebills'] ?? 0;
$currencyCode = $data['currencyCode'] ?? 'USD';
$formDigest = $data['formDigest'] ?? '';
$timestamp = $data['timestamp'] ?? date('Y-m-d H:i:s');

// Validate form digest for dynamic pricing (if provided)
if ($formDigest && $initialPrice) {
    $salt = ($clientAccnum === '948700') ? CCBILL_FAK_SALT : CCBILL_SALT;
    if ($salt) {
        $expectedDigest = md5($initialPrice . $initialPeriod . $recurringPrice . $recurringPeriod . $numRebills . $currencyCode . $salt);
        if ($expectedDigest !== $formDigest) {
            error_log("CCBill webhook: Invalid form digest. Expected: $expectedDigest, Got: $formDigest");
            respond('INVALID_DIGEST');
        }
    }
}

$db = getDB();

// Create payments_log table if it doesn't exist
$db->exec('CREATE TABLE IF NOT EXISTS payments_log (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    event_type TEXT NOT NULL,
    subscription_id TEXT,
    ccbill_sub_account TEXT,
    amount_cents INTEGER,
    email TEXT,
    username TEXT,
    fan_user_id INTEGER,
    raw_payload TEXT,
    created_at TEXT DEFAULT (datetime("now"))
)');

// Create fan_payment_methods table for CBPT functionality
$db->exec('CREATE TABLE IF NOT EXISTS fan_payment_methods (
    fan_user_id INTEGER PRIMARY KEY,
    ccbill_subscription_id TEXT NOT NULL,
    card_last_four TEXT DEFAULT "",
    card_type TEXT DEFAULT "",
    created_at TEXT DEFAULT (datetime("now")),
    updated_at TEXT DEFAULT (datetime("now")),
    FOREIGN KEY (fan_user_id) REFERENCES users(id)
)');

// Ensure tip_events table exists for tip handling
$db->exec('CREATE TABLE IF NOT EXISTS tip_events (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    fan_user_id INTEGER,
    amount_cents INTEGER,
    message TEXT,
    created_at TEXT DEFAULT (datetime("now")),
    FOREIGN KEY (fan_user_id) REFERENCES users (id)
)');

// Find user ID if we have email
$fanUserId = null;
if ($email) {
    $stmt = $db->prepare('SELECT id FROM users WHERE email = :email');
    $stmt->bindValue(':email', $email, SQLITE3_TEXT);
    $result = $stmt->execute();
    $user = $result->fetchArray(SQLITE3_ASSOC);
    $fanUserId = $user ? $user['id'] : null;
}

// Log the event in payments_log table
$stmt = $db->prepare('INSERT INTO payments_log (event_type, subscription_id, ccbill_sub_account, amount_cents, email, username, fan_user_id, raw_payload) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
$stmt->bindValue(1, $eventType, SQLITE3_TEXT);
$stmt->bindValue(2, $subscriptionId, SQLITE3_TEXT);
$stmt->bindValue(3, $clientSubacc, SQLITE3_TEXT);
$stmt->bindValue(4, intval($initialPrice * 100), SQLITE3_INTEGER);
$stmt->bindValue(5, $email, SQLITE3_TEXT);
$stmt->bindValue(6, $username, SQLITE3_TEXT);
$stmt->bindValue(7, $fanUserId, SQLITE3_INTEGER);
$stmt->bindValue(8, $rawBody, SQLITE3_TEXT);
$stmt->execute();

// Process the event
switch ($eventType) {
    case 'NewSaleSuccess':
        if (!$email) {
            error_log("CCBill webhook: NewSaleSuccess missing email");
            respond('MISSING_EMAIL');
        }

        $amount = intval($initialPrice * 100); // Convert to cents

        // Determine if this is membership (sub 0000) or one-time (sub 0001)
        if ($clientSubacc === '0000' || $clientSubacc === '0629') {
            // Membership subscription
            if (!$fanUserId) {
                // Create new user
                $tempHash = password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT);
                $displayName = $firstName && $lastName ? "$firstName $lastName" : ($username ?: explode('@', $email)[0]);
                
                $stmt = $db->prepare("INSERT INTO users (email, password_hash, display_name, role) VALUES (:email, :hash, :name, 'fan')");
                $stmt->bindValue(':email', $email, SQLITE3_TEXT);
                $stmt->bindValue(':hash', $tempHash, SQLITE3_TEXT);
                $stmt->bindValue(':name', $displayName, SQLITE3_TEXT);
                $stmt->execute();
                $fanUserId = $db->lastInsertRowID();
            }

            // Create or update subscription
            $expiresAt = date('Y-m-d H:i:s', strtotime('+30 days'));
            
            // Check if subscription already exists
            $stmt = $db->prepare('SELECT id FROM subscriptions WHERE ccbill_subscription_id = :subid');
            $stmt->bindValue(':subid', $subscriptionId, SQLITE3_TEXT);
            $result = $stmt->execute();
            $existing = $result->fetchArray(SQLITE3_ASSOC);

            if (!$existing) {
                $stmt = $db->prepare("INSERT INTO subscriptions (user_id, status, plan, price_cents, expires_at, ccbill_subscription_id) VALUES (:uid, 'active', 'monthly', :price, :exp, :subid)");
                $stmt->bindValue(':uid', $fanUserId, SQLITE3_INTEGER);
                $stmt->bindValue(':price', $amount, SQLITE3_INTEGER);
                $stmt->bindValue(':exp', $expiresAt, SQLITE3_TEXT);
                $stmt->bindValue(':subid', $subscriptionId, SQLITE3_TEXT);
                $stmt->execute();
            }

            // Record transaction
            $stmt = $db->prepare("INSERT INTO transactions (user_id, type, amount_cents, description, reference_id, status) VALUES (:uid, 'subscription', :amount, 'Monthly subscription', :ref, 'completed')");
            $stmt->bindValue(':uid', $fanUserId, SQLITE3_INTEGER);
            $stmt->bindValue(':amount', $amount, SQLITE3_INTEGER);
            $stmt->bindValue(':ref', $subscriptionId, SQLITE3_TEXT);
            $stmt->execute();

            // Store payment method for future CBPT charges
            if ($subscriptionId && $fanUserId) {
                $cardLastFour = $data['cardLastFour'] ?? $data['last4'] ?? '';
                $cardType = $data['cardType'] ?? '';
                
                $stmt = $db->prepare("INSERT OR REPLACE INTO fan_payment_methods (fan_user_id, ccbill_subscription_id, card_last_four, card_type, updated_at) VALUES (:uid, :subid, :last4, :type, datetime('now'))");
                $stmt->bindValue(':uid', $fanUserId, SQLITE3_INTEGER);
                $stmt->bindValue(':subid', $subscriptionId, SQLITE3_TEXT);
                $stmt->bindValue(':last4', $cardLastFour, SQLITE3_TEXT);
                $stmt->bindValue(':type', $cardType, SQLITE3_TEXT);
                $stmt->execute();
            }

        } else if ($clientSubacc === '0001' || $clientSubacc === '0630') {
            // One-time purchase (tip or PPV)
            if ($fanUserId) {
                // For now, treat all one-time purchases as tips
                // TODO: Distinguish between tips and PPV based on additional data
                
                // Insert into tip_events table
                $tipMessage = "Tip of $" . number_format($initialPrice, 2);
                $stmt = $db->prepare("INSERT INTO tip_events (fan_user_id, amount_cents, message) VALUES (:uid, :amount, :msg)");
                $stmt->bindValue(':uid', $fanUserId, SQLITE3_INTEGER);
                $stmt->bindValue(':amount', $amount, SQLITE3_INTEGER);
                $stmt->bindValue(':msg', $tipMessage, SQLITE3_TEXT);
                $stmt->execute();

                // Record transaction
                $stmt = $db->prepare("INSERT INTO transactions (user_id, type, amount_cents, description, reference_id, status) VALUES (:uid, 'tip', :amount, :desc, :ref, 'completed')");
                $stmt->bindValue(':uid', $fanUserId, SQLITE3_INTEGER);
                $stmt->bindValue(':amount', $amount, SQLITE3_INTEGER);
                $stmt->bindValue(':desc', $tipMessage, SQLITE3_TEXT);
                $stmt->bindValue(':ref', $subscriptionId, SQLITE3_TEXT);
                $stmt->execute();

                // Store payment method for future CBPT charges (first-time one-time purchase)
                if ($subscriptionId && $fanUserId) {
                    $cardLastFour = $data['cardLastFour'] ?? $data['last4'] ?? '';
                    $cardType = $data['cardType'] ?? '';
                    
                    // Check if we already have a payment method for this user
                    $stmt = $db->prepare("SELECT fan_user_id FROM fan_payment_methods WHERE fan_user_id = :uid");
                    $stmt->bindValue(':uid', $fanUserId, SQLITE3_INTEGER);
                    $result = $stmt->execute();
                    $existing = $result->fetchArray();
                    
                    if (!$existing) {
                        $stmt = $db->prepare("INSERT INTO fan_payment_methods (fan_user_id, ccbill_subscription_id, card_last_four, card_type, updated_at) VALUES (:uid, :subid, :last4, :type, datetime('now'))");
                        $stmt->bindValue(':uid', $fanUserId, SQLITE3_INTEGER);
                        $stmt->bindValue(':subid', $subscriptionId, SQLITE3_TEXT);
                        $stmt->bindValue(':last4', $cardLastFour, SQLITE3_TEXT);
                        $stmt->bindValue(':type', $cardType, SQLITE3_TEXT);
                        $stmt->execute();
                    }
                }
            }
        }
        break;

    case 'NewSaleFailure':
        // Log the failed payment - already logged in payments_log above
        error_log("CCBill webhook: Payment failed for $email, subscription $subscriptionId");
        break;

    case 'RenewalSuccess':
        if ($subscriptionId) {
            $newExpiry = date('Y-m-d H:i:s', strtotime('+30 days'));
            $amount = intval($recurringPrice * 100);

            $stmt = $db->prepare("UPDATE subscriptions SET status = 'active', expires_at = :exp WHERE ccbill_subscription_id = :subid");
            $stmt->bindValue(':exp', $newExpiry, SQLITE3_TEXT);
            $stmt->bindValue(':subid', $subscriptionId, SQLITE3_TEXT);
            $stmt->execute();

            // Record renewal transaction
            if ($fanUserId) {
                $stmt = $db->prepare("INSERT INTO transactions (user_id, type, amount_cents, description, reference_id, status) VALUES (:uid, 'renewal', :amount, 'Subscription renewal', :ref, 'completed')");
                $stmt->bindValue(':uid', $fanUserId, SQLITE3_INTEGER);
                $stmt->bindValue(':amount', $amount, SQLITE3_INTEGER);
                $stmt->bindValue(':ref', $subscriptionId, SQLITE3_TEXT);
                $stmt->execute();
            }
        }
        break;

    case 'RenewalFailure':
        if ($subscriptionId) {
            // Don't immediately cancel - CCBill will retry
            error_log("CCBill webhook: Renewal failed for subscription $subscriptionId");
        }
        break;

    case 'Cancellation':
        if ($subscriptionId) {
            $stmt = $db->prepare("UPDATE subscriptions SET status = 'cancelled' WHERE ccbill_subscription_id = :subid");
            $stmt->bindValue(':subid', $subscriptionId, SQLITE3_TEXT);
            $stmt->execute();
        }
        break;

    case 'Expiration':
        if ($subscriptionId) {
            $stmt = $db->prepare("UPDATE subscriptions SET status = 'expired' WHERE ccbill_subscription_id = :subid");
            $stmt->bindValue(':subid', $subscriptionId, SQLITE3_TEXT);
            $stmt->execute();
        }
        break;

    case 'Chargeback':
        if ($subscriptionId) {
            // Immediately deactivate on chargeback
            $stmt = $db->prepare("UPDATE subscriptions SET status = 'expired' WHERE ccbill_subscription_id = :subid");
            $stmt->bindValue(':subid', $subscriptionId, SQLITE3_TEXT);
            $stmt->execute();

            // Log the chargeback transaction
            if ($fanUserId) {
                $stmt = $db->prepare("INSERT INTO transactions (user_id, type, amount_cents, description, reference_id, status) VALUES (:uid, 'refund', 0, 'Chargeback', :ref, 'completed')");
                $stmt->bindValue(':uid', $fanUserId, SQLITE3_INTEGER);
                $stmt->bindValue(':ref', $subscriptionId, SQLITE3_TEXT);
                $stmt->execute();
            }
        }
        break;

    default:
        error_log("CCBill webhook: Unknown event type: $eventType");
        break;
}

$db->close();
respond('OK');
?>