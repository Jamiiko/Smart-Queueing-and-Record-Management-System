<?php
session_start();
require_once 'config/database.php';
require_once 'includes/GoogleAuthenticator.php';

// Rate limiting configuration
$max_attempts = 5;
$lockout_time = 15;

// Get client IP
$ip_address = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

// If already logged in, redirect
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] == 'admin') {
        header('Location: admin/dashboard.php');
    } else {
        header('Location: staff/clinic-dashboard.php?clinic_id=' . $_SESSION['clinic_id']);
    }
    exit();
}

// Initialize session login attempts
if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = [
        'count' => 0,
        'first_attempt' => time(),
        'locked_until' => 0
    ];
}

// Check if session is locked
if ($_SESSION['login_attempts']['locked_until'] > time()) {
    $remaining_minutes = ceil(($_SESSION['login_attempts']['locked_until'] - time()) / 60);
    $error = "Too many failed attempts. Please try again in {$remaining_minutes} minutes.";
}

// Reset counter if time window passed (30 minutes)
if (time() - $_SESSION['login_attempts']['first_attempt'] > 1800) {
    $_SESSION['login_attempts'] = [
        'count' => 0,
        'first_attempt' => time(),
        'locked_until' => 0
    ];
}

// Function to log login attempts
function logLoginAttempt($db, $username, $success, $reason = null, $user_id = null) {
    global $ip_address, $user_agent;
    
    $query = "INSERT INTO login_history (user_id, username, ip_address, user_agent, success, failure_reason) 
              VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $db->prepare($query);
    $stmt->execute([$user_id, $username, $ip_address, $user_agent, $success, $reason]);
}

