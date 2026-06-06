<?php
// staff/profile.php - User Profile Management
// Camp Evangelista Station Hospital

require_once dirname(__DIR__) . '/config/database.php';

session_start();

// ============================================
// AUTHENTICATION CHECK
// ============================================

if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit();
}

// Allowed roles for staff pages
$allowed_staff_roles = ['admin', 'doctor', 'nurse', 'technician', 'staff'];

if (!in_array($_SESSION['role'], $allowed_staff_roles)) {
    header('Location: ../unauthorized.php');
    exit();
}

// ============================================
// DATABASE CONNECTION
// ============================================
$database = new Database();
$db = $database->getConnection();

// ============================================
// SESSION TIMEOUT CHECK (After $db exists)
// ============================================
require_once dirname(__DIR__) . '/includes/SessionManager.php';
$sessionManager = new SessionManager($db);
if (!$sessionManager->checkTimeout()) {
    exit(); // Already redirected to login
}
$sessionManager->logActivity('Viewed profile page');

$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

// ============================================
// HANDLE PROFILE UPDATE
// ============================================

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // Update profile (email only for now)
    if (isset($_POST['update_profile'])) {
        $email = trim($_POST['email'] ?? '');
        
        $stmt = $db->prepare("UPDATE users SET email = ? WHERE id = ?");
        if ($stmt->execute([$email, $user_id])) {
            $message = "Profile updated successfully!";
        } else {
            $error = "Failed to update profile.";
        }
    }
    
    // Change password
    if (isset($_POST['update_password'])) {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        // Get current user's password hash
        $stmt = $db->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Verify current password
        if (password_verify($current_password, $user['password'])) {
            // Check if new password meets requirements
            if (strlen($new_password) < 6) {
                $error = "New password must be at least 6 characters long.";
            } elseif ($new_password !== $confirm_password) {
                $error = "New passwords do not match.";
            } else {
                // Update password
                $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
                $update = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
                if ($update->execute([$new_hash, $user_id])) {
                    $message = "Password changed successfully!";
                } else {
                    $error = "Failed to change password.";
                }
            }
        } else {
            $error = "Current password is incorrect.";
        }
    }
}

// ============================================
// GET USER INFORMATION
// ============================================

$query = "SELECT u.*, c.name as clinic_name 
          FROM users u
          LEFT JOIN clinics c ON u.clinic_id = c.id
          WHERE u.id = :user_id";

