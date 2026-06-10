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
// SESSION TIMEOUT CHECK
// ============================================
require_once dirname(__DIR__) . '/includes/SessionManager.php';
$sessionManager = new SessionManager($db);
if (!$sessionManager->checkTimeout()) {
    exit(); 
}
$sessionManager->logActivity('Viewed profile page');

$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

// ============================================
// HANDLE PROFILE UPDATE
// ============================================

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['update_profile'])) {
        $email = trim($_POST['email'] ?? '');
        $stmt = $db->prepare("UPDATE users SET email = ? WHERE id = ?");
        if ($stmt->execute([$email, $user_id])) {
            $message = "Profile updated successfully!";
        } else {
            $error = "Failed to update profile.";
        }
    }
    
    if (isset($_POST['update_password'])) {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        $stmt = $db->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (password_verify($current_password, $user['password'])) {
            if (strlen($new_password) < 6) {
                $error = "New password must be at least 6 characters long.";
            } elseif ($new_password !== $confirm_password) {
                $error = "New passwords do not match.";
            } else {
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
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile | Camp Evangelista Hospital</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['"Plus Jakarta Sans"', '-apple-system', 'sans-serif'],
                        mono: ['"JetBrains Mono"', 'monospace']
                    }
                }
            }
        }
    </script>
    <style>
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-5px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes modalFadeIn {
            from { opacity: 0; transform: scale(0.96) translateY(-4px); }
            to { opacity: 1; transform: scale(1) translateY(0); }
        }
    </style>