$error = '';
$show_2fa = false;
$temp_user = null;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    if ($_SESSION['login_attempts']['locked_until'] > time()) {
        $error = "Account temporarily locked. Please try again later.";
    }
    else {
        $database = new Database();
        $db = $database->getConnection();
        
        // Check if this is 2FA verification step
        if (isset($_POST['verify_2fa'])) {
            $user_id = $_SESSION['2fa_user_id'] ?? 0;
            $code = trim($_POST['2fa_code'] ?? '');
            
            if (empty($user_id) || empty($code)) {
                $error = 'Invalid verification request';
            } else {
                $query = "SELECT * FROM users WHERE id = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$user_id]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$user) {
                    $error = 'User not found';
                } 
                elseif (empty($user['twofa_secret'])) {
                    $error = '2FA not set up for this user';
                }
                else {
                    $ga = new GoogleAuthenticator();
                    
                    if ($ga->verifyCode($user['twofa_secret'], $code, 2)) {
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['username'] = $user['username'];
                        $_SESSION['full_name'] = $user['full_name'];
                        $_SESSION['role'] = $user['role'];
                        $_SESSION['clinic_id'] = $user['clinic_id'];
                        $_SESSION['clinic_name'] = $user['clinic_name'];
                        $_SESSION['last_activity'] = time(); // Session timeout tracking
                        
                        $update = "UPDATE users SET last_login_time = NOW(), last_login_ip = ? WHERE id = ?";
                        $update_stmt = $db->prepare($update);
                        $update_stmt->execute([$ip_address, $user['id']]);
                        
                        unset($_SESSION['2fa_user_id']);
                        logLoginAttempt($db, $user['username'], 1, null, $user['id']);
                        
                        if ($user['role'] == 'admin') {
                            header('Location: admin/dashboard.php');
                        } else {
                            header('Location: staff/clinic-dashboard.php?clinic_id=' . $user['clinic_id']);
                        }
                        exit();
                    } else {
                        $error = 'Invalid 2FA code. Please try again.';
                        logLoginAttempt($db, $user['username'], 0, 'Invalid 2FA code', $user['id']);
                        $_SESSION['login_attempts']['count']++;
                    }
                }
            }
        }
        else {
            // Initial login step
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
            
            if (empty($username) || empty($password)) {
                $error = 'Username and password are required';
            } else {
                usleep(rand(100000, 300000));
                
                $query = "SELECT u.*, c.name as clinic_name 
                          FROM users u
                          LEFT JOIN clinics c ON u.clinic_id = c.id
                          WHERE u.username = :username";
                
                $stmt = $db->prepare($query);
                $stmt->bindParam(':username', $username);
                $stmt->execute();
                
                if ($stmt->rowCount() > 0) {
                    $user = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    // Check if account is active (if column exists)
                    if (isset($user['is_active']) && $user['is_active'] == 0) {
                        $error = 'Your account has been deactivated. Please contact administrator.';
                        logLoginAttempt($db, $username, 0, 'Account deactivated', $user['id']);
                    }
                    elseif (isset($user['locked_until']) && $user['locked_until'] && strtotime($user['locked_until']) > time()) {
                        $lockout_minutes = ceil((strtotime($user['locked_until']) - time()) / 60);
                        $error = "Account is locked. Try again in {$lockout_minutes} minutes.";
                        logLoginAttempt($db, $username, 0, 'Account locked', $user['id']);
                    }
                    elseif (password_verify($password, $user['password'])) {
                        if ($user['twofa_enabled'] == 1) {
                            $_SESSION['2fa_user_id'] = $user['id'];
                            $show_2fa = true;
                        } else {
                            $_SESSION['user_id'] = $user['id'];
                            $_SESSION['username'] = $user['username'];
                            $_SESSION['full_name'] = $user['full_name'];
                            $_SESSION['role'] = $user['role'];
                            $_SESSION['clinic_id'] = $user['clinic_id'];
                            $_SESSION['clinic_name'] = $user['clinic_name'];
                            $_SESSION['last_activity'] = time(); // Session timeout tracking
                            
                            // Reset login attempts (if column exists)
                            if (isset($user['login_attempts'])) {
                                $reset = "UPDATE users SET login_attempts = 0, locked_until = NULL WHERE id = ?";
                                $reset_stmt = $db->prepare($reset);
                                $reset_stmt->execute([$user['id']]);
                            }
                            
                            $update = "UPDATE users SET last_login_time = NOW(), last_login_ip = ? WHERE id = ?";
                            $update_stmt = $db->prepare($update);
                            $update_stmt->execute([$ip_address, $user['id']]);
                            
                            logLoginAttempt($db, $username, 1, null, $user['id']);
                            
                            $_SESSION['login_attempts'] = [
                                'count' => 0,
                                'first_attempt' => time(),
                                'locked_until' => 0
                            ];
                            
                            if ($user['role'] == 'admin') {
                                header('Location: admin/dashboard.php');
                            } else {
                                header('Location: staff/clinic-dashboard.php?clinic_id=' . $user['clinic_id']);
                            }
                            exit();
                        }
                    } else {
                        $error = 'Invalid username or password';
                        
                        // Update login attempts (if column exists)
                        if (isset($user['login_attempts'])) {
                            $new_attempts = $user['login_attempts'] + 1;
                            
                            if ($new_attempts >= $max_attempts) {
                                $lock_until = date('Y-m-d H:i:s', time() + ($lockout_time * 60));
                                $update = "UPDATE users SET login_attempts = ?, locked_until = ?, last_login_attempt = NOW() WHERE id = ?";
                                $update_stmt = $db->prepare($update);
                                $update_stmt->execute([$new_attempts, $lock_until, $user['id']]);
                                $_SESSION['login_attempts']['locked_until'] = time() + ($lockout_time * 60);
                                $error = "Too many failed attempts. Account locked for {$lockout_time} minutes.";
                            } else {
                                $update = "UPDATE users SET login_attempts = ?, last_login_attempt = NOW() WHERE id = ?";
                                $update_stmt = $db->prepare($update);
                                $update_stmt->execute([$new_attempts, $user['id']]);
                            }
                        }
                        
                        logLoginAttempt($db, $username, 0, 'Invalid password', $user['id']);
                        $_SESSION['login_attempts']['count']++;
                        
                        if ($_SESSION['login_attempts']['count'] >= $max_attempts) {
                            $_SESSION['login_attempts']['locked_until'] = time() + ($lockout_time * 60);
                            $error = "Too many failed attempts. Please try again in {$lockout_time} minutes.";
                        }
                    }
                } else {
                    $error = 'Invalid username or password';
                    logLoginAttempt($db, $username, 0, 'User not found');
                    $_SESSION['login_attempts']['count']++;
                    
                    if ($_SESSION['login_attempts']['count'] >= $max_attempts) {
                        $_SESSION['login_attempts']['locked_until'] = time() + ($lockout_time * 60);
                        $error = "Too many failed attempts. Please try again in {$lockout_time} minutes.";
                    }
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Secure Login | 4ID Station Hospital | Camp Evangelista</title>
    
    <link rel="stylesheet" href="css/command-dashboard.css">
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    
    <style>
        /* ==========================================================================
           CSS ISOLATION BOX: Prevents background/button pollution from global files
           ========================================================================== */
        html, body {
            margin: 0 !important;
            padding: 0 !important;
            box-sizing: border-box !important;
        }

        .secure-landing-env {
            background-color: #0A0F1E !important;
            color: #FFFFFF !important;
            min-height: 100vh !important;
            width: 100% !important;
            display: flex !important;
            font-family: 'Inter', sans-serif !important;
        }

        /* FLEX SPLIT SYSTEM LAYOUT CONTAINER */
        .secure-landing-env .login-wrapper {
            display: flex !important;
            width: 100% !important;
            min-height: 100vh !important;
        }

        /* LEFT BRAND PANEL CONTAINER */
        .secure-landing-env .brand-panel {
            flex: 1 !important;
            background: linear-gradient(135deg, #050b14 0%, #141B2B 100%) !important;
            display: flex !important;
            flex-direction: column !important;
            justify-content: center !important;
            align-items: center !important;
            padding: 48px !important;
            position: relative !important;
            overflow: hidden !important;
            border-right: 1px solid #2D3748 !important;
            
            /* Clean Subtle Entrance Animation Track */
            animation: panelFadeIn 0.85s cubic-bezier(0.25, 1, 0.5, 1) both;
        }

        .secure-landing-env .brand-panel::before {
            content: '' !important;
            position: absolute !important;
            width: 100% !important;
            height: 100% !important;
            background-image: radial-gradient(circle at 30% 30%, rgba(45, 212, 191, 0.05) 0%, transparent 60%) !important;
            pointer-events: none !important;
        }

        .secure-landing-env .brand-content {
            max-width: 440px !important;
            text-align: center !important;
            position: relative !important;
            z-index: 5 !important;
        }

        /* LOGO RING WRAPPER WITH BREATHING MICRO GLOW */
        .secure-landing-env .logo-container {
            width: 160px !important;
            height: 160px !important;
            background-color: rgba(255, 255, 255, 0.02) !important;
            border: 1px solid #2D3748 !important;
            border-radius: 50% !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            margin: 0 auto 32px !important;
            overflow: hidden !important;
            padding: 6px !important;
            
            /* High Tech Entry Sequence */
            animation: 
                logoReveal 1s cubic-bezier(0.25, 1, 0.5, 1) 0.15s both,
                logoBreathingGlow 4s ease-in-out infinite 1.2s;
        }

        .secure-landing-env .logo-img {
            width: 100% !important;
            height: 100% !important;
            object-fit: contain !important;
            border-radius: 50% !important;
        }

        .secure-landing-env .brand-content h1 {
            font-size: 32px !important;
            font-weight: 700 !important;
            letter-spacing: -0.5px !important;
            line-height: 1.2 !important;
            margin: 0 0 12px 0 !important;
            color: #FFFFFF !important;
        }

        .secure-landing-env .brand-content p {
            font-size: 15px !important;
            color: #94A3B8 !important;
            line-height: 1.6 !important;
            margin: 0 !important;
        }

        .secure-landing-env .security-badge {
            display: inline-flex !important;
            align-items: center !important;
            gap: 8px !important;
            background: rgba(45, 212, 191, 0.1) !important;
            border: 1px solid rgba(45, 212, 191, 0.2) !important;
            padding: 6px 16px !important;
            border-radius: 30px !important;
            margin-top: 32px !important;
            font-size: 11px !important;
            font-weight: 600 !important;
            color: #2DD4BF !important;
            letter-spacing: 0.5px !important;
        }

        /* RIGHT SECURITY GATE ENTRY PANEL */
        .secure-landing-env .form-panel {
            width: 100% !important;
            max-width: 640px !important;
            background-color: #141B2B !important;
            display: flex !important;
            flex-direction: column !important;
            justify-content: center !important;
            padding: 48px 80px !important;
            box-shadow: -10px 0 30px rgba(0,0,0,0.2) !important;
            border-left: 1px solid #2D3748 !important;
            overflow-y: auto !important;
            
            /* Entry Animation Integration */
            animation: panelFadeIn 0.85s cubic-bezier(0.25, 1, 0.5, 1) both;
        }

        .secure-landing-env .form-container {
            width: 100% !important;
            max-width: 440px !important;
            margin: 0 auto !important;
        }

        .secure-landing-env .form-header-mobile {
            display: none !important;
            text-align: center !important;
            margin-bottom: 32px !important;
        }

        .secure-landing-env .form-title {
            font-size: 24px !important;
            font-weight: 700 !important;
            color: #FFFFFF !important;
            margin: 0 0 6px 0 !important;
            letter-spacing: -0.3px !important;
        }

        .secure-landing-env .form-subtitle {
            font-size: 14px !important;
            color: #94A3B8 !important;
            margin: 0 0 32px 0 !important;
        }

        .secure-landing-env .form-group {
            margin-bottom: 20px !important;
        }

        .secure-landing-env .form-label {
            display: block !important;
            margin-bottom: 8px !important;
            font-size: 11px !important;
            font-weight: 600 !important;
            color: #94A3B8 !important;
            text-transform: uppercase !important;
            letter-spacing: 0.5px !important;
        }

        /* ENCAPSULATED SECURITY FIELD INPUT WRAPPERS */
        .secure-landing-env .input-wrapper {
            display: flex !important;
            align-items: center !important;
            border: 1.5px solid #2D3748 !important;
            border-radius: 8px !important;
            background-color: #0A0F1E !important;
            transition: border-color 0.2s ease, box-shadow 0.2s ease !important;
            width: 100% !important;
        }

        .secure-landing-env .input-wrapper:focus-within {
            border-color: #2DD4BF !important;
            box-shadow: 0 0 0 3px rgba(45, 212, 191, 0.15) !important;
        }

        .secure-landing-env .input-icon {
            padding: 0 16px !important;
            color: #64748B !important;
            display: flex !important;
            align-items: center !important;
        }

        .secure-landing-env .form-control {
            flex: 1 !important;
            padding: 14px 16px 14px 0 !important;
            border: none !important;
            background: transparent !important;
            font-size: 14px !important;
            color: #FFFFFF !important;
            outline: none !important;
            width: 100% !important;
        }

        .secure-landing-env .form-control::placeholder {
            color: #4A5568 !important;
        }

        .secure-landing-env .code-input {
            text-align: center !important;
            font-size: 24px !important;
            letter-spacing: 6px !important;
            font-weight: 700 !important;
            padding-right: 16px !important;
        }

        /* STRICT ISOLATED SYSTEM ACTION BUTTONS */
        .secure-landing-env .btn-submit-override {
            width: 100% !important;
            padding: 14px !important;
            border: none !important;
            border-radius: 8px !important;
            font-size: 14px !important;
            font-weight: 600 !important;
            cursor: pointer !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            gap: 8px !important;
            transition: background 0.2s, box-shadow 0.2s !important;
            margin-top: 8px !important;
            font-family: inherit !important;
        }

        .secure-landing-env .btn-primary-override {
            background-color: #2DD4BF !important;
            color: #0A0F1E !important;
        }

        .secure-landing-env .btn-primary-override:hover {
            background-color: #14b8a6 !important;
            box-shadow: 0 0 14px rgba(45, 212, 191, 0.2) !important;
        }

        .secure-landing-env .btn-outline-override {
            background-color: transparent !important;
            border: 1px solid #2D3748 !important;
            color: #FFFFFF !important;
            text-decoration: none !important;
        }

        .secure-landing-env .btn-outline-override:hover {
            background-color: #1E2639 !important;
        }

        /* NOTIFICATIONS & ALERTS */
        .secure-landing-env .alert {
            padding: 16px !important;
            border-radius: 8px !important;
            margin-bottom: 24px !important;
            display: flex !important;
            align-items: flex-start !important;
            gap: 12px !important;
            font-size: 14px !important;
            line-height: 1.4 !important;
        }

        .secure-landing-env .alert-danger {
            background-color: rgba(239, 68, 68, 0.1) !important;
            border: 1px solid #EF4444 !important;
            color: #EF4444 !important;
        }

        .secure-landing-env .attempt-counter {
            background-color: rgba(245, 158, 11, 0.1) !important;
            border: 1px solid #F59E0B !important;
            color: #F59E0B !important;
            padding: 10px 16px !important;
            border-radius: 6px !important;
            font-size: 12px !important;
            font-weight: 500 !important;
            margin-bottom: 20px !important;
            display: flex !important;
            align-items: center !important;
            gap: 8px !important;
        }

        /* SANDBOX CREDENTIALS SECTION */
        .secure-landing-env .credentials-section {
            margin-top: 32px !important;
            border: 1px solid #2D3748 !important;
            border-radius: 8px !important;
            background-color: #0A0F1E !important;
            padding: 16px !important;
        }

        .secure-landing-env .credentials-header {
            display: flex !important;
            align-items: center !important;
            gap: 8px !important;
            font-size: 11px !important;
            font-weight: 700 !important;
            color: #94A3B8 !important;
            text-transform: uppercase !important;
            letter-spacing: 0.5px !important;
            margin-bottom: 12px !important;
        }

        .secure-landing-env .credentials-header i:first-child {
            color: #2DD4BF !important;
        }

        .secure-landing-env .credentials-grid {
            display: grid !important;
            grid-template-columns: repeat(2, 1fr) !important;
            gap: 8px !important;
            max-height: 150px !important;
            overflow-y: auto !important;
        }

        .secure-landing-env .credentials-grid::-webkit-scrollbar { width: 4px; }
        .secure-landing-env .credentials-grid::-webkit-scrollbar-thumb {
            background: #2D3748 !important;
            border-radius: 2px !important;
        }

        .secure-landing-env .credential-item {
            background-color: #141B2B !important;
            border: 1px solid #2D3748 !important;
            border-radius: 4px !important;
            padding: 8px 12px !important;
        }

        .secure-landing-env .credential-role {
            font-size: 11px !important;
            font-weight: 600 !important;
            color: #FFFFFF !important;
            margin-bottom: 2px !important;
        }

        .secure-landing-env .credential-details {
            font-size: 11px !important;
            font-family: monospace !important;
            color: #2DD4BF !important;
        }

        /* EXTERNAL PATIENT PORTAL CTA FOOTER WRAPPER */
        .secure-landing-env .portal-link-wrapper {
            margin-top: 32px !important;
            padding-top: 24px !important;
            border-top: 1px solid #2D3748 !important;
        }

        .secure-landing-env .btn-portal {
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            padding: 14px !important;
            background-color: rgba(45, 212, 191, 0.02) !important;
            border: 1px dashed #2DD4BF !important;
            border-radius: 8px !important;
            color: #2DD4BF !important;
            font-size: 14px !important;
            font-weight: 600 !important;
            text-decoration: none !important;
            transition: all 0.2s ease !important;
        }

        .secure-landing-env .btn-portal:hover {
            background-color: #2DD4BF !important;
            color: #0A0F1E !important;
        }

        .secure-landing-env .btn-portal span {
            flex: 1 !important;
            text-align: center !important;
        }

        .secure-landing-env .portal-note {
            text-align: center !important;
            font-size: 11px !important;
            color: #64748B !important;
            margin-top: 8px !important;
        }

        .secure-landing-env .page-footer {
            margin-top: auto !important;
            padding-top: 48px !important;
            text-align: center !important;
            font-size: 12px !important;
            color: #64748B !important;
        }

        /* ==========================================================================
           HARDWARE-ACCELERATED ANIMATION TRANSLATIONS
           ========================================================================== */
        @keyframes panelFadeIn {
            from { opacity: 0; transform: scale(0.99); }
            to { opacity: 1; transform: scale(1); }
        }

        @keyframes logoReveal {
            from { opacity: 0; transform: translateY(15px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes logoBreathingGlow {
            0%, 100% { box-shadow: 0 0 0 0 rgba(45, 212, 191, 0.06); border-color: #2D3748; }
            50% { box-shadow: 0 0 14px 3px rgba(45, 212, 191, 0.15); border-color: rgba(45, 212, 191, 0.35); }
        }

        /* ==========================================================================
           RESPONSIVE LAYOUT BOUNDARIES
           ========================================================================== */
        @media (max-width: 1024px) {
            .secure-landing-env .form-panel { padding: 48px 40px !important; }
        }

        @media (max-width: 840px) {
            .secure-landing-env .login-wrapper { flex-direction: column !important; }
            .secure-landing-env .brand-panel { display: none !important; }
            .secure-landing-env .form-panel {
                max-width: 100% !important;
                min-height: 100vh !important;
                border-left: none !important;
                padding: 32px 20px !important;
            }
            .secure-landing-env .form-header-mobile { display: block !important; }
            .secure-landing-env .mobile-logo-wrapper {
                width: 90px !important;
                height: 90px !important;
                background-color: #0A0F1E !important;
                border: 1px solid #2D3748 !important;
                border-radius: 50% !important;
                display: flex !important;
                align-items: center !important;
                justify-content: center !important;
                margin: 0 auto 16px !important;
                padding: 6px !important;
            }
            .secure-landing-env .mobile-logo-wrapper img {
                width: 100% !important;
                height: 100% !important;
                object-fit: contain !important;
                border-radius: 50% !important;
            }
            .secure-landing-env .mobile-title { font-size: 20px !important; font-weight: 700 !important; color: #FFFFFF !important; }
            .secure-landing-env .mobile-subtitle { font-size: 13px !important; color: #94A3B8 !important; margin-top: 4px !important; }
        }
    </style>
</head>
<body>

    <div class="secure-landing-env">
        <div class="login-wrapper">
            
            <div class="brand-panel">
                <div class="brand-content">
                    <div class="logo-container">
                        <img src="assets/images/logo.png" alt="4ID Station Hospital Logo" class="logo-img">
                    </div>
                    <h1>4ID Station Hospital</h1>
                    <p>Camp Evangelista • Outpatient Record Management & Smart Queueing System</p>
                    <div class="security-badge">
                        <i class="fas fa-shield-alt"></i>
                        <span>2FA SECURED PORTAL</span>
                    </div>
                </div>
            </div>

            <div class="form-panel">
                <div class="form-container">
                    
                    <div class="form-header-mobile">
                        <div class="mobile-logo-wrapper">
                            <img src="images/331025910_562010602523931_5033391126347262805_n.jpg" alt="4ID Station Hospital Logo">
                        </div>
                        <div class="mobile-title">4ID Station Hospital</div>
                        <div class="mobile-subtitle">Camp Evangelista Outpatient System</div>
                    </div>

                    <div class="form-title">
                        <?php echo $show_2fa ? 'Security Verification' : 'Welcome Back'; ?>
                    </div>
                    <div class="form-subtitle">
                        <?php echo $show_2fa ? 'Please enter your authentication parameter token' : 'Sign in to access your dashboard system terminal'; ?>
                    </div>

                    <?php if ($_SESSION['login_attempts']['count'] > 0 && !$show_2fa): ?>
                        <div class="attempt-counter">
                            <i class="fas fa-exclamation-triangle"></i>
                            <span>Failed authentication attempts: <strong><?php echo $_SESSION['login_attempts']['count']; ?>/<?php echo $max_attempts; ?></strong></span>
                        </div>
                    <?php endif; ?>

                    <?php if ($error): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle" style="margin-top: 2px;"></i>
                            <div><?php echo $error; ?></div>
                        </div>
                    <?php endif; ?>

                    <?php if ($show_2fa): ?>
                        <form method="POST">
                            <div class="form-group">
                                <label class="form-label">Two-Factor Authentication Code</label>
                                <div class="input-wrapper">
                                    <span class="input-icon">
                                        <i class="fas fa-qrcode"></i>
                                    </span>
                                    <input type="text" 
                                           name="2fa_code" 
                                           class="form-control code-input" 
                                           placeholder="000000"
                                           maxlength="6"
                                           pattern="[0-9]{6}"
                                           autocomplete="off"
                                           required>
                                </div>
                                <span style="font-size: 11px; color: #64748B; display: block; margin-top: 8px; line-height: 1.4;">
                                    Enter the 6-digit dynamic token generated inside your registered Google Authenticator app structure.
                                </span>
                            </div>
                            <button type="submit" name="verify_2fa" class="btn-submit-override btn-primary-override">
                                <i class="fas fa-shield-alt"></i>
                                Verify & Complete Login
                            </button>
                            <a href="index.php" class="btn-submit-override btn-outline-override" style="gap: 8px;">
                                <i class="fas fa-arrow-left"></i>
                                Return to Login Step
                            </a>
                        </form>
                    <?php else: ?>
                        <form method="POST">
                            <div class="form-group">
                                <label class="form-label">Username ID</label>
                                <div class="input-wrapper">
                                    <span class="input-icon">
                                        <i class="fas fa-user"></i>
                                    </span>
                                    <input type="text" 
                                           name="username" 
                                           class="form-control" 
                                           placeholder="Enter institutional user configuration code" 
                                           value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                                           required>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Security Access Password</label>
                                <div class="input-wrapper">
                                    <span class="input-icon">
                                        <i class="fas fa-lock"></i>
                                    </span>
                                    <input type="password" 
                                           name="password" 
                                           class="form-control" 
                                           placeholder="••••••••••••" 
                                           required>
                                </div>
                            </div>

                            <button type="submit" class="btn-submit-override btn-primary-override" id="loginButton">
                                <i class="fas fa-sign-in-alt"></i>
                                Authenticate Terminal
                            </button>
                        </form>
                    <?php endif; ?>

                    <div class="credentials-section">
                        <div class="credentials-header">
                            <i class="fas fa-id-card"></i>
                            <span>Institutional Access Registry</span>
                            <i class="fas fa-chevron-down" style="margin-left: auto; font-size: 10px;"></i>
                        </div>
                        <div class="credentials-grid">
                            <div class="credential-item">
                                <div class="credential-role"><i class="fas fa-crown" style="color: #F59E0B; font-size: 10px;"></i> Admin</div>
                                <div class="credential-details">admin / admin123</div>
                            </div>
                            <div class="credential-item">
                                <div class="credential-role">Registration</div>
                                <div class="credential-details">reg_staff / clinic123</div>
                            </div>
                            <div class="credential-item">
                                <div class="credential-role">Vital Signs</div>
                                <div class="credential-details">vital_staff / clinic123</div>
                            </div>
                            <div class="credential-item">
                                <div class="credential-role">Laboratory</div>
                                <div class="credential-details">lab_tech / clinic123</div>
                            </div>
                            <div class="credential-item">
                                <div class="credential-role">X-Ray</div>
                                <div class="credential-details">xray_tech / clinic123</div>
                            </div>
                            <div class="credential-item">
                                <div class="credential-role">ECG</div>
                                <div class="credential-details">ecg_tech / clinic123</div>
                            </div>
                            <div class="credential-item">
                                <div class="credential-role">Dental</div>
                                <div class="credential-details">dentist / clinic123</div>
                            </div>
                            <div class="credential-item">
                                <div class="credential-role">OPTO</div>
                                <div class="credential-details">optometrist / clinic123</div>
                            </div>
                            <div class="credential-item">
                                <div class="credential-role">General Doctor</div>
                                <div class="credential-details">dr_general / clinic123</div>
                            </div>
                        </div>
                    </div>

                    <div class="portal-link-wrapper">
                        <a href="patient-portal\track-queue.php" class="btn-portal">
                            <i class="fas fa-users"></i>
                            <span>Launch External Patient Portal</span>
                            <i class="fas fa-arrow-right"></i>
                        </a>
                        <div class="portal-note">
                            <i class="fas fa-info-circle"></i> Live tracking metrics monitor — Requires zero profile credentials
                        </div>
                    </div>

                </div>

                <footer class="page-footer">
                    4th Infantry Division • Camp Evangelista Station Hospital Secure System Terminal
                </footer>
            </div>
        </div>
    </div>

    <script>
        // Safely sweep alert nodes off DOM container tree after presentation frame
        setTimeout(() => {
            const alerts = document.querySelectorAll('.secure-landing-env .alert');
            alerts.forEach(alert => {
                alert.style.transition = 'opacity 0.4s ease, transform 0.4s ease';
                alert.style.opacity = '0';
                alert.style.transform = 'translateY(-4px)';
                setTimeout(() => alert.remove(), 400);
            });
        }, 5000);

        // Autofocus token inputs dynamically on multi step transition splits
        <?php if ($show_2fa): ?>
        document.querySelector('input[name="2fa_code"]')?.focus();
        <?php endif; ?>
        
        // Prevent duplicate network payloads when processing operations
        const loginForm = document.querySelector('form');
        const loginButton = document.getElementById('loginButton');
        
        if (loginForm && loginButton) {
            loginForm.addEventListener('submit', function(e) {
                if (!loginForm.checkValidity()) return;
                setTimeout(() => {
                    loginButton.disabled = true;
                    loginButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Running Security Checks...';
                }, 100);
            });
        }
        
        const verifyButton = document.querySelector('button[name="verify_2fa"]');
        if (verifyButton) {
            verifyButton.addEventListener('click', function(e) {
                const form = this.closest('form');
                if (form && form.checkValidity()) {
                    setTimeout(() => {
                        this.disabled = true;
                        this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Authorizing Token...';
                    }, 100);
                }
            });
        }
    </script>
</body>
</html>