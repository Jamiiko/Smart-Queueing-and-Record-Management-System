<?php
// admin/login-monitor.php
// Camp Evangelista Station Hospital

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/SessionManager.php';

session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: ../index.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

// ============================================
// LOGIN HISTORY
// ============================================

$query = "SELECT h.*, u.full_name
          FROM login_history h
          LEFT JOIN users u ON h.user_id = u.id
          ORDER BY h.attempt_time DESC
          LIMIT 100";

$history = $db->query($query)->fetchAll(PDO::FETCH_ASSOC);

// ============================================
// LOCKED ACCOUNTS
// ============================================

$locked = "SELECT username, full_name, login_attempts, locked_until
           FROM users
           WHERE locked_until > NOW()
           ORDER BY locked_until DESC";

$locked_accounts = $db->query($locked)->fetchAll(PDO::FETCH_ASSOC);

// ============================================
// STATISTICS
// ============================================

$total_attempts = count($history);
$successful_logins = 0;
$failed_logins = 0;
$unique_users = [];

foreach ($history as $log) {

    if ($log['success']) {
        $successful_logins++;
    } else {
        $failed_logins++;
    }

    if (!in_array($log['username'], $unique_users)) {
        $unique_users[] = $log['username'];
    }
}

$success_rate = $total_attempts > 0
    ? round(($successful_logins / $total_attempts) * 100)
    : 0;

// ============================================
// TODAY ATTEMPTS
// ============================================

$today = date('Y-m-d');
$today_attempts = 0;