$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    die("User not found.");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile | Camp Evangelista Hospital</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    
    <style>
        :root {
            --soft-blue: #4A90E2;
            --soft-blue-dark: #3A7BC8;
            --soft-blue-light: #E7F3FB;
            --teal: #009688;
            --teal-dark: #00796B;
            --soft-green: #A4D1B1;
            --warm-yellow: #FFB84D;
            --light-coral: #FF6F61;
            --white: #FFFFFF;
            --light-gray: #F2F2F2;
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
            background-color: var(--light-gray);
            color: var(--charcoal);
            line-height: 1.5;
        }

        /* Sidebar Navigation */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: 280px;
            height: 100vh;
            background: var(--white);
            box-shadow: var(--shadow-md);
            z-index: 1000;
            overflow-y: auto;
            border-right: 1px solid var(--border-light);
        }

        .sidebar-logo {
            padding: 28px 24px;
            border-bottom: 1px solid var(--border-light);
            margin-bottom: 24px;
        }

        .sidebar-logo h2 {
            color: var(--soft-blue);
            font-size: 1.1rem;
            font-weight: 700;
            letter-spacing: -0.3px;
            margin-bottom: 4px;
        }

        .sidebar-logo p {
            color: var(--charcoal);
            font-size: 0.7rem;
            opacity: 0.7;
        }

        .nav-menu {
            list-style: none;
            padding: 0 16px;
        }

        .nav-item {
            margin-bottom: 4px;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            border-radius: 12px;
            color: var(--charcoal);
            text-decoration: none;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .nav-link i {
            width: 22px;
            color: var(--soft-blue);
            font-size: 1.1rem;
        }

        .nav-link:hover {
            background: var(--soft-blue-light);
            color: var(--soft-blue);
        }

        .nav-link.active {
            background: var(--soft-blue);
            color: white;
        }

        .nav-link.active i {
            color: white;
        }

        /* Main Content */
        .main-content {
            margin-left: 280px;
            padding: 28px 36px;
            min-height: 100vh;
        }

        /* Top Bar */
        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 32px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--border-light);
        }

        .page-title h1 {
            color: var(--dark-gray);
            font-size: 1.75rem;
            font-weight: 600;
            letter-spacing: -0.02em;
            margin-bottom: 4px;
        }

        .page-title p {
            color: var(--charcoal);
            font-size: 0.85rem;
            opacity: 0.7;
        }

        .date-time {
            text-align: right;
            font-size: 0.85rem;
        }

        .date {
            color: var(--charcoal);
            font-weight: 500;
        }

        .time {
            color: var(--soft-blue);
            font-weight: 600;
        }

        /* Profile Card */
        .profile-card {
            max-width: 800px;
            margin: 0 auto;
        }

        .card {
            background: var(--white);
            border-radius: 24px;
            border: 1px solid var(--border-light);
            overflow: hidden;
            box-shadow: var(--shadow-md);
            margin-bottom: 24px;
        }

        .card-header {
            padding: 20px 28px;
            border-bottom: 1px solid var(--border-light);
            background: var(--white);
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .card-header h2 {
            color: var(--dark-gray);
            font-size: 1.2rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .card-header h2 i {
            color: var(--soft-blue);
        }

        .card-body {
            padding: 28px;
        }

        /* Avatar */
        .avatar-section {
            text-align: center;
            margin-bottom: 24px;
        }

        .avatar {
            width: 100px;
            height: 100px;
            background: var(--soft-blue);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 16px;
        }

        .avatar i {
            font-size: 50px;
            color: white;
        }

        .role-badge {
            display: inline-block;
            padding: 6px 16px;
            border-radius: 30px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .role-admin { background: var(--light-coral); color: white; }
        .role-doctor { background: var(--soft-blue); color: white; }
        .role-nurse { background: var(--soft-green); color: var(--dark-gray); }
        .role-technician { background: var(--warm-yellow); color: var(--dark-gray); }
        .role-staff { background: var(--soft-blue-light); color: var(--soft-blue); }

        /* Info Rows */
        .info-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 14px 0;
            border-bottom: 1px solid var(--border-light);
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .info-label {
            font-weight: 600;
            color: var(--charcoal);
            font-size: 0.85rem;
        }

        .info-value {
            color: var(--dark-gray);
            font-size: 0.9rem;
        }

        /* Form Styles */
        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--charcoal);
            font-size: 0.8rem;
        }

        .form-group input {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid var(--border-light);
            border-radius: 12px;
            font-family: inherit;
            font-size: 0.9rem;
            transition: all 0.2s;
        }

        .form-group input:focus {
            outline: none;
            border-color: var(--soft-blue);
            box-shadow: 0 0 0 3px rgba(74, 144, 226, 0.1);
        }

        .btn-primary {
            background: var(--teal);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 12px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
            width: 100%;
            justify-content: center;
        }

        .btn-primary:hover {
            background: var(--teal-dark);
            transform: translateY(-1px);
        }

        /* Alerts */
        .alert {
            padding: 14px 20px;
            border-radius: 16px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .alert-success {
            background: var(--soft-green);
            color: var(--dark-gray);
            border-left: 3px solid var(--teal);
        }

        .alert-danger {
            background: #FEF2F0;
            color: var(--light-coral);
            border-left: 3px solid var(--light-coral);
        }

        hr {
            margin: 20px 0;
            border-color: var(--border-light);
        }

        /* Back Link */
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--soft-blue);
            text-decoration: none;
            margin-top: 16px;
            font-size: 0.85rem;
        }

        .back-link:hover {
            text-decoration: underline;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s;
            }
            .main-content {
                margin-left: 0;
                padding: 20px;
            }
            .info-row {
                flex-direction: column;
                align-items: flex-start;
                gap: 8px;
            }
            .top-bar {
                flex-direction: column;
                align-items: flex-start;
                gap: 16px;
            }
        }
    </style>
</head>
<body>

<!-- Sidebar Navigation -->
<aside class="sidebar">
    <div class="sidebar-logo">
        <h2>4ID Station Hospital</h2>
        <p>Camp Evangelista</p>
    </div>
    <ul class="nav-menu">
        <li class="nav-item">
            <a href="clinic-dashboard.php?clinic_id=<?php echo $_SESSION['clinic_id'] ?? 1; ?>" class="nav-link">
                <i class="fas fa-tachometer-alt"></i>
                <span>Dashboard</span>
            </a>
        </li>
        <li class="nav-item">
            <a href="registration.php" class="nav-link">
                <i class="fas fa-user-plus"></i>
                <span>Register Patient</span>
            </a>
        </li>
        <li class="nav-item">
            <a href="patient-queue.php" class="nav-link">
                <i class="fas fa-list"></i>
                <span>All Clinics</span>
            </a>
        </li>
        <li class="nav-item">
            <a href="../patient-portal/track-queue.php" target="_blank" class="nav-link">
                <i class="fas fa-search"></i>
                <span>Patient Portal</span>
            </a>
        </li>
        <li class="nav-item" style="margin-top: auto; border-top: 1px solid var(--border-light); padding-top: 16px;">
            <div style="padding: 12px 16px; background: var(--soft-blue-light); border-radius: 12px; margin-bottom: 8px;">
                <div style="font-size: 0.7rem; color: var(--charcoal);">Logged in as</div>
                <div style="font-weight: 600; color: var(--dark-gray);"><?php echo $_SESSION['full_name'] ?? $_SESSION['username']; ?></div>
                <div style="font-size: 0.7rem; color: var(--soft-blue);">
                    <i class="fas fa-tag"></i> Role: <?php echo ucfirst($_SESSION['role']); ?>
                </div>
            </div>
        </li>
        <li class="nav-item">
            <a href="profile.php" class="nav-link active">
                <i class="fas fa-user-circle"></i>
                <span>My Profile</span>
            </a>
        </li>
        <li class="nav-item">
            <a href="../logout.php" class="nav-link" style="color: var(--light-coral);" onclick="return confirm('Are you sure you want to logout?')">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </li>
    </ul>
