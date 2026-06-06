<?php
session_start();
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/GoogleAuthenticator.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();
$ga = new GoogleAuthenticator();

$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

// Get user
$query = "SELECT * FROM users WHERE id = ?";
$stmt = $db->prepare($query);
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Generate new secret if not exists
if (empty($user['twofa_secret'])) {
    $secret = $ga->createSecret();
    $update = "UPDATE users SET twofa_secret = ? WHERE id = ?";
    $update_stmt = $db->prepare($update);
    $update_stmt->execute([$secret, $user_id]);
} else {
    $secret = $user['twofa_secret'];
}

// Handle enable/disable
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['enable'])) {
        $code = $_POST['code'] ?? '';
        if ($ga->verifyCode($secret, $code, 2)) {
            $update = "UPDATE users SET twofa_enabled = 1 WHERE id = ?";
            $update_stmt = $db->prepare($update);
            $update_stmt->execute([$user_id]);
            $message = '2FA enabled successfully!';
            
            // Refresh user data
            $stmt->execute([$user_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
        } else {
            $error = 'Invalid verification code';
        }
    } elseif (isset($_POST['disable'])) {
        $update = "UPDATE users SET twofa_enabled = 0 WHERE id = ?";
        $update_stmt = $db->prepare($update);
        $update_stmt->execute([$user_id]);
        $message = '2FA disabled';
        
        // Refresh user data
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

$qr_url = $ga->getQRCodeUrl($user['username'], $secret, 'CampEvangelista Hospital');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>2FA Setup - Camp Evangelista</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">Two-Factor Authentication Setup</h4>
                    </div>
                    <div class="card-body">
                        <?php if ($message): ?>
                            <div class="alert alert-success"><?php echo $message; ?></div>
                        <?php endif; ?>
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>

                        <?php if ($user['twofa_enabled']): ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle"></i> 2FA is currently ENABLED
                            </div>
                            <form method="POST">
                                <button type="submit" name="disable" class="btn btn-danger w-100">
                                    <i class="fas fa-ban"></i> Disable 2FA
                                </button>
                            </form>
                        <?php else: ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i> Scan the QR code below with Google Authenticator app
                            </div>
                            
                            <div class="text-center mb-4">
                                <img src="https://chart.googleapis.com/chart?chs=200x200&chld=M|0&cht=qr&chl=<?php echo urlencode($qr_url); ?>" 
                                     alt="QR Code" class="img-fluid">
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Secret Key (manual entry)</label>
                                <input type="text" class="form-control" value="<?php echo $secret; ?>" readonly>
                            </div>
                            
                            <form method="POST">
                                <div class="mb-3">
                                    <label class="form-label">Verify Code</label>
                                    <input type="text" name="code" class="form-control" 
                                           placeholder="Enter 6-digit code" maxlength="6" required>
                                </div>
                                <button type="submit" name="enable" class="btn btn-primary w-100">
                                    <i class="fas fa-check"></i> Enable 2FA
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>