</head>
<body class="bg-slate-50 dark:bg-[#111827] text-slate-800 dark:text-slate-100 font-sans antialiased min-h-screen transition-colors duration-200">

    <aside id="sidebar" class="fixed top-0 left-0 h-screen bg-white dark:bg-[#1f2937] border-r border-slate-300/90 dark:border-slate-700/80 shadow-xl md:shadow-none z-[1000] flex flex-col justify-between overflow-x-hidden transition-all duration-300 ease-in-out group/sidebar -translate-x-full md:translate-x-0 w-[270px] md:w-[80px] md:hover:w-[270px]">
        
        <div>
            <div class="p-4 border-b border-slate-300/90 dark:border-slate-700/60 mb-6 flex flex-col items-center justify-center min-h-[150px]">
                
                <div class="hidden md:flex md:group-hover/sidebar:hidden flex-col items-center justify-center font-extrabold text-xl tracking-wider text-sky-600 dark:text-sky-400 leading-tight select-none">
                    <span>C</span>
                    <span>E</span>
                    <span>S</span>
                    <span>H</span>
                </div>

                <div class="flex md:hidden md:group-hover/sidebar:flex flex-col items-center animate-[fadeIn_0.2s_ease-in-out]">
                    <img src="../assets/images/logo.png" alt="Logo" class="max-w-[80px] h-auto rounded mb-3 opacity-90 transition-all duration-200 dark:brightness-110 dark:contrast-125 dark:drop-shadow-[0_0_8px_rgba(56,189,248,0.3)]" onerror="this.style.display='none'">
                    <h2 class="text-slate-800 dark:text-slate-100 text-sm font-extrabold tracking-tight text-center whitespace-nowrap">4ID Station Hospital</h2>
                    <p class="text-slate-400 dark:text-slate-400 text-[0.65rem] font-bold uppercase tracking-widest text-center whitespace-nowrap mt-0.5">Camp Evangelista</p>
                </div>
            </div>
            
            <nav class="px-3 md:group-hover/sidebar:px-4 transition-all duration-200">
                <ul class="list-none p-0 space-y-1">
                    <?php if ($_SESSION['role'] === 'admin'): ?>
                    <li>
                        <a href="../admin/dashboard.php" class="flex items-center rounded-xl font-medium transition-all duration-150 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700/50 p-3 justify-center md:justify-start gap-0 md:group-hover/sidebar:gap-3 border-l-4 border-transparent group/link">
                            <div class="w-6 h-6 flex items-center justify-center shrink-0"><i class="fas fa-tachometer-alt text-base text-slate-400 group-hover/link:text-sky-500 transition-colors"></i></div>
                            <span class="opacity-100 md:opacity-0 md:group-hover/sidebar:opacity-100 text-[0.85rem] tracking-wide whitespace-nowrap transition-opacity duration-200 origin-left">Dashboard</span>
                        </a>
                    </li>
                    <?php else: ?>
                    <li>
                        <a href="clinic-dashboard.php?clinic_id=<?php echo isset($_SESSION['clinic_id']) ? $_SESSION['clinic_id'] : 1; ?>" class="flex items-center rounded-xl font-medium transition-all duration-150 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700/50 p-3 justify-center md:justify-start gap-0 md:group-hover/sidebar:gap-3 border-l-4 border-transparent group/link">
                            <div class="w-6 h-6 flex items-center justify-center shrink-0"><i class="fas fa-desktop text-base text-slate-400 group-hover/link:text-sky-500 transition-colors"></i></div>
                            <span class="opacity-100 md:opacity-0 md:group-hover/sidebar:opacity-100 text-[0.85rem] tracking-wide whitespace-nowrap transition-opacity duration-200 origin-left">Dashboard</span>
                        </a>
                    </li>
                    <?php endif; ?>

                    <li>
                        <a href="registration.php" class="flex items-center rounded-xl font-medium transition-all duration-150 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700/50 p-3 justify-center md:justify-start gap-0 md:group-hover/sidebar:gap-3 border-l-4 border-transparent group/link">
                            <div class="w-6 h-6 flex items-center justify-center shrink-0"><i class="fas fa-user-plus text-base text-slate-400 group-hover/link:text-sky-500 transition-colors"></i></div>
                            <span class="opacity-100 md:opacity-0 md:group-hover/sidebar:opacity-100 text-[0.85rem] tracking-wide whitespace-nowrap transition-opacity duration-200 origin-left">Registration</span>
                        </a>
                    </li>

                    <li>
                        <a href="patient-queue.php" class="flex items-center rounded-xl font-medium transition-all duration-150 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700/50 p-3 justify-center md:justify-start gap-0 md:group-hover/sidebar:gap-3 border-l-4 border-transparent group/link">
                            <div class="w-6 h-6 flex items-center justify-center shrink-0"><i class="fas fa-list text-base text-slate-400 group-hover/link:text-sky-500 transition-colors"></i></div>
                            <span class="opacity-100 md:opacity-0 md:group-hover/sidebar:opacity-100 text-[0.85rem] tracking-wide whitespace-nowrap transition-opacity duration-200 origin-left">All Clinics Queue</span>
                        </a>
                    </li>

                    <li>
                        <a href="../patient-portal/track-queue.php" target="_blank" class="flex items-center rounded-xl font-medium transition-all duration-150 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700/50 p-3 justify-center md:justify-start gap-0 md:group-hover/sidebar:gap-3 border-l-4 border-transparent group/link">
                            <div class="w-6 h-6 flex items-center justify-center shrink-0"><i class="fas fa-search text-base text-slate-400 group-hover/link:text-sky-500 transition-colors"></i></div>
                            <span class="opacity-100 md:opacity-0 md:group-hover/sidebar:opacity-100 text-[0.85rem] tracking-wide whitespace-nowrap transition-opacity duration-200 origin-left">Patient Portal</span>
                        </a>
                    </li>

                    <li>
                        <a href="profile.php" class="flex items-center rounded-xl font-semibold transition-all duration-150 bg-sky-50 dark:bg-sky-500/10 text-sky-600 dark:text-sky-400 border-l-4 border-sky-500 p-3 justify-center md:justify-start gap-0 md:group-hover/sidebar:gap-3 mt-2">
                            <div class="w-6 h-6 flex items-center justify-center shrink-0"><i class="fas fa-user-circle text-base"></i></div>
                            <span class="opacity-100 md:opacity-0 md:group-hover/sidebar:opacity-100 text-[0.85rem] tracking-wide whitespace-nowrap transition-opacity duration-200 origin-left">My Profile</span>
                        </a>
                    </li>
                </ul>
            </nav>
        </div>

        <div class="p-3 md:p-2 md:group-hover/sidebar:p-4 border-t border-slate-300/90 dark:border-slate-700/80 shrink-0 transition-all duration-300">
            <div class="bg-slate-50 dark:bg-slate-800/60 rounded-xl p-3 md:p-0 md:group-hover/sidebar:p-3 border border-slate-200 dark:border-slate-700/50 md:border-transparent md:dark:border-transparent md:bg-transparent md:dark:bg-transparent md:group-hover/sidebar:bg-slate-50 md:group-hover/sidebar:dark:bg-slate-800/60 md:group-hover/sidebar:border-slate-200 md:group-hover/sidebar:dark:border-slate-700/50 flex items-center justify-center md:group-hover/sidebar:justify-start gap-3 md:gap-0 md:group-hover/sidebar:gap-3 transition-all duration-300 overflow-hidden">
                <div class="w-10 h-10 rounded-full bg-white dark:bg-slate-700 flex items-center justify-center text-sky-600 dark:text-sky-400 border border-slate-200 dark:border-slate-600 shrink-0 shadow-sm md:shadow-none md:group-hover/sidebar:shadow-sm">
                    <i class="fas fa-user-md"></i>
                </div>
                <div class="overflow-hidden max-w-full md:max-w-0 md:group-hover/sidebar:max-w-full opacity-100 md:opacity-0 md:group-hover/sidebar:opacity-100 transition-all duration-300 shrink-0 md:shrink md:group-hover/sidebar:shrink-0 min-w-0">
                    <p class="text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-0.5 whitespace-nowrap">Logged in as</p>
                    <p class="text-sm font-bold text-slate-900 dark:text-white truncate leading-tight whitespace-nowrap"><?php echo htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username']); ?></p>
                    <p class="text-[11px] text-sky-600 dark:text-sky-400 font-medium capitalize mt-0.5 whitespace-nowrap"><?php echo htmlspecialchars($_SESSION['role']); ?></p>
                </div>
            </div>
        </div>
    </aside>

    <main class="min-h-screen ml-0 md:ml-[80px] p-6 md:p-8 transition-all duration-300 max-w-[1600px] mx-auto">
        
        <header class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-8 pb-6 border-b border-slate-200 dark:border-slate-800">
            <div class="flex items-center gap-4">
                <button id="mobileMenuBtn" class="md:hidden w-11 h-11 flex items-center justify-center text-slate-600 dark:text-slate-300 bg-white dark:bg-[#1f2937] border border-slate-200 dark:border-slate-800 rounded-xl shadow-sm hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                    <i class="fas fa-bars text-lg"></i>
                </button>
                <div>
                    <h1 class="text-2xl md:text-3xl font-extrabold text-slate-900 dark:text-white tracking-tight">Account Profile</h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Manage credentials, view records and security context attributes</p>
                </div>
            </div>
            
            <div class="flex items-center gap-4">
                <div class="text-right hidden sm:block">
                    <div class="text-sm font-semibold text-slate-800 dark:text-slate-200" id="currentDate"></div>
                    <div class="text-xs font-mono font-bold text-sky-600 dark:text-sky-400 mt-0.5" id="currentTime"></div>
                </div>

                <button id="themeToggleBtn" class="w-11 h-11 flex items-center justify-center bg-white dark:bg-[#1f2937] border border-slate-200 dark:border-slate-800 rounded-xl text-slate-500 dark:text-amber-400 hover:border-sky-500 dark:hover:border-sky-400 transition-all shadow-sm">
                    <i id="themeToggleIcon" class="fas fa-moon text-lg"></i>
                </button>

                <div class="relative">
                    <button id="profileMenuBtn" class="w-11 h-11 bg-white dark:bg-[#1f2937] rounded-full flex items-center justify-center text-sky-600 dark:text-sky-400 border border-slate-200 dark:border-slate-800 shadow-sm hover:border-sky-500 dark:hover:border-sky-400 transition-all duration-150 focus:outline-none">
                        <i class="fas fa-user-circle text-2xl"></i>
                    </button>
                    
                    <div id="profileDropdown" class="hidden absolute right-0 mt-3 w-56 bg-white dark:bg-[#1f2937] border border-slate-200 dark:border-slate-800 rounded-2xl shadow-xl z-[1100] animate-[modalFadeIn_0.15s_ease-out]">
                        <div class="p-4 border-b border-slate-100 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/50 rounded-t-2xl">
                            <p class="text-sm font-bold text-slate-900 dark:text-white truncate"><?php echo htmlspecialchars(isset($_SESSION['full_name']) ? $_SESSION['full_name'] : $_SESSION['username']); ?></p>
                            <p class="text-xs font-medium text-slate-500 dark:text-slate-400 mt-0.5 capitalize"><?php echo htmlspecialchars($_SESSION['role']); ?></p>
                        </div>
                        <div class="p-2 flex flex-col gap-1">
                            <a href="../logout.php" onclick="return confirm('Confirm Logout?')" class="flex items-center gap-3 w-full text-left px-3 py-2.5 text-sm font-semibold text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-500/10 rounded-xl transition-colors">
                                <i class="fas fa-sign-out-alt"></i> Logout Session
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <div class="max-w-4xl mx-auto space-y-6">
            <?php if ($message): ?>
                <div class="alert p-4 bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-300 dark:border-emerald-500/20 text-emerald-600 dark:text-emerald-400 rounded-2xl flex items-center justify-between shadow-sm">
                    <div class="flex items-center"><i class="fas fa-check-circle mr-3 text-base"></i> <span class="text-xs font-bold uppercase tracking-wide"><?= htmlspecialchars($message) ?></span></div>
                    <button onclick="this.parentElement.remove()" class="text-emerald-600/50 hover:text-emerald-500 transition-colors"><i class="fas fa-times"></i></button>
                </div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert p-4 bg-rose-50 dark:bg-rose-500/10 border border-rose-300 dark:border-rose-500/20 text-rose-600 dark:text-rose-400 rounded-2xl flex items-center justify-between shadow-sm">
                    <div class="flex items-center"><i class="fas fa-exclamation-triangle mr-3 text-base"></i> <span class="text-xs font-bold uppercase tracking-wide"><?= htmlspecialchars($error) ?></span></div>
                    <button onclick="this.parentElement.remove()" class="text-rose-600/50 hover:text-rose-500 transition-colors"><i class="fas fa-times"></i></button>
                </div>
            <?php endif; ?>

            <div class="bg-white dark:bg-[#1f2937] border border-slate-200 dark:border-slate-800 rounded-3xl shadow-sm overflow-hidden transition-all duration-300">
                <div class="px-6 py-5 border-b border-slate-100 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-800/30 flex items-center gap-3">
                    <div class="w-8 h-8 rounded-lg bg-sky-500/10 flex items-center justify-center text-sky-500"><i class="fas fa-id-card"></i></div>
                    <h3 class="text-sm font-extrabold text-slate-900 dark:text-white uppercase tracking-wider">Identity Overview</h3>
                </div>
                <div class="p-6 md:p-8">
                    <div class="flex flex-col md:flex-row items-center gap-8 mb-8 pb-8 border-b border-slate-100 dark:border-slate-800">
                        <div class="relative w-24 h-24 flex items-center justify-center rounded-2xl bg-sky-50 dark:bg-slate-800 text-sky-600 dark:text-sky-400 border border-sky-100 dark:border-slate-700 shadow-sm shrink-0">
                            <i class="fas fa-user-md text-4xl"></i>
                            <span class="absolute -bottom-1 -right-1 w-6 h-6 bg-emerald-500 border-2 border-white dark:border-[#1f2937] rounded-full shadow-sm"></span>
                        </div>
                        <div class="text-center md:text-left flex-1 min-w-0">
                            <h2 class="text-2xl font-black text-slate-900 dark:text-white tracking-tight leading-none mb-2 truncate"><?php echo htmlspecialchars($user['full_name']); ?></h2>
                            <span class="inline-block text-xs font-bold px-3 py-1 rounded-full tracking-wide <?php echo $role_badge_style; ?>">
                                <?php echo ucfirst($user['role']); ?> Identity Clearance
                            </span>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 text-sm">
                        <div class="flex flex-col gap-1.5 p-4 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-100 dark:border-slate-800">
                            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider flex items-center gap-1.5"><i class="fas fa-user w-3"></i> Username</span>
                            <span class="font-bold text-slate-800 dark:text-slate-200 font-mono"><?php echo htmlspecialchars($user['username']); ?></span>
                        </div>
                        <div class="flex flex-col gap-1.5 p-4 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-100 dark:border-slate-800">
                            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider flex items-center gap-1.5"><i class="fas fa-clinic-medical w-3"></i> Assignment</span>
                            <span class="font-bold text-slate-800 dark:text-slate-200"><?php echo htmlspecialchars($user['clinic_name'] ?? 'General Scope / All Clinics'); ?></span>
                        </div>
                        <div class="flex flex-col gap-1.5 p-4 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-100 dark:border-slate-800">
                            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider flex items-center gap-1.5"><i class="fas fa-envelope w-3"></i> Email Reference</span>
                            <span class="font-bold text-slate-800 dark:text-slate-200 truncate"><?php echo htmlspecialchars($user['email'] ?? 'Not provisioned'); ?></span>
                        </div>
                        <div class="flex flex-col gap-1.5 p-4 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-100 dark:border-slate-800">
                            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider flex items-center gap-1.5"><i class="fas fa-calendar-alt w-3"></i> Provision Date</span>
                            <span class="font-bold text-slate-800 dark:text-slate-200"><?php echo date('F d, Y', strtotime($user['created_at'])); ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="bg-white dark:bg-[#1f2937] border border-slate-200 dark:border-slate-800 rounded-3xl shadow-sm overflow-hidden flex flex-col">
                    <div class="px-6 py-5 border-b border-slate-100 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-800/30 flex items-center gap-3">
                        <div class="w-8 h-8 rounded-lg bg-sky-500/10 flex items-center justify-center text-sky-500"><i class="fas fa-envelope-open-text"></i></div>
                        <h3 class="text-sm font-extrabold text-slate-900 dark:text-white uppercase tracking-wider">Update Contact</h3>
                    </div>
                    <form method="POST" class="p-6 md:p-8 flex flex-col flex-1">
                        <div class="mb-6 flex-1">
                            <label class="text-xs font-bold text-slate-500 dark:text-slate-400 tracking-wider uppercase mb-2 block">Email Address</label>
                            <input type="email" name="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" placeholder="example@hospital.gov.ph" class="w-full px-4 py-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-[#111827] text-slate-900 dark:text-white placeholder-slate-400 text-sm focus:outline-none focus:ring-1 focus:ring-sky-500 focus:border-sky-500 transition-all">
                            <p class="text-[10px] font-semibold text-slate-400 dark:text-slate-500 mt-2 pl-1">Leave blank if relay notifications are not required.</p>
                        </div>
                        <button type="submit" name="update_profile" class="w-full inline-flex items-center justify-center gap-2 px-5 py-3 rounded-xl bg-sky-600 hover:bg-sky-700 text-white text-xs font-bold uppercase tracking-wider shadow-sm transition-all mt-auto">
                            <i class="fas fa-save"></i> Save Reference
                        </button>
                    </form>
                </div>

                <div class="bg-white dark:bg-[#1f2937] border border-slate-200 dark:border-slate-800 rounded-3xl shadow-sm overflow-hidden flex flex-col">
                    <div class="px-6 py-5 border-b border-slate-100 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-800/30 flex items-center gap-3">
                        <div class="w-8 h-8 rounded-lg bg-amber-500/10 flex items-center justify-center text-amber-500"><i class="fas fa-key"></i></div>
                        <h3 class="text-sm font-extrabold text-slate-900 dark:text-white uppercase tracking-wider">Authentication Update</h3>
                    </div>
                    <form method="POST" class="p-6 md:p-8 flex flex-col flex-1">
                        <div class="space-y-4 mb-6 flex-1">
                            <div>
                                <label class="text-xs font-bold text-slate-500 dark:text-slate-400 tracking-wider uppercase mb-2 block">Current Password</label>
                                <input type="password" name="current_password" placeholder="••••••••" required class="w-full px-4 py-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-[#111827] text-slate-900 dark:text-white placeholder-slate-400 text-sm focus:outline-none focus:ring-1 focus:ring-amber-500 focus:border-amber-500 transition-all">
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="text-xs font-bold text-slate-500 dark:text-slate-400 tracking-wider uppercase mb-2 block">New Password</label>
                                    <input type="password" name="new_password" placeholder="Min. 6 chars" required minlength="6" class="w-full px-4 py-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-[#111827] text-slate-900 dark:text-white placeholder-slate-400 text-sm focus:outline-none focus:ring-1 focus:ring-amber-500 focus:border-amber-500 transition-all">
                                </div>
                                <div>
                                    <label class="text-xs font-bold text-slate-500 dark:text-slate-400 tracking-wider uppercase mb-2 block">Verify Hash</label>
                                    <input type="password" name="confirm_password" placeholder="Re-enter new" required class="w-full px-4 py-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-[#111827] text-slate-900 dark:text-white placeholder-slate-400 text-sm focus:outline-none focus:ring-1 focus:ring-amber-500 focus:border-amber-500 transition-all">
                                </div>
                            </div>
                        </div>
                        <button type="submit" name="update_password" class="w-full inline-flex items-center justify-center gap-2 px-5 py-3 rounded-xl bg-slate-800 hover:bg-slate-900 dark:bg-slate-700 dark:hover:bg-slate-600 text-white text-xs font-bold uppercase tracking-wider shadow-sm transition-all mt-auto">
                            <i class="fas fa-shield-alt"></i> Commit Key Change
                        </button>
                    </form>
                </div>
            </div>
            
        </div>
    </main>

    <script>
        function updateDateTime() {
            const now = new Date();
            const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
            document.getElementById('currentDate').textContent = now.toLocaleDateString('en-US', options);
            document.getElementById('currentTime').textContent = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
        }
        updateDateTime();
        setInterval(updateDateTime, 1000);

        const mobileMenuBtn = document.getElementById('mobileMenuBtn');
        const sidebar = document.getElementById('sidebar');
        if (mobileMenuBtn && sidebar) {
            mobileMenuBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                sidebar.classList.toggle('-translate-x-full');
            });
        }
        document.addEventListener('click', (e) => {
            if (sidebar && !sidebar.classList.contains('-translate-x-full')) {
                if (mobileMenuBtn && !mobileMenuBtn.contains(e.target)) {
                    sidebar.classList.add('-translate-x-full');
                }
            }
        });

        const profileMenuBtn = document.getElementById('profileMenuBtn');
        const profileDropdown = document.getElementById('profileDropdown');
        if (profileMenuBtn && profileDropdown) {
            profileMenuBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                profileDropdown.classList.toggle('hidden');
            });
            document.addEventListener('click', () => profileDropdown.classList.add('hidden'));
        }

        const themeToggleBtn = document.getElementById('themeToggleBtn');
        const themeToggleIcon = document.getElementById('themeToggleIcon');
        const htmlElement = document.documentElement;

        const isDark = localStorage.getItem('theme') === 'dark' || (!localStorage.getItem('theme') && window.matchMedia('(prefers-color-scheme: dark)').matches);
        if (isDark) {
            htmlElement.classList.add('dark');
            if(themeToggleIcon) themeToggleIcon.className = 'fas fa-sun text-lg text-amber-400';
        } else {
            if(themeToggleIcon) themeToggleIcon.className = 'fas fa-moon text-lg text-slate-500';
        }

        themeToggleBtn.addEventListener('click', () => {
            if (htmlElement.classList.contains('dark')) {
                htmlElement.classList.remove('dark');
                themeToggleIcon.className = 'fas fa-moon text-lg text-slate-500';
                localStorage.setItem('theme', 'light');
            } else {
                htmlElement.classList.add('dark');
                themeToggleIcon.className = 'fas fa-sun text-lg text-amber-400';
                localStorage.setItem('theme', 'dark');
            }
        });

        setTimeout(() => {
            document.querySelectorAll('.alert').forEach(alert => {
                alert.style.transition = 'all 0.4s ease-in-out';
                alert.style.opacity = '0';
                alert.style.transform = 'translateY(-10px)';
                setTimeout(() => alert.remove(), 400);
            });
        }, 5000);
    </script>
</body>
</html>