foreach ($history as $log) {
    if (date('Y-m-d', strtotime($log['attempt_time'])) == $today) {
        $today_attempts++;
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>
        Login Monitor | 4ID Station Hospital | Camp Evangelista
    </title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=REM:ital,wght@0,100..900;1,100..900&family=Sour+Gummy:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
    <!-- ============================================
         BOOTSTRAP
    ============================================= -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous"> 

    <!-- ============================================
         GOOGLE FONTS
    ============================================= -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap"
          rel="stylesheet">

    <!-- ============================================
         FONT AWESOME
    ============================================= -->
    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">

    <style>

        :root {
            --soft-blue: #4170a5;
            --soft-blue-dark: #3A7BC8;
            --soft-blue-light: #E7F3FB;
            --teal: #009688;
            --teal-dark: #00796B;
            --soft-green: #A4D1B1;
            --warm-yellow: #FFB84D;
            --light-coral: #FF6F61;
            --white: #FFFFFF;
            --light-gray: #F2F2F2;
            --light-beige: #F4F1EC;
            --pale-blue: #E7F3FB;
            --dark-gray: #212121;
            --charcoal: #333333;
            --border-light: #E5E9F0;
            --shadow-sm: 0 2px 8px rgba(0, 0, 0, 0.04);
            --shadow-md: 0 4px 16px rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 8px 24px rgba(0, 0, 0, 0.08);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--light-gray);
            color: var(--charcoal);
        }

        /* ============================================
           SIDEBAR
        ============================================= */

        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: 280px;
            height: 100vh;
            background: white;
            border-right: 1px solid var(--border-light);
            box-shadow: var(--shadow-md);
            overflow-y: auto;
            z-index: 1000;
        }

        .sidebar-logo {
            padding: 28px 24px;
            border-bottom: 1px solid var(--border-light);
        }

        .sidebar-logo h2 {
            color: var(--soft-blue);
            font-size: 1.1rem;
            font-weight: 700;
        }

        .sidebar-logo p {
            font-size: 0.75rem;
            opacity: 0.7;
        }

        .nav-menu {
            list-style: none;
            padding: 16px;
        }

        .nav-item {
            margin-bottom: 6px;
        }

        .nav-link-custom {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            border-radius: 12px;
            color: var(--charcoal);
            text-decoration: none;
            transition: 0.2s ease;
            font-weight: 500;
        }

        .nav-link-custom:hover {
            background: var(--pale-blue);
            color: var(--soft-blue);
        }

        .nav-link-custom.active {
            background: var(--soft-blue);
            color: white;
        }

        .nav-link-custom i {
            width: 20px;
        }

        /* ============================================
           MAIN CONTENT
        ============================================= */

        .main-content {
            margin-left: 280px;
            padding: 30px;
        }

        .page-title h1 {
            font-size: 1.8rem;
            font-weight: 700;
        }

        .page-title p {
            opacity: 0.7;
        }

        /* ============================================
           STAT CARDS
        ============================================= */

        .stat-card {
            background: white;
            border-radius: 20px;
            padding: 20px;
            border: 1px solid var(--border-light);
            box-shadow: var(--shadow-sm);
            transition: 0.2s ease;
            height: 100%;
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-md);
        }

        .stat-icon {
            width: 52px;
            height: 52px;
            background: var(--pale-blue);
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--soft-blue);
            font-size: 1.3rem;
            margin-bottom: 14px;
        }

        .stat-value {
            font-size: 1.8rem;
            font-weight: 700;
        }

        .stat-label {
            font-size: 0.75rem;
            text-transform: uppercase;
            opacity: 0.7;
        }

        /* ============================================
           PANELS
        ============================================= */

        .panel {
            background: white;
            border-radius: 20px;
            border: 1px solid var(--border-light);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
        }

        .panel-header {
            padding: 18px 24px;
            border-bottom: 1px solid var(--border-light);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .panel-header h3 {
            margin: 0;
            font-size: 1rem;
            font-weight: 600;
        }

        .panel-body {
            padding: 0;
        }

        /* ============================================
           TABLE
        ============================================= */

        .table td,
        .table th {
            vertical-align: middle;
        }

        .ip-address {
            background: var(--light-gray);
            padding: 4px 8px;
            border-radius: 8px;
            font-family: monospace;
            font-size: 0.8rem;
        }

        /* ============================================
           RESPONSIVE
        ============================================= */

        @media (max-width: 768px) {

            .sidebar {
                display: none;
            }

            .main-content {
                margin-left: 0;
                padding: 20px;
            }
        }

    </style>

</head>

<body>

<!-- ============================================
     SIDEBAR
============================================= -->

<aside class="sidebar">

    <div class="sidebar-logo">
        <h2>4ID Station Hospital</h2>
        <p>Camp Evangelista</p>
    </div>

    <ul class="nav-menu">

        <li class="nav-item">
            <a href="dashboard.php" class="nav-link-custom">
                <i class="fas fa-tachometer-alt"></i>
                Dashboard
            </a>
        </li>

        <li class="nav-item">
            <a href="patients.php" class="nav-link-custom">
                <i class="fas fa-users"></i>
                Patients
            </a>
        </li>

        <li class="nav-item">
            <a href="reports.php" class="nav-link-custom">
                <i class="fas fa-chart-bar"></i>
                Reports
            </a>
        </li>

        <li class="nav-item">
            <a href="users.php" class="nav-link-custom">
                <i class="fas fa-users-cog"></i>
                Users
            </a>
        </li>

        <li class="nav-item">
            <a href="login-monitor.php"
               class="nav-link-custom active">
                <i class="fas fa-history"></i>
                Login Monitor
            </a>
        </li>

        <li class="nav-item mt-4">
            <a href="../logout.php"
               class="nav-link-custom text-danger">
                <i class="fas fa-sign-out-alt"></i>
                Logout
            </a>
        </li>

    </ul>

</aside>

<!-- ============================================
     MAIN CONTENT
============================================= -->

<main class="main-content">

    <!-- TOP BAR -->

    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">

        <div class="page-title">
            <h1>Login Security Monitor</h1>
            <p>Monitor login attempts and security events</p>
        </div>

        <div class="text-end">
            <div id="currentDate"></div>
            <div id="currentTime"
                 class="fw-bold text-primary"></div>
        </div>

    </div>

    <!-- ============================================
         STATS
    ============================================= -->

    <div class="row g-3 mb-4">

        <div class="col-xl col-md-4 col-sm-6">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-chart-line"></i>
                </div>

                <div class="stat-value">
                    <?php echo number_format($total_attempts); ?>
                </div>

                <div class="stat-label">
                    Total Attempts
                </div>
            </div>
        </div>

        <div class="col-xl col-md-4 col-sm-6">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-check-circle"></i>
                </div>

                <div class="stat-value">
                    <?php echo number_format($successful_logins); ?>
                </div>

                <div class="stat-label">
                    Successful
                </div>
            </div>
        </div>

        <div class="col-xl col-md-4 col-sm-6">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-times-circle"></i>
                </div>

                <div class="stat-value">
                    <?php echo number_format($failed_logins); ?>
                </div>

                <div class="stat-label">
                    Failed
                </div>
            </div>
        </div>

        <div class="col-xl col-md-4 col-sm-6">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-percent"></i>
                </div>

                <div class="stat-value">
                    <?php echo $success_rate; ?>%
                </div>

                <div class="stat-label">
                    Success Rate
                </div>
            </div>
        </div>

        <div class="col-xl col-md-4 col-sm-6">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-calendar-day"></i>
                </div>

                <div class="stat-value">
                    <?php echo number_format($today_attempts); ?>
                </div>

                <div class="stat-label">
                    Today's Attempts
                </div>
            </div>
        </div>

    </div>

    <!-- ============================================
         LOCKED ALERT
    ============================================= -->

    <?php if (!empty($locked_accounts)): ?>

        <div class="alert alert-danger rounded-4 shadow-sm mb-4">

            <div class="d-flex align-items-center gap-3">

                <i class="fas fa-lock fa-2x"></i>

                <div>
                    <strong>
                        Locked Accounts Detected
                    </strong>

                    <div>
                        <?php echo count($locked_accounts); ?>
                        account(s) are currently locked.
                    </div>
                </div>

            </div>

        </div>

    <?php endif; ?>

    <!-- ============================================
         LOGIN HISTORY
    ============================================= -->

    <div class="panel">

        <div class="panel-header">
            <h3>
                <i class="fas fa-history text-primary"></i>
                Recent Login Activity
            </h3>

            <span class="text-muted small">
                Last 100 attempts
            </span>
        </div>

        <div class="table-responsive">

            <table class="table table-hover align-middle mb-0">

                <thead class="table-light">

                    <tr>
                        <th>Time</th>
                        <th>Username</th>
                        <th>Full Name</th>
                        <th>IP Address</th>
                        <th>Status</th>
                        <th>Reason</th>
                    </tr>

                </thead>

                <tbody>

                <?php foreach ($history as $log): ?>

                    <tr>

                        <td>
                            <?php echo date(
                                'M d, Y h:i:s A',
                                strtotime($log['attempt_time'])
                            ); ?>
                        </td>

                        <td>
                            <strong>
                                <?php echo htmlspecialchars($log['username']); ?>
                            </strong>
                        </td>

                        <td>
                            <?php echo htmlspecialchars($log['full_name'] ?? '—'); ?>
                        </td>

                        <td>
                            <span class="ip-address">
                                <?php echo htmlspecialchars($log['ip_address']); ?>
                            </span>
                        </td>

                        <td>

                            <?php if ($log['success']): ?>

                                <span class="badge bg-success">
                                    Success
                                </span>

                            <?php else: ?>

                                <span class="badge bg-danger">
                                    Failed
                                </span>

                            <?php endif; ?>

                        </td>

                        <td>

                            <?php

                            $reason = $log['failure_reason'] ?? '';

                            if ($reason == 'Invalid password') {

                                echo '<span class="text-warning">
                                      <i class="fas fa-key"></i>
                                      Invalid password
                                      </span>';

                            } elseif ($reason == 'User not found') {

                                echo '<span class="text-secondary">
                                      <i class="fas fa-user-slash"></i>
                                      User not found
                                      </span>';

                            } elseif ($reason == 'Invalid 2FA code') {

                                echo '<span class="text-danger">
                                      <i class="fas fa-qrcode"></i>
                                      Invalid 2FA code
                                      </span>';

                            } elseif ($reason == 'Account locked') {

                                echo '<span class="text-danger">
                                      <i class="fas fa-lock"></i>
                                      Account locked
                                      </span>';

                            } elseif ($reason == 'CAPTCHA failed') {

                                echo '<span class="text-warning">
                                      <i class="fas fa-robot"></i>
                                      CAPTCHA failed
                                      </span>';

                            } else {

                                echo '—';
                            }

                            ?>

                        </td>

                    </tr>

                <?php endforeach; ?>

                </tbody>

            </table>

        </div>

    </div>

</main>

<!-- ============================================
     SESSION WARNING MODAL
============================================= -->

<div class="modal fade"
     id="sessionModal"
     tabindex="-1">

    <div class="modal-dialog modal-dialog-centered">

        <div class="modal-content rounded-4">

            <div class="modal-body text-center p-4">

                <i class="fas fa-hourglass-half
                          text-warning
                          fs-1
                          mb-3"></i>

                <h4>
                    Session About to Expire
                </h4>

                <p>
                    You will be logged out due to inactivity.
                </p>

                <p id="countdownText"
                   class="fs-3 fw-bold">
                    2:00
                </p>

                <button class="btn btn-success rounded-pill px-4"
                        onclick="keepSessionAlive()">

                    Stay Logged In

                </button>

            </div>

        </div>

    </div>

</div>

<!-- ============================================
     BOOTSTRAP JS
============================================= -->

<script src="../assets/bootstrap/js/bootstrap.bundle.min.js"></script>

<script>

    // ============================================
    // DATE TIME
    // ============================================

    function updateDateTime() {

        const now = new Date();

        const options = {
            weekday: 'long',
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        };

        document.getElementById('currentDate')
            .textContent =
            now.toLocaleDateString('en-US', options);

        document.getElementById('currentTime')
            .textContent =
            now.toLocaleTimeString('en-US', {
                hour: '2-digit',
                minute: '2-digit'
            });
    }

    updateDateTime();
    setInterval(updateDateTime, 1000);

    // ============================================
    // AUTO LOGOUT
    // ============================================

    const INACTIVITY_TIMEOUT = 30 * 60 * 1000;

    let inactivityTimer;
    let warningTimer;
    let warningShown = false;

    const sessionModal =
        new bootstrap.Modal(
            document.getElementById('sessionModal')
        );

    function sendHeartbeat() {

        fetch('heartbeat.php', {
            method: 'POST',
            credentials: 'same-origin'
        }).catch(err => console.log(err));
    }

    function resetInactivityTimer() {

        clearTimeout(inactivityTimer);
        clearTimeout(warningTimer);

        hideWarningModal();

        inactivityTimer =
            setTimeout(logoutUser,
                INACTIVITY_TIMEOUT);

        warningTimer =
            setTimeout(showWarningModal,
                INACTIVITY_TIMEOUT - (2 * 60 * 1000));

        sendHeartbeat();
    }

    function logoutUser() {

        window.location.href = '../logout.php';
    }

    function showWarningModal() {

        if (warningShown) return;

        warningShown = true;

        sessionModal.show();

        let secondsLeft = 120;

        const countdown =
            document.getElementById('countdownText');

        const interval = setInterval(function() {

            secondsLeft--;

            const minutes =
                Math.floor(secondsLeft / 60);

            const seconds =
                secondsLeft % 60;

            countdown.textContent =
                `${minutes}:${seconds.toString().padStart(2, '0')}`;

            if (secondsLeft <= 0) {
                clearInterval(interval);
            }

        }, 1000);
    }

    function hideWarningModal() {

        warningShown = false;
        sessionModal.hide();
    }

    function keepSessionAlive() {

        sendHeartbeat();

        hideWarningModal();

        resetInactivityTimer();
    }

    // ============================================
    // USER ACTIVITY TRACKING
    // ============================================

    const events = [
        'mousedown',
        'mousemove',
        'keypress',
        'scroll',
        'touchstart',
        'click'
    ];

    events.forEach(function(event) {

        document.addEventListener(
            event,
            resetInactivityTimer,
            false
        );

    });

    resetInactivityTimer();

    // ============================================
    // HEARTBEAT EVERY 5 MINUTES
    // ============================================

    setInterval(function() {

        if (!warningShown) {
            sendHeartbeat();
        }

    }, 5 * 60 * 1000);

</script>

</body>
</html>