</aside>

<!-- Main Content -->
<main class="main-content">
    <!-- Top Bar -->
    <div class="top-bar">
        <div class="page-title">
            <h1>My Profile</h1>
            <p>View and update your account information</p>
        </div>
        <div class="date-time">
            <div class="date" id="currentDate"></div>
            <div class="time" id="currentTime"></div>
        </div>
    </div>

    <div class="profile-card">
        <!-- Alerts -->
        <?php if ($message): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo $message; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <!-- Profile Information Card -->
        <div class="card">
            <div class="card-header">
                <h2><i class="fas fa-user-circle"></i> Account Information</h2>
            </div>
            <div class="card-body">
                <div class="avatar-section">
                    <div class="avatar">
                        <i class="fas fa-user-md"></i>
                    </div>
                    <h3><?php echo htmlspecialchars($user['full_name']); ?></h3>
                    <span class="role-badge role-<?php echo $user['role']; ?>">
                        <?php echo ucfirst($user['role']); ?>
                    </span>
                </div>

                <div class="info-row">
                    <span class="info-label"><i class="fas fa-user"></i> Username</span>
                    <span class="info-value"><?php echo htmlspecialchars($user['username']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label"><i class="fas fa-envelope"></i> Email</span>
                    <span class="info-value"><?php echo htmlspecialchars($user['email'] ?? 'Not set'); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label"><i class="fas fa-clinic-medical"></i> Clinic Assignment</span>
                    <span class="info-value"><?php echo htmlspecialchars($user['clinic_name'] ?? 'All Clinics'); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label"><i class="fas fa-calendar-alt"></i> Account Created</span>
                    <span class="info-value"><?php echo date('F d, Y', strtotime($user['created_at'])); ?></span>
                </div>
            </div>
        </div>

        <!-- Update Profile Card -->
        <div class="card">
            <div class="card-header">
                <h2><i class="fas fa-edit"></i> Update Profile</h2>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="form-group">
                        <label><i class="fas fa-envelope"></i> Email Address</label>
                        <input type="email" name="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" placeholder="your@email.com">
                        <small style="color: var(--charcoal); opacity: 0.6;">Leave empty if you don't want to set an email</small>
                    </div>
                    <button type="submit" name="update_profile" class="btn-primary">
                        <i class="fas fa-save"></i> Update Profile
                    </button>
                </form>
            </div>
        </div>

        <!-- Change Password Card -->
        <div class="card">
            <div class="card-header">
                <h2><i class="fas fa-key"></i> Change Password</h2>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="form-group">
                        <label><i class="fas fa-lock"></i> Current Password</label>
                        <input type="password" name="current_password" placeholder="Enter current password" required>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-lock"></i> New Password</label>
                        <input type="password" name="new_password" placeholder="Min. 6 characters" required minlength="6">
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-lock"></i> Confirm New Password</label>
                        <input type="password" name="confirm_password" placeholder="Re-enter new password" required>
                    </div>
                    <button type="submit" name="update_password" class="btn-primary">
                        <i class="fas fa-key"></i> Change Password
                    </button>
                </form>
            </div>
        </div>

        <a href="clinic-dashboard.php?clinic_id=<?php echo $_SESSION['clinic_id'] ?? 1; ?>" class="back-link">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>
    </div>
</main>

<script>
    // Date and Time Display
    function updateDateTime() {
        const now = new Date();
        const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
        document.getElementById('currentDate').textContent = now.toLocaleDateString('en-US', options);
        document.getElementById('currentTime').textContent = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
    }
    updateDateTime();
    setInterval(updateDateTime, 1000);

    // Auto-hide alerts after 5 seconds
    setTimeout(() => {
        document.querySelectorAll('.alert').forEach(alert => {
            alert.style.transition = 'opacity 0.5s';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 500);
        });
    }, 5000);
</script>
</body>
</html>