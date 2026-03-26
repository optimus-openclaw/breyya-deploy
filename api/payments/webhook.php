<?php
/**
 * POST /api/payments/webhook
 * CCBill webhook handler for subscription events
 *
 * CCBill sends POST data with these events:
 * - NewSaleSuccess: New subscription created
 * - RenewalSuccess: Subscription renewed
 * - Cancellation: Subscription cancelled
 * - Expiration: Subscription expired
 * - Chargeback: Payment disputed
 */

require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/database.php';

// Log all webhook data for debugging
$rawBody = file_get_contents('php://input');
$logDir = __DIR__ . '/../../data/webhooks';
if (!is_dir($logDir)) mkdir($logDir, 0755, true);
file_put_contents("$logDir/" . date('Y-m-d_His') . '.json', $rawBody);

// CCBill sends form-encoded data
$data = $_POST ?: json_decode($rawBody, true) ?: [];
$eventType = $data['eventType'] ?? $data['event_type'] ?? '';

// CCBill CBPT upsales don't send eventType — detect from data fields
// UpSalesSuccess has: subscriptionId + email + billedInitialPrice + action=chargeByPreviousTransactionId
if (!$eventType && !empty($data['subscriptionId']) && !empty($data['email'])) {
    if (!empty($data['billedInitialPrice']) && empty($data['billedAmount'])) {
        // Likely an UpSalesSuccess webhook (CBPT charge)
        $eventType = 'UpSalesSuccess';
        error_log("Webhook: Detected UpSalesSuccess from data fields (no eventType sent)");
    } elseif (!empty($data['billedAmount']) || !empty($data['billedInitialPrice'])) {
        // Could be a NewSaleSuccess without eventType (legacy detection)
        $eventType = 'NewSaleSuccess';
        error_log("Webhook: Detected NewSaleSuccess from data fields (no eventType sent)");
    }
}

// Verify CCBill signature (TODO: implement with real CCBill salt)
// $expectedDigest = md5($data['subscriptionId'] . '...' . CCBILL_SALT);

$db = getDB();

// Create fan_payment_methods table if it doesn't exist
$createTableQuery = "CREATE TABLE IF NOT EXISTS fan_payment_methods (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    fan_user_id INTEGER NOT NULL,
    ccbill_subscription_id TEXT NOT NULL,
    card_last_four TEXT,
    card_type TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(fan_user_id, ccbill_subscription_id)
)";
$db->exec($createTableQuery);

