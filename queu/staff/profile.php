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

// Dynamic UI Badge Mapping for Staff Roles
$role_classes = [
    'admin' => 'bg-rose-50 text-rose-700 dark:bg-rose-950/40 dark:text-rose-400 border border-rose-200 dark:border-rose-900/40',
    'doctor' => 'bg-sky-50 text-sky-700 dark:bg-sky-950/40 dark:text-sky-400 border border-sky-200 dark:border-sky-900/40',
    'nurse' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-900/40',
    'technician' => 'bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-400 border border-amber-200 dark:border-amber-900/40',
    'staff' => 'bg-slate-50 text-slate-700 dark:bg-slate-800 dark:text-slate-300 border border-slate-200 dark:border-slate-700/50'
];
$current_role = strtolower($user['role'] ?? 'staff');
$role_badge_style = $role_classes[$current_role] ?? $role_classes['staff'];
?>

<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile | Camp Evangelista Hospital</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['"Plus Jakarta Sans"', 'sans-serif'],
                        mono: ['"JetBrains Mono"', 'monospace']
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-slate-50 dark:bg-slate-900 text-slate-800 dark:text-slate-100 font-sans min-h-screen antialiased transition-colors duration-300">

<div class="flex">
    
    <aside class="fixed top-0 left-0 z-40 w-72 h-screen bg-white dark:bg-slate-800 border-r border-slate-200/80 dark:border-slate-700/60 hidden md:flex flex-col justify-between p-6 transition-all duration-300">
        <div class="space-y-7">
            <div class="flex items-center gap-3 px-2">
                <div class="w-10 h-10 bg-sky-600 rounded-xl flex items-center justify-center text-white shadow-md shadow-sky-500/20">
                    <i class="fas fa-hospital-user text-lg"></i>
                </div>
                <div>
                    <h2 class="text-sm font-bold text-slate-900 dark:text-white tracking-tight leading-none mb-1">4ID Station Hospital</h2>
                    <p class="text-[11px] font-medium text-slate-400 dark:text-slate-500">Camp Evangelista</p>
                </div>
            </div>

            <nav class="space-y-1.5">
                <a href="clinic-dashboard.php?clinic_id=<?php echo $_SESSION['clinic_id'] ?? 1; ?>" class="flex items-center gap-3.5 px-4 py-3 rounded-xl font-medium text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-700/50 hover:text-slate-900 dark:hover:text-white transition-all text-sm group">
                    <i class="fas fa-tachometer-alt text-slate-400 group-hover:text-sky-500 transition-colors"></i>
                    <span>Dashboard</span>
                </a>
                
                <a href="registration.php" class="flex items-center gap-3.5 px-4 py-3 rounded-xl font-medium text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-700/50 hover:text-slate-900 dark:hover:text-white transition-all text-sm group">
                    <i class="fas fa-user-plus text-slate-400 group-hover:text-sky-500 transition-colors"></i>
                    <span>Register Patient</span>
                </a>
                
                <a href="patient-queue.php" class="flex items-center gap-3.5 px-4 py-3 rounded-xl font-medium text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-700/50 hover:text-slate-900 dark:hover:text-white transition-all text-sm group">
                    <i class="fas fa-list text-slate-400 group-hover:text-sky-500 transition-colors"></i>
                    <span>All Clinics</span>
                </a>
                
                <a href="../patient-portal/track-queue.php" target="_blank" class="flex items-center gap-3.5 px-4 py-3 rounded-xl font-medium text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-700/50 hover:text-slate-900 dark:hover:text-white transition-all text-sm group">
                    <i class="fas fa-search text-slate-400 group-hover:text-sky-500 transition-colors"></i>
                    <span>Patient Portal</span>
                </a>
                
                <div class="h-px bg-slate-100 dark:bg-slate-700/50 my-4"></div>
                
                <a href="profile.php" class="flex items-center gap-3.5 px-4 py-3 rounded-xl font-semibold bg-sky-50 dark:bg-sky-950/40 text-sky-600 dark:text-sky-400 transition-all text-sm">
                    <i class="fas fa-user-circle"></i>
                    <span>My Profile</span>
                </a>
            </nav>
        </div>

        <div class="space-y-3">
            <div class="p-4 bg-slate-50 dark:bg-slate-900/60 rounded-2xl border border-slate-100 dark:border-slate-800">
                <p class="text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider mb-1">Logged in as</p>
                <h4 class="text-xs font-bold text-slate-800 dark:text-slate-200 truncate mb-1.5"><?php echo htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username']); ?></h4>
                <div class="inline-flex items-center gap-1.5 text-[11px] font-semibold px-2 py-0.5 rounded-md bg-sky-50 text-sky-700 dark:bg-sky-950/30 dark:text-sky-400">
                    <i class="fas fa-shield-alt text-[10px]"></i>
                    <span><?php echo ucfirst($_SESSION['role']); ?></span>
                </div>
            </div>

            <a href="../logout.php" onclick="return confirm('Are you sure you want to logout?')" class="flex items-center justify-center gap-2 w-full px-4 py-2.5 rounded-xl text-xs font-semibold text-rose-600 dark:text-rose-400 bg-rose-50 hover:bg-rose-100 dark:bg-rose-950/20 dark:hover:bg-rose-950/40 border border-rose-100 dark:border-rose-900/30 transition-all">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout Session</span>
            </a>
        </div>
    </aside>

    <main class="flex-1 md:ml-72 min-h-screen p-4 md:p-8 lg:p-10">
        
        <header class="flex flex-col sm:flex-row justify-between sm:items-center gap-4 pb-6 mb-8 border-b border-slate-200/70 dark:border-slate-800">
            <div>
                <h1 class="text-2xl font-bold text-slate-950 dark:text-white tracking-tight">Account Profile</h1>
                <p class="text-xs font-medium text-slate-400 dark:text-slate-500 mt-0.5">Manage credentials, view records and security context attributes</p>
            </div>
            
            <div class="flex items-center gap-4 self-end sm:self-auto">
                <button onclick="document.documentElement.classList.toggle('dark')" class="w-9 h-9 flex items-center justify-center rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700/60 text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white shadow-sm transition-all">
                    <i class="fas fa-moon dark:hidden"></i>
                    <i class="fas fa-sun hidden dark:block"></i>
                </button>
                
                <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700/60 px-4 py-2 rounded-xl shadow-sm text-right min-w-[140px]">
                    <div id="currentDate" class="text-[11px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wide"></div>
                    <div id="currentTime" class="text-sm font-bold text-sky-600 dark:text-sky-400 font-mono tracking-tight mt-0.5"></div>
                </div>
            </div>
        </header>

        <div class="max-w-3xl mx-auto">
            <?php if ($message): ?>
                <div class="alert-banner flex items-center gap-3 p-4 mb-6 rounded-xl bg-emerald-50 dark:bg-emerald-950/20 border border-emerald-200/60 dark:border-emerald-900/30 text-emerald-800 dark:text-emerald-400 text-sm font-medium transition-all duration-300 shadow-sm">
                    <i class="fas fa-check-circle text-emerald-500 text-base"></i>
                    <span><?php echo $message; ?></span>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert-banner flex items-center gap-3 p-4 mb-6 rounded-xl bg-rose-50 dark:bg-rose-950/20 border border-rose-200/60 dark:border-rose-900/30 text-rose-800 dark:text-rose-400 text-sm font-medium transition-all duration-300 shadow-sm">
                    <i class="fas fa-exclamation-circle text-rose-500 text-base"></i>
                    <span><?php echo $error; ?></span>
                </div>
            <?php endif; ?>

            <div class="space-y-6">
                
                <div class="bg-white dark:bg-slate-800 border border-slate-200/60 dark:border-slate-700/70 rounded-2xl shadow-sm overflow-hidden transition-all duration-300">
                    <div class="px-6 py-4 bg-slate-50/60 dark:bg-slate-800/40 border-b border-slate-100 dark:border-slate-700/60 flex items-center gap-2.5">
                        <i class="fas fa-id-card text-sky-500 text-sm"></i>
                        <h3 class="text-sm font-bold text-slate-900 dark:text-white">Account Information</h3>
                    </div>
                    
                    <div class="p-6 md:p-8">
                        <div class="text-center pb-6 border-b border-slate-100 dark:border-slate-700/40 mb-6">
                            <div class="relative w-20 h-20 mx-auto mb-3 flex items-center justify-center rounded-full bg-gradient-to-tr from-sky-500 to-indigo-600 text-white shadow-md">
                                <i class="fas fa-user-md text-3xl"></i>
                                <span class="absolute bottom-0 right-0 w-5 h-5 bg-emerald-500 border-2 border-white dark:border-slate-800 rounded-full shadow-sm"></span>
                            </div>
                            <h2 class="text-lg font-bold text-slate-900 dark:text-white tracking-tight"><?php echo htmlspecialchars($user['full_name']); ?></h2>
                            <span class="inline-block mt-2 text-xs font-bold px-3 py-1 rounded-full tracking-wide <?php echo $role_badge_style; ?>">
                                <?php echo ucfirst($user['role']); ?>
                            </span>
                        </div>

                        <div class="divide-y divide-slate-100 dark:divide-slate-700/40 text-sm">
                            <div class="flex justify-between items-center py-3.5">
                                <span class="font-medium text-slate-400 dark:text-slate-500 flex items-center gap-2.5"><i class="fas fa-user w-4 text-slate-300 dark:text-slate-600"></i> Username</span>
                                <span class="font-semibold text-slate-800 dark:text-slate-200 font-mono text-xs bg-slate-50 dark:bg-slate-900 px-2 py-1 rounded border border-slate-200/40 dark:border-slate-800"><?php echo htmlspecialchars($user['username']); ?></span>
                            </div>
                            <div class="flex justify-between items-center py-3.5">
                                <span class="font-medium text-slate-400 dark:text-slate-500 flex items-center gap-2.5"><i class="fas fa-envelope w-4 text-slate-300 dark:text-slate-600"></i> Email Reference</span>
                                <span class="font-medium text-slate-800 dark:text-slate-200"><?php echo htmlspecialchars($user['email'] ?? 'Not provisioned'); ?></span>
                            </div>
                            <div class="flex justify-between items-center py-3.5">
                                <span class="font-medium text-slate-400 dark:text-slate-500 flex items-center gap-2.5"><i class="fas fa-clinic-medical w-4 text-slate-300 dark:text-slate-600"></i> Assignment</span>
                                <span class="font-semibold text-slate-800 dark:text-slate-200"><?php echo htmlspecialchars($user['clinic_name'] ?? 'General Scope / All Clinics'); ?></span>
                            </div>
                            <div class="flex justify-between items-center py-3.5">
                                <span class="font-medium text-slate-400 dark:text-slate-500 flex items-center gap-2.5"><i class="fas fa-calendar-alt w-4 text-slate-300 dark:text-slate-600"></i> Date Created</span>
                                <span class="font-medium text-slate-800 dark:text-slate-200"><?php echo date('F d, Y', strtotime($user['created_at'])); ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-slate-800 border border-slate-200/60 dark:border-slate-700/70 rounded-2xl shadow-sm overflow-hidden transition-all duration-300">
                    <div class="px-6 py-4 bg-slate-50/60 dark:bg-slate-800/40 border-b border-slate-100 dark:border-slate-700/60 flex items-center gap-2.5">
                        <i class="fas fa-user-edit text-sky-500 text-sm"></i>
                        <h3 class="text-sm font-bold text-slate-900 dark:text-white">Update Contact Profile</h3>
                    </div>
                    
                    <form method="POST" class="p-6 space-y-4">
                        <div class="space-y-1.5">
                            <label class="text-xs font-bold text-slate-500 dark:text-slate-400 tracking-wide uppercase">Email Address</label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-slate-400 dark:text-slate-600 pointer-events-none text-xs">
                                    <i class="fas fa-envelope"></i>
                                </span>
                                <input type="email" name="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" placeholder="example@hospital.gov.ph" class="w-full pl-10 pr-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-900/50 text-slate-900 dark:text-white placeholder-slate-400 text-sm focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 dark:focus:border-sky-500 transition-all">
                            </div>
                            <p class="text-[11px] text-slate-400 dark:text-slate-500 pl-1">Leave blank if notification relays are not required for this station account.</p>
                        </div>
                        
                        <div class="pt-2 flex justify-end">
                            <button type="submit" name="update_profile" class="inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl bg-sky-600 hover:bg-sky-700 active:scale-[0.98] text-white text-xs font-bold shadow-md shadow-sky-600/10 transition-all cursor-pointer">
                                <i class="fas fa-save text-xs"></i>
                                <span>Save Changes</span>
                            </button>
                        </div>
                    </form>
                </div>

                <div class="bg-white dark:bg-slate-800 border border-slate-200/60 dark:border-slate-700/70 rounded-2xl shadow-sm overflow-hidden transition-all duration-300">
                    <div class="px-6 py-4 bg-slate-50/60 dark:bg-slate-800/40 border-b border-slate-100 dark:border-slate-700/60 flex items-center gap-2.5">
                        <i class="fas fa-key text-sky-500 text-sm"></i>
                        <h3 class="text-sm font-bold text-slate-900 dark:text-white">Security Authentication Password Reset</h3>
                    </div>
                    
                    <form method="POST" class="p-6 space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div class="space-y-1.5">
                                <label class="text-xs font-bold text-slate-500 dark:text-slate-400 tracking-wide uppercase">Current Password</label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-slate-400 dark:text-slate-600 pointer-events-none text-xs">
                                        <i class="fas fa-lock"></i>
                                    </span>
                                    <input type="password" name="current_password" placeholder="••••••••" required class="w-full pl-10 pr-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-900/50 text-slate-900 dark:text-white placeholder-slate-300 dark:placeholder-slate-700 text-sm focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 dark:focus:border-sky-500 transition-all">
                                </div>
                            </div>
                            
                            <div class="space-y-1.5">
                                <label class="text-xs font-bold text-slate-500 dark:text-slate-400 tracking-wide uppercase">New Password</label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-slate-400 dark:text-slate-600 pointer-events-none text-xs">
                                        <i class="fas fa-shield-alt"></i>
                                    </span>
                                    <input type="password" name="new_password" placeholder="Min. 6 chars" required minlength="6" class="w-full pl-10 pr-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-900/50 text-slate-900 dark:text-white placeholder-slate-300 dark:placeholder-slate-700 text-sm focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 dark:focus:border-sky-500 transition-all">
                                </div>
                            </div>
                            
                            <div class="space-y-1.5">
                                <label class="text-xs font-bold text-slate-500 dark:text-slate-400 tracking-wide uppercase">Confirm New Password</label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-slate-400 dark:text-slate-600 pointer-events-none text-xs">
                                        <i class="fas fa-check-shield"></i>
                                    </span>
                                    <input type="password" name="confirm_password" placeholder="Re-enter new" required class="w-full pl-10 pr-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-900/50 text-slate-900 dark:text-white placeholder-slate-300 dark:placeholder-slate-700 text-sm focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 dark:focus:border-sky-500 transition-all">
                                </div>
                            </div>
                        </div>
                        
                        <div class="pt-2 flex justify-end">
                            <button type="submit" name="update_password" class="inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 active:scale-[0.98] text-white text-xs font-bold shadow-md shadow-emerald-600/10 transition-all cursor-pointer">
                                <i class="fas fa-key text-xs"></i>
                                <span>Commit Password Change</span>
                            </button>
                        </div>
                    </form>
                </div>

                <div class="pt-2 flex justify-start">
                    <a href="clinic-dashboard.php?clinic_id=<?php echo $_SESSION['clinic_id'] ?? 1; ?>" class="inline-flex items-center gap-2 text-xs font-bold text-slate-400 dark:text-slate-500 hover:text-sky-600 dark:hover:text-sky-400 transition-colors group">
                        <i class="fas fa-arrow-left group-hover:-translate-x-1 transition-transform"></i>
                        <span>Return back to Station Dashboard</span>
                    </a>
                </div>
                
            </div>
        </div>
    </main>
</div>

<script>
    function updateDateTime() {
        const now = new Date();
        const options = { weekday: 'short', year: 'numeric', month: 'short', day: 'numeric' };
        document.getElementById('currentDate').textContent = now.toLocaleDateString('en-US', options);
        document.getElementById('currentTime').textContent = now.toLocaleTimeString('en-US', { 
            hour: '2-digit', 
            minute: '2-digit', 
            second: '2-digit',
            hour12: false 
        });
    }
    updateDateTime();
    setInterval(updateDateTime, 1000);

    // Auto-dismiss transient validation states banner components gently after timeout
    setTimeout(() => {
        document.querySelectorAll('.alert-banner').forEach(banner => {
            banner.style.transition = 'all 0.5s cubic-bezier(0.4, 0, 0.2, 1)';
            banner.style.opacity = '0';
            banner.style.transform = 'translateY(-8px)';
            setTimeout(() => banner.remove(), 500);
        });
    }, 5000);
</script>
</body>
</html>