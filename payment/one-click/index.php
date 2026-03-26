<?php
/**
 * One-Click Payment Page
 * Allows fans with saved payment methods to make quick purchases
 */

require_once __DIR__ . '/../../api/lib/auth.php';
require_once __DIR__ . '/../../api/lib/database.php';

setCorsHeaders();

// Check authentication
$user = getCurrentUser();
if (!$user || $user['role'] !== 'fan') {
    // Redirect to login if not authenticated as fan
    header('Location: /login?redirect=' . urlencode('/payment/one-click/'));
    exit;
}

// Get amount from query parameter (for tips/PPV purchases)
$amount = floatval($_GET['amount'] ?? 20.00);
$description = $_GET['description'] ?? 'One-time charge';

// Validate amount
if ($amount <= 0 || $amount > 500) {
    $amount = 20.00;
    $description = 'One-time charge';
}

// Check user's subscription and payment method status
$db = getDB();
$hasActiveSubscription = false;
$subscriptionExpiry = null;

try {
    // Check for active subscription
    $stmt = $db->prepare("SELECT expires_at FROM subscriptions WHERE user_id = :uid AND status = 'active' AND expires_at > datetime('now') ORDER BY expires_at DESC LIMIT 1");
    $stmt->bindValue(':uid', $user['id'], SQLITE3_INTEGER);
    $result = $stmt->execute();
    $subscription = $result->fetchArray(SQLITE3_ASSOC);
    
    if ($subscription) {
        $hasActiveSubscription = true;
        $subscriptionExpiry = $subscription['expires_at'];
    }
} catch (Exception $e) {
    error_log("One-click subscription check error: " . $e->getMessage());
} finally {
    $db->close();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Quick Payment - Breyya</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  html, body { height: 100%; }
  body {
    font-family: 'DM Sans', -apple-system, sans-serif;
    background: #0d1b2a;
    color: #fff;
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
  }

  .container { 
    width: 100%; 
    max-width: 400px; 
    background: #13243a; 
    border-radius: 16px;
    padding: 28px; 
    text-align: center;
    box-shadow: 0 8px 30px rgba(0,0,0,0.6);
  }

  h1 { 
    font-size: 24px; 
    font-weight: 700; 
    margin-bottom: 8px; 
    color: #00b4d8; 
  }

  .amount { 
    font-size: 32px; 
    font-weight: 700; 
    margin-bottom: 16px; 
    color: #fff;
  }

  .description {
    font-size: 16px;
    color: #b0c4d4;
    margin-bottom: 24px;
  }

  .card-info {
    background: #1e2d3d;
    border-radius: 12px;
    padding: 16px;
    margin-bottom: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
  }

  .card-icon {
    font-size: 24px;
    filter: grayscale(1) brightness(1.2);
  }

  .card-text {
    font-size: 16px;
    color: #d0e4f0;
  }

  .btn-primary {
    display: block;
    width: 100%;
    background: #00b4d8;
    color: #fff;
    font-family: inherit;
    font-size: 18px;
    font-weight: 700;
    padding: 16px;
    border-radius: 50px;
    border: none;
    cursor: pointer;
    letter-spacing: 0.5px;
    margin-bottom: 16px;
    transition: all 0.2s;
    position: relative;
    overflow: hidden;
  }

  .btn-primary:hover {
    background: #0090b0;
    transform: scale(1.02);
  }

  .btn-primary:disabled {
    background: #556a7a;
    cursor: not-allowed;
    transform: none;
  }

  .btn-secondary {
    display: block;
    width: 100%;
    background: transparent;
    color: #00b4d8;
    font-family: inherit;
    font-size: 16px;
    font-weight: 600;
    padding: 14px;
    border-radius: 50px;
    border: 2px solid #00b4d844;
    cursor: pointer;
    text-decoration: none;
    transition: all 0.2s;
  }

  .btn-secondary:hover {
    border-color: #00b4d8;
    color: #7dd8f0;
  }

  .loading {
    display: none;
    align-items: center;
    justify-content: center;
    gap: 8px;
  }

  .spinner {
    width: 20px;
    height: 20px;
    border: 2px solid #ffffff44;
    border-top: 2px solid #fff;
    border-radius: 50%;
    animation: spin 1s linear infinite;
  }

  @keyframes spin {
    to { transform: rotate(360deg); }
  }

  .message {
    margin-top: 16px;
    padding: 12px;
    border-radius: 8px;
    font-weight: 600;
    display: none;
  }

  .message.success {
    background: #10b98122;
    border: 1px solid #10b981;
    color: #10b981;
  }

  .message.error {
    background: #ef444422;
    border: 1px solid #ef4444;
    color: #ef4444;
  }

  .no-card {
    text-align: center;
    color: #b0c4d4;
    margin-bottom: 24px;
  }

  .no-card h2 {
    color: #00b4d8;
    margin-bottom: 12px;
  }

  @media (min-width: 768px) {
    .container { max-width: 480px; padding: 36px; }
    h1 { font-size: 28px; }
    .amount { font-size: 40px; }
    .description { font-size: 18px; }
    .btn-primary { font-size: 20px; padding: 18px; }
    .btn-secondary { font-size: 18px; padding: 16px; }
  }
</style>
</head>
<body>
<div class="container">
  <h1>Quick Payment</h1>
  <div class="amount">$<?php echo number_format($amount, 2); ?></div>
  <div class="description"><?php echo htmlspecialchars($description); ?></div>

  <div id="loading-check" class="loading" style="display: flex; margin: 20px 0;">
    <div class="spinner"></div>
    <span>Checking payment method...</span>
  </div>

  <div id="payment-form" style="display: none;">
    <div class="card-info">
      <span class="card-icon" id="card-icon">💳</span>
      <span class="card-text" id="card-text">Loading...</span>
    </div>

    <button id="pay-btn" class="btn-primary" onclick="processPayment()">
      <span id="pay-text">Pay Now</span>
      <div class="loading" id="pay-loading">
        <div class="spinner"></div>
        <span>Processing...</span>
      </div>
    </button>

    <a href="/login" class="btn-secondary">Back to Profile</a>
  </div>

  <div id="no-card" style="display: none;">
    <div class="no-card">
      <h2 id="no-card-title">No Saved Payment Method</h2>
      <p id="no-card-message">You need to add a payment method first to use quick payments.</p>
    </div>
    <a href="#" class="btn-primary" id="add-payment-btn">
      Add Payment Method
    </a>
    <a href="/login" class="btn-secondary">Back to Profile</a>
  </div>

  <div id="need-subscription" style="display: none;">
    <div class="no-card">
      <h2>Subscribe First</h2>
      <p>You need to subscribe first to enable quick payments.</p>
    </div>
    <a href="https://api.ccbill.com/wap-frontflex/flexforms/d6c111d7-3565-4d8a-a3d7-211539a585f3?clientSubacc=0000&initialPrice=20.00&initialPeriod=30&recurringPrice=20.00&recurringPeriod=30&numRebills=99&currencyCode=840&formDigest=49e3e975243bc0e17baefda82ebfdd97" class="btn-primary">
      Subscribe Now
    </a>
    <a href="/login" class="btn-secondary">Back to Profile</a>
  </div>

  <div id="message" class="message"></div>
</div>

<script>
const amount = <?php echo json_encode($amount); ?>;
const description = <?php echo json_encode($description); ?>;
const fanUserId = <?php echo json_encode($user['id']); ?>;
const hasActiveSubscription = <?php echo json_encode($hasActiveSubscription); ?>;

let paymentMethod = null;

// Check for saved payment method on page load
async function checkSavedCard() {
  try {
    const response = await fetch('/api/payments/check-saved-card.php', {
      credentials: 'include'
    });
    
    if (!response.ok) {
      throw new Error('Failed to check payment method');
    }
    
    const data = await response.json();
    
    document.getElementById('loading-check').style.display = 'none';
    
    // Decision logic based on subscription status and saved card
    if (!hasActiveSubscription) {
      // No active subscription - show subscribe first message
      showNeedSubscription();
    } else if (data.has_saved_card) {
      // Has subscription + saved card - show CBPT payment form
      paymentMethod = data;
      showPaymentForm();
    } else {
      // Has subscription but no saved card - show "card wasn't saved" message with tip FlexForm
      showNoCardWithTipForm();
    }
    
  } catch (error) {
    console.error('Error checking saved card:', error);
    document.getElementById('loading-check').style.display = 'none';
    showError('Failed to load payment information');
  }
}

function showPaymentForm() {
  const cardIcon = paymentMethod.card_type === 'Visa' ? '💳' : 
                   paymentMethod.card_type === 'MasterCard' ? '💳' : '💳';
  
  document.getElementById('card-icon').textContent = cardIcon;
  document.getElementById('card-text').textContent = 
    `${paymentMethod.card_type} ending in ${paymentMethod.card_last_four}`;
  
  document.getElementById('payment-form').style.display = 'block';
}

function showNoCard() {
  document.getElementById('no-card').style.display = 'block';
}

function showNoCardWithTipForm() {
  const noCardDiv = document.getElementById('no-card');
  const titleEl = document.getElementById('no-card-title');
  const messageEl = document.getElementById('no-card-message');
  const addPaymentBtn = document.getElementById('add-payment-btn');
  
  titleEl.textContent = "Card Info Wasn't Saved";
  messageEl.textContent = "Your card info wasn't saved. Pay via secure form.";
  addPaymentBtn.textContent = "Pay via Secure Form";
  
  // Calculate tip FlexForm URL with proper digest
  const formattedAmount = parseFloat(amount).toFixed(2);
  const initialPeriod = '3';
  const currencyCode = '840';
  const salt = 'XXDzs2W4u9XtgNnXNQ4FyUgk';
  const digestString = formattedAmount + initialPeriod + currencyCode + salt;
  const digest = md5(digestString);
  
  const tipFormUrl = `https://api.ccbill.com/wap-frontflex/flexforms/d6c111d7-3565-4d8a-a3d7-211539a585f3?clientSubacc=0001&initialPrice=${formattedAmount}&initialPeriod=${initialPeriod}&recurringPrice=0.00&recurringPeriod=0&numRebills=0&currencyCode=${currencyCode}&formDigest=${digest}`;
  
  addPaymentBtn.href = tipFormUrl;
  noCardDiv.style.display = 'block';
}

function showNeedSubscription() {
  document.getElementById('need-subscription').style.display = 'block';
}

async function processPayment() {
  const payBtn = document.getElementById('pay-btn');
  const payText = document.getElementById('pay-text');
  const payLoading = document.getElementById('pay-loading');
  
  // Disable button and show loading
  payBtn.disabled = true;
  payText.style.display = 'none';
  payLoading.style.display = 'flex';
  
  try {
    const response = await fetch('/api/payments/cbpt-charge.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      credentials: 'include',
      body: JSON.stringify({
        fan_user_id: fanUserId,
        amount: amount,
        description: description
      })
    });
    
    const result = await response.json();
    
    if (result.success) {
      showMessage('success', 'Payment successful! ✅');
      setTimeout(() => {
        window.location.href = '/login'; // Redirect back to profile
      }, 2000);
    } else {
      showError(result.error || 'Payment failed');
      resetButton();
    }
    
  } catch (error) {
    console.error('Payment error:', error);
    showError('Payment processing failed');
    resetButton();
  }
}

