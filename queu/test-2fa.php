<?php
require_once 'config/database.php';
require_once 'includes/GoogleAuthenticator.php';

$database = new Database();
$db = $database->getConnection();
$ga = new GoogleAuthenticator();

// Get admin user
$query = "SELECT * FROM users WHERE username = 'admin'";
$stmt = $db->query($query);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

echo "<h1>2FA Test</h1>";

if (!$user) {
    die("Admin user not found");
}

echo "<h3>User: " . $user['username'] . "</h3>";
echo "<p>2FA Enabled: " . ($user['twofa_enabled'] ? 'YES' : 'NO') . "</p>";
echo "<p>2FA Secret: " . ($user['twofa_secret'] ?? 'NOT SET') . "</p>";

// If no secret, generate one
if (empty($user['twofa_secret'])) {
    $secret = $ga->createSecret();
    echo "<p style='color:blue'>Generated new secret: $secret</p>";
    
    $update = "UPDATE users SET twofa_secret = ? WHERE id = ?";
    $update_stmt = $db->prepare($update);
    $update_stmt->execute([$secret, $user['id']]);
    
    $user['twofa_secret'] = $secret;
}

// Generate current code
$current_code = $ga->getCode($user['twofa_secret']);
echo "<h2>Current 6-digit code: <span style='font-size:32px;color:green'>$current_code</span></h2>";

// Test verification
$test_code = $current_code;
$result = $ga->verifyCode($user['twofa_secret'], $test_code, 2);
echo "<p>Verification test with current code: " . ($result ? '✅ SUCCESS' : '❌ FAILED') . "</p>";

// Generate QR code URL
$qr_url = $ga->getQRCodeUrl($user['username'], $user['twofa_secret'], 'CampEvangelista');
echo "<h3>Scan this QR code in Google Authenticator:</h3>";
echo "<img src='https://chart.googleapis.com/chart?chs=200x200&chld=M|0&cht=qr&chl=" . urlencode($qr_url) . "' alt='QR Code'>";

echo "<p><a href='admin/setup-2fa.php' target='_blank'>Go to 2FA Setup Page</a></p>";