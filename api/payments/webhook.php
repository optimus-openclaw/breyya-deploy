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

// Log all webhook data for debugging
$rawBody = file_get_contents('php://input');
$logDir = __DIR__ . '/../../data/webhooks';
if (!is_dir($logDir)) mkdir($logDir, 0755, true);
file_put_contents("$logDir/" . date('Y-m-d_His') . '.json', $rawBody);

// CCBill sends form-encoded data
$data = $_POST ?: json_decode($rawBody, true) ?: [];
$eventType = $data['eventType'] ?? $data['event_type'] ?? '';

// Verify CCBill signature (TODO: implement with real CCBill salt)
// $expectedDigest = md5($data['subscriptionId'] . '...' . CCBILL_SALT);

$db = getDB();

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