switch ($eventType) {
    case 'NewSaleSuccess':
        $email = strtolower($data['email'] ?? '');
        $subscriptionId = $data['subscriptionId'] ?? '';
        $amount = intval(($data['billedAmount'] ?? 0) * 100);

        if (!$email) break;

        // Find or create user
        $stmt = $db->prepare('SELECT id FROM users WHERE email = :email');
        $stmt->bindValue(':email', $email, SQLITE3_TEXT);
        $result = $stmt->execute();
        $user = $result->fetchArray(SQLITE3_ASSOC);

        if (!$user) {
            // Create user with temporary password (they'll set it up later)
            $tempHash = password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT);
            $stmt = $db->prepare("INSERT INTO users (email, password_hash, display_name, role) VALUES (:email, :hash, :name, 'fan')");
            $stmt->bindValue(':email', $email, SQLITE3_TEXT);
            $stmt->bindValue(':hash', $tempHash, SQLITE3_TEXT);
            $stmt->bindValue(':name', explode('@', $email)[0], SQLITE3_TEXT);
            $stmt->execute();
            $userId = $db->lastInsertRowID();
        } else {
            $userId = $user['id'];
        }

        // Create subscription (30 days from now)
        $expiresAt = date('Y-m-d H:i:s', strtotime('+30 days'));
        $stmt = $db->prepare("INSERT INTO subscriptions (user_id, status, plan, price_cents, expires_at, ccbill_subscription_id) VALUES (:uid, 'active', 'monthly', :price, :exp, :subid)");
        $stmt->bindValue(':uid', $userId, SQLITE3_INTEGER);
        $stmt->bindValue(':price', $amount, SQLITE3_INTEGER);
        $stmt->bindValue(':exp', $expiresAt, SQLITE3_TEXT);
        $stmt->bindValue(':subid', $subscriptionId, SQLITE3_TEXT);
        $stmt->execute();

        // Record transaction
        $stmt = $db->prepare("INSERT INTO transactions (user_id, type, amount_cents, description, reference_id, status) VALUES (:uid, 'subscription', :amount, 'Monthly subscription', :ref, 'completed')");
        $stmt->bindValue(':uid', $userId, SQLITE3_INTEGER);
        $stmt->bindValue(':amount', $amount, SQLITE3_INTEGER);
        $stmt->bindValue(':ref', $subscriptionId, SQLITE3_TEXT);
        $stmt->execute();

        // Store payment method for future one-click payments
        $cardType = $data['cardType'] ?? $data['card_type'] ?? 'Card';
        $cardLast4 = $data['last4'] ?? $data['cardLast4'] ?? $data['card_last_four'] ?? '****';
        
        // Insert or update payment method
        $stmt = $db->prepare("INSERT OR REPLACE INTO fan_payment_methods (fan_user_id, ccbill_subscription_id, card_last_four, card_type, updated_at) VALUES (:uid, :subid, :last4, :type, datetime('now'))");
        $stmt->bindValue(':uid', $userId, SQLITE3_INTEGER);
        $stmt->bindValue(':subid', $subscriptionId, SQLITE3_TEXT);
        $stmt->bindValue(':last4', $cardLast4, SQLITE3_TEXT);
        $stmt->bindValue(':type', $cardType, SQLITE3_TEXT);
        $stmt->execute();
        
        error_log("Stored payment method for user $userId: $cardType ending in $cardLast4");
        break;

    case 'NewSaleTransactionSuccess':
    case 'TransactionSuccess':
        // Handle one-time purchases (tips/PPV) - also store payment method
        $email = strtolower($data['email'] ?? '');
        $subscriptionId = $data['subscriptionId'] ?? '';
        $amount = intval(($data['billedAmount'] ?? 0) * 100);

        if (!$email || !$subscriptionId) break;

        // Find user
        $stmt = $db->prepare('SELECT id FROM users WHERE email = :email');
        $stmt->bindValue(':email', $email, SQLITE3_TEXT);
        $result = $stmt->execute();
        $user = $result->fetchArray(SQLITE3_ASSOC);

        if ($user) {
            $userId = $user['id'];

            // Store payment method for future one-click payments
            $cardType = $data['cardType'] ?? $data['card_type'] ?? 'Card';
            $cardLast4 = $data['last4'] ?? $data['cardLast4'] ?? $data['card_last_four'] ?? '****';
            
            // Insert or update payment method
            $stmt = $db->prepare("INSERT OR REPLACE INTO fan_payment_methods (fan_user_id, ccbill_subscription_id, card_last_four, card_type, updated_at) VALUES (:uid, :subid, :last4, :type, datetime('now'))");
            $stmt->bindValue(':uid', $userId, SQLITE3_INTEGER);
            $stmt->bindValue(':subid', $subscriptionId, SQLITE3_TEXT);
            $stmt->bindValue(':last4', $cardLast4, SQLITE3_TEXT);
            $stmt->bindValue(':type', $cardType, SQLITE3_TEXT);
            $stmt->execute();
            
            error_log("Stored payment method for one-time purchase - user $userId: $cardType ending in $cardLast4");
        }
        break;

    case 'UpSalesSuccess':
        // CBPT one-click charge completed (tips, PPV, ratings)
        $email = strtolower($data['email'] ?? '');
        $subscriptionId = $data['subscriptionId'] ?? '';
        $amount = intval(floatval($data['billedInitialPrice'] ?? $data['initialPrice'] ?? $data['billedAmount'] ?? 0) * 100);
        
        if (!$email) {
            error_log("UpSalesSuccess webhook: no email provided");
            break;
        }
        
        // Find user
        $stmt = $db->prepare('SELECT id FROM users WHERE LOWER(email) = :email');
        $stmt->bindValue(':email', $email, SQLITE3_TEXT);
        $result = $stmt->execute();
        $user = $result->fetchArray(SQLITE3_ASSOC);
        
        if ($user) {
            $userId = $user['id'];
            
            // Record the transaction
            $db->exec("CREATE TABLE IF NOT EXISTS transactions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                type TEXT NOT NULL,
                amount_cents INTEGER NOT NULL,
                description TEXT,
                reference_id TEXT,
                status TEXT DEFAULT 'completed',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )");
            
            $stmt = $db->prepare("INSERT INTO transactions (user_id, type, amount_cents, description, reference_id, status) VALUES (:uid, 'tip', :amount, 'One-click charge (CBPT)', :ref, 'completed')");
            $stmt->bindValue(':uid', $userId, SQLITE3_INTEGER);
            $stmt->bindValue(':amount', $amount, SQLITE3_INTEGER);
            $stmt->bindValue(':ref', $subscriptionId, SQLITE3_TEXT);
            $stmt->execute();
            
            // Update payment method (in case card changed)
            $cardType = $data['cardType'] ?? $data['card_type'] ?? 'Card';
            $cardLast4 = $data['last4'] ?? $data['cardLast4'] ?? $data['card_last_four'] ?? '****';
            
            $stmt = $db->prepare("INSERT OR REPLACE INTO fan_payment_methods (fan_user_id, ccbill_subscription_id, card_last_four, card_type, updated_at) VALUES (:uid, :subid, :last4, :type, datetime('now'))");
            $stmt->bindValue(':uid', $userId, SQLITE3_INTEGER);
            $stmt->bindValue(':subid', $subscriptionId, SQLITE3_TEXT);
            $stmt->bindValue(':last4', $cardLast4, SQLITE3_TEXT);
            $stmt->bindValue(':type', $cardType, SQLITE3_TEXT);
            $stmt->execute();
            
            error_log("UpSalesSuccess: User $userId charged $" . number_format($amount/100, 2) . " via CBPT - sub: $subscriptionId");
        } else {
            error_log("UpSalesSuccess webhook: no user found for email $email");
        }
        break;

    case 'RenewalSuccess':
        $subscriptionId = $data['subscriptionId'] ?? '';
        if ($subscriptionId) {
            $newExpiry = date('Y-m-d H:i:s', strtotime('+30 days'));
            $stmt = $db->prepare("UPDATE subscriptions SET status = 'active', expires_at = :exp WHERE ccbill_subscription_id = :subid");
            $stmt->bindValue(':exp', $newExpiry, SQLITE3_TEXT);
            $stmt->bindValue(':subid', $subscriptionId, SQLITE3_TEXT);
            $stmt->execute();
        }
        break;

    case 'Cancellation':
        $subscriptionId = $data['subscriptionId'] ?? '';
        if ($subscriptionId) {
            $stmt = $db->prepare("UPDATE subscriptions SET status = 'cancelled' WHERE ccbill_subscription_id = :subid");
            $stmt->bindValue(':subid', $subscriptionId, SQLITE3_TEXT);
            $stmt->execute();
        }
        break;

    case 'Expiration':
        $subscriptionId = $data['subscriptionId'] ?? '';
        if ($subscriptionId) {
            $stmt = $db->prepare("UPDATE subscriptions SET status = 'expired' WHERE ccbill_subscription_id = :subid");
            $stmt->bindValue(':subid', $subscriptionId, SQLITE3_TEXT);
            $stmt->execute();
        }
        break;

    case 'Chargeback':
        $subscriptionId = $data['subscriptionId'] ?? '';
        if ($subscriptionId) {
            // Deactivate user on chargeback
            $stmt = $db->prepare("UPDATE subscriptions SET status = 'expired' WHERE ccbill_subscription_id = :subid");
            $stmt->bindValue(':subid', $subscriptionId, SQLITE3_TEXT);
            $stmt->execute();

            // Log the chargeback
            $stmt = $db->prepare("SELECT user_id FROM subscriptions WHERE ccbill_subscription_id = :subid");
            $stmt->bindValue(':subid', $subscriptionId, SQLITE3_TEXT);
            $result = $stmt->execute();
            $sub = $result->fetchArray(SQLITE3_ASSOC);
            if ($sub) {
                $stmt = $db->prepare("INSERT INTO transactions (user_id, type, amount_cents, description, reference_id, status) VALUES (:uid, 'refund', 0, 'Chargeback', :ref, 'completed')");
                $stmt->bindValue(':uid', $sub['user_id'], SQLITE3_INTEGER);
                $stmt->bindValue(':ref', $subscriptionId, SQLITE3_TEXT);
                $stmt->execute();
            }
        }
        break;
}

$db->close();

// Always respond 200 to CCBill
http_response_code(200);
echo 'OK';