function resetButton() {
  const payBtn = document.getElementById('pay-btn');
  const payText = document.getElementById('pay-text');
  const payLoading = document.getElementById('pay-loading');
  
  payBtn.disabled = false;
  payText.style.display = 'inline';
  payLoading.style.display = 'none';
}

function showMessage(type, text) {
  const messageEl = document.getElementById('message');
  messageEl.textContent = text;
  messageEl.className = `message ${type}`;
  messageEl.style.display = 'block';
}

function showError(text) {
  showMessage('error', text);
}

// MD5 function for FlexForm digest calculation
function md5(str) {
  function md5cycle(x, k) {
    var a = x[0], b = x[1], c = x[2], d = x[3];
    a = ff(a, b, c, d, k[0], 7, -680876936);
    d = ff(d, a, b, c, k[1], 12, -389564586);
    c = ff(c, d, a, b, k[2], 17, 606105819);
    b = ff(b, c, d, a, k[3], 22, -1044525330);
    a = ff(a, b, c, d, k[4], 7, -176418897);
    d = ff(d, a, b, c, k[5], 12, 1200080426);
    c = ff(c, d, a, b, k[6], 17, -1473231341);
    b = ff(b, c, d, a, k[7], 22, -45705983);
    a = ff(a, b, c, d, k[8], 7, 1770035416);
    d = ff(d, a, b, c, k[9], 12, -1958414417);
    c = ff(c, d, a, b, k[10], 17, -42063);
    b = ff(b, c, d, a, k[11], 22, -1990404162);
    a = ff(a, b, c, d, k[12], 7, 1804603682);
    d = ff(d, a, b, c, k[13], 12, -40341101);
    c = ff(c, d, a, b, k[14], 17, -1502002290);
    b = ff(b, c, d, a, k[15], 22, 1236535329);
    
    a = gg(a, b, c, d, k[1], 5, -165796510);
    d = gg(d, a, b, c, k[6], 9, -1069501632);
    c = gg(c, d, a, b, k[11], 14, 643717713);
    b = gg(b, c, d, a, k[0], 20, -373897302);
    a = gg(a, b, c, d, k[5], 5, -701558691);
    d = gg(d, a, b, c, k[10], 9, 38016083);
    c = gg(c, d, a, b, k[15], 14, -660478335);
    b = gg(b, c, d, a, k[4], 20, -405537848);
    a = gg(a, b, c, d, k[9], 5, 568446438);
    d = gg(d, a, b, c, k[14], 9, -1019803690);
    c = gg(c, d, a, b, k[3], 14, -187363961);
    b = gg(b, c, d, a, k[8], 20, 1163531501);
    a = gg(a, b, c, d, k[13], 5, -1444681467);
    d = gg(d, a, b, c, k[2], 9, -51403784);
    c = gg(c, d, a, b, k[7], 14, 1735328473);
    b = gg(b, c, d, a, k[12], 20, -1926607734);
    
    a = hh(a, b, c, d, k[5], 4, -378558);
    d = hh(d, a, b, c, k[8], 11, -2022574463);
    c = hh(c, d, a, b, k[11], 16, 1839030562);
    b = hh(b, c, d, a, k[14], 23, -35309556);
    a = hh(a, b, c, d, k[1], 4, -1530992060);
    d = hh(d, a, b, c, k[4], 11, 1272893353);
    c = hh(c, d, a, b, k[7], 16, -155497632);
    b = hh(b, c, d, a, k[10], 23, -1094730640);
    a = hh(a, b, c, d, k[13], 4, 681279174);
    d = hh(d, a, b, c, k[0], 11, -358537222);
    c = hh(c, d, a, b, k[3], 16, -722521979);
    b = hh(b, c, d, a, k[6], 23, 76029189);
    a = hh(a, b, c, d, k[9], 4, -640364487);
    d = hh(d, a, b, c, k[12], 11, -421815835);
    c = hh(c, d, a, b, k[15], 16, 530742520);
    b = hh(b, c, d, a, k[2], 23, -995338651);
    
    a = ii(a, b, c, d, k[0], 6, -198630844);
    d = ii(d, a, b, c, k[7], 10, 1126891415);
    c = ii(c, d, a, b, k[14], 15, -1416354905);
    b = ii(b, c, d, a, k[5], 21, -57434055);
    a = ii(a, b, c, d, k[12], 6, 1700485571);
    d = ii(d, a, b, c, k[3], 10, -1894986606);
    c = ii(c, d, a, b, k[10], 15, -1051523);
    b = ii(b, c, d, a, k[1], 21, -2054922799);
    a = ii(a, b, c, d, k[8], 6, 1873313359);
    d = ii(d, a, b, c, k[15], 10, -30611744);
    c = ii(c, d, a, b, k[6], 15, -1560198380);
    b = ii(b, c, d, a, k[13], 21, 1309151649);
    a = ii(a, b, c, d, k[4], 6, -145523070);
    d = ii(d, a, b, c, k[11], 10, -1120210379);
    c = ii(c, d, a, b, k[2], 15, 718787259);
    b = ii(b, c, d, a, k[9], 21, -343485551);
    
    x[0] = add32(a, x[0]);
    x[1] = add32(b, x[1]);
    x[2] = add32(c, x[2]);
    x[3] = add32(d, x[3]);
  }
  
  function cmn(q, a, b, x, s, t) {
    a = add32(add32(a, q), add32(x, t));
    return add32((a << s) | (a >>> (32 - s)), b);
  }
  
  function ff(a, b, c, d, x, s, t) {
    return cmn((b & c) | ((~b) & d), a, b, x, s, t);
  }
  
  function gg(a, b, c, d, x, s, t) {
    return cmn((b & d) | (c & (~d)), a, b, x, s, t);
  }
  
  function hh(a, b, c, d, x, s, t) {
    return cmn(b ^ c ^ d, a, b, x, s, t);
  }
  
  function ii(a, b, c, d, x, s, t) {
    return cmn(c ^ (b | (~d)), a, b, x, s, t);
  }
  
  function md51(s) {
    var n = s.length,
    state = [1732584193, -271733879, -1732584194, 271733878], i;
    for (i = 64; i <= s.length; i += 64) {
      md5cycle(state, md5blk(s.substring(i - 64, i)));
    }
    s = s.substring(i - 64);
    var tail = [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0];
    for (i = 0; i < s.length; i++)
      tail[i >> 2] |= s.charCodeAt(i) << ((i % 4) << 3);
    tail[i >> 2] |= 0x80 << ((i % 4) << 3);
    if (i > 55) {
      md5cycle(state, tail);
      for (i = 0; i < 16; i++) tail[i] = 0;
    }
    tail[14] = n * 8;
    md5cycle(state, tail);
    return state;
  }
  
  function md5blk(s) {
    var md5blks = [], i;
    for (i = 0; i < 64; i += 4) {
      md5blks[i >> 2] = s.charCodeAt(i) + (s.charCodeAt(i + 1) << 8) + (s.charCodeAt(i + 2) << 16) + (s.charCodeAt(i + 3) << 24);
    }
    return md5blks;
  }
  
  function rhex(n) {
    var hex_chr = '0123456789abcdef'.split('');
    var s = '', j = 0;
    for (; j < 4; j++)
      s += hex_chr[(n >> (j * 8 + 4)) & 0x0F] + hex_chr[(n >> (j * 8)) & 0x0F];
    return s;
  }
  
  function hex(x) {
    for (var i = 0; i < x.length; i++)
      x[i] = rhex(x[i]);
    return x.join('');
  }
  
  function add32(a, b) {
    return (a + b) & 0xFFFFFFFF;
  }
  
  return hex(md51(str));
}

// Start checking on page load
checkSavedCard();
</script>
</body>
</html>