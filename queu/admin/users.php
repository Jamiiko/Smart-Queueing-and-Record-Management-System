<?php
// admin/users.php - User Management (Icon-less Clinic Column Layout)
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
// FIRST: Add missing columns if not exists
// ============================================
try {
    $db->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS `email` varchar(100) DEFAULT NULL");
    $db->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS `is_active` tinyint(1) DEFAULT 1");
} catch (PDOException $e) {
    // Columns might already exist, ignore error
}

// ============================================
// HANDLE USER ACTIONS
// ============================================

// Handle user deletion
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    if ($id != 1) { // Don't delete main admin
        $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $message = "User deleted successfully!";
    }
    header('Location: users.php');
    exit();
}

// Handle user activation
if (isset($_GET['activate'])) {
    $id = (int)$_GET['activate'];
    $stmt = $db->prepare("UPDATE users SET is_active = 1 WHERE id = ?");
    $stmt->execute([$id]);
    $message = "User activated successfully!";
    header('Location: users.php');
    exit();
}

// Handle user deactivation
if (isset($_GET['deactivate'])) {
    $id = (int)$_GET['deactivate'];
    if ($id != 1) { // Can't deactivate main admin
        $stmt = $db->prepare("UPDATE users SET is_active = 0 WHERE id = ?");
        $stmt->execute([$id]);
        $message = "User deactivated successfully!";
    }
    header('Location: users.php');
    exit();
}

// Handle user addition
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_user'])) {
    $username = trim($_POST['username']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email'] ?? '');
    $role = $_POST['role'];
    $clinic_id = !empty($_POST['clinic_id']) ? $_POST['clinic_id'] : null;
    $is_active = isset($_POST['is_active']) ? 1 : 1; // Default active
    
    $query = "INSERT INTO users (username, password, full_name, email, role, clinic_id, is_active) 
              VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt = $db->prepare($query);
    $stmt->execute([$username, $password, $full_name, $email, $role, $clinic_id, $is_active]);
    
    $message = "User added successfully!";
    header('Location: users.php');
    exit();
}

// Handle user edit
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_user'])) {
    $id = (int)$_POST['user_id'];
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email'] ?? '');
    $role = $_POST['role'];
    $clinic_id = !empty($_POST['clinic_id']) ? $_POST['clinic_id'] : null;
    
    $query = "UPDATE users SET full_name = ?, email = ?, role = ?, clinic_id = ? WHERE id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$full_name, $email, $role, $clinic_id, $id]);
    
    $message = "User updated successfully!";
    header('Location: users.php');
    exit();
}

// Handle password reset
if (isset($_GET['reset_password'])) {
    $id = (int)$_GET['reset_password'];
    if ($id != 1) { // Can't reset main admin password here
        $new_password = password_hash('clinic123', PASSWORD_DEFAULT);
        $stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->execute([$new_password, $id]);
        $message = "Password reset to 'clinic123' for user ID: $id";
    }
    header('Location: users.php');
    exit();
}

// ============================================
// GET SEARCH/FILTER PARAMETERS
// ============================================
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$role_filter = isset($_GET['role_filter']) ? $_GET['role_filter'] : '';
$status_filter = isset($_GET['status_filter']) ? $_GET['status_filter'] : '';

// Build query with filters
$query = "SELECT u.*, c.name as clinic_name 
          FROM users u
          LEFT JOIN clinics c ON u.clinic_id = c.id
          WHERE 1=1";
$params = [];

if (!empty($search)) {
    $query .= " AND (u.username LIKE ? OR u.full_name LIKE ? OR u.email LIKE ?)";
    $search_term = "%$search%";
    $params = array_merge($params, [$search_term, $search_term, $search_term]);
}

if (!empty($role_filter)) {
    $query .= " AND u.role = ?";
    $params[] = $role_filter;
}

if (!empty($status_filter)) {
    $is_active = ($status_filter == 'active') ? 1 : 0;
    $query .= " AND u.is_active = ?";
    $params[] = $is_active;
}

$query .= " ORDER BY u.id";
$stmt = $db->prepare($query);
$stmt->execute($params);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all clinics for dropdowns
$clinics = $db->query("SELECT * FROM clinics WHERE is_active = 1 ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$total_users = count($users);
$total_active = 0;
$total_inactive = 0;
$admin_count = 0;
$doctor_count = 0;
$nurse_count = 0;
$technician_count = 0;
$staff_count = 0;

foreach ($users as $user) {
    if ($user['is_active']) $total_active++;
    else $total_inactive++;
    
    switch ($user['role']) {
        case 'admin': $admin_count++; break;
        case 'doctor': $doctor_count++; break;
        case 'nurse': $nurse_count++; break;
        case 'technician': $technician_count++; break;
        case 'staff': $staff_count++; break;
    }
}

// Get user for edit modal (if edit parameter is set)
$edit_user = null;
if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$id]);
    $edit_user = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management | 4ID Station Hospital | Camp Evangelista</title>
    
    <!-- Consistent Fonts & Icons -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    
    <!-- Tailwind CSS CDN Engine -->
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
</head>
<body class="bg-slate-50 dark:bg-[#111827] text-slate-800 dark:text-slate-100 font-sans antialiased min-h-full transition-colors duration-200">

    <!-- Hover-Expandable Navigation Sidebar Drawer Frame -->
    <aside id="sidebar" class="fixed top-0 left-0 h-screen bg-white dark:bg-[#1f2937] border-r border-slate-300/90 dark:border-slate-700/80 shadow-xl md:shadow-none z-[1000] flex flex-col justify-between overflow-x-hidden transition-all duration-300 ease-in-out group/sidebar -translate-x-full md:translate-x-0 w-[260px] md:w-[80px] md:hover:w-[260px]">
        <div>
            <!-- Sidebar Header Layout -->
            <div class="p-4 border-b border-slate-300/90 dark:border-slate-700/60 mb-5 flex flex-col items-center justify-center min-h-[160px]">
                <div class="hidden md:flex md:group-hover/sidebar:hidden flex-col items-center justify-center font-extrabold text-2xl tracking-wider text-sky-600 dark:text-sky-400 leading-tight select-none">
                    <span>C</span><span>E</span><span>S</span><span>H</span>
                </div>
                <div class="flex md:hidden md:group-hover/sidebar:flex flex-col items-center">
                    <img src="../assets/images/logo.png" alt="CESH Logo" class="w-21 h-21 object-contain rounded-xl mb-2.5" onerror="this.style.display='none'">
                    <h2 class="text-slate-800 dark:text-slate-100 text-sm font-extrabold tracking-tight text-center whitespace-nowrap">4ID Station Hospital</h2>
                    <p class="text-slate-400 dark:text-slate-400 text-[10px] font-bold uppercase tracking-widest text-center whitespace-nowrap mt-1">Camp Evangelista</p>
                </div>
            </div>
            
            <nav class="px-3 md:group-hover/sidebar:px-4 transition-all duration-200">
                <ul class="list-none p-0 space-y-1.5">
                    <li>
                        <a href="dashboard.php" class="flex items-center rounded-xl font-medium transition-all duration-150 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700/50 p-3 justify-center md:justify-start gap-0 md:group-hover/sidebar:gap-4 border-l-4 border-transparent group/link">
                            <div class="w-6 h-6 flex items-center justify-center shrink-0">
                                <i class="fas fa-tachometer-alt text-base text-slate-400 group-hover/link:text-sky-500 transition-colors"></i>
                            </div>
                            <span class="opacity-100 md:opacity-0 md:group-hover/sidebar:opacity-100 text-xs tracking-wide whitespace-nowrap transition-opacity duration-200 origin-left">Dashboard</span>
                        </a>
                    </li>
                    <li>
                        <a href="patients.php" class="flex items-center rounded-xl font-medium transition-all duration-150 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700/50 p-3 justify-center md:justify-start gap-0 md:group-hover/sidebar:gap-4 border-l-4 border-transparent group/link">
                            <div class="w-6 h-6 flex items-center justify-center shrink-0">
                                <i class="fas fa-users text-base text-slate-400 group-hover/link:text-sky-500 transition-colors"></i>
                            </div>
                            <span class="opacity-100 md:opacity-0 md:group-hover/sidebar:opacity-100 text-xs tracking-wide whitespace-nowrap transition-opacity duration-200 origin-left">Patients</span>
                        </a>
                    </li>
                    <li>
                        <a href="queue-monitor.php" class="flex items-center rounded-xl font-medium transition-all duration-150 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700/50 p-3 justify-center md:justify-start gap-0 md:group-hover/sidebar:gap-4 border-l-4 border-transparent group/link">
                            <div class="w-6 h-6 flex items-center justify-center shrink-0">
                                <i class="fas fa-chart-line text-base text-slate-400 group-hover/link:text-sky-500 transition-colors"></i>
                            </div>
                            <span class="opacity-100 md:opacity-0 md:group-hover/sidebar:opacity-100 text-xs tracking-wide whitespace-nowrap transition-opacity duration-200 origin-left">Queue Monitor</span>
                        </a>
                    </li>
                    <li>
                        <a href="clinic-congestion.php" class="flex items-center rounded-xl font-medium transition-all duration-150 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700/50 p-3 justify-center md:justify-start gap-0 md:group-hover/sidebar:gap-4 border-l-4 border-transparent group/link">
                            <div class="w-6 h-6 flex items-center justify-center shrink-0">
                                <i class="fas fa-chart-pie text-base text-slate-400 group-hover/link:text-sky-500 transition-colors"></i>
                            </div>
                            <span class="opacity-100 md:opacity-0 md:group-hover/sidebar:opacity-100 text-xs tracking-wide whitespace-nowrap transition-opacity duration-200 origin-left">Clinic Congestion</span>
                        </a>
                    </li>
                    <li>
                        <a href="reports.php" class="flex items-center rounded-xl font-medium transition-all duration-150 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700/50 p-3 justify-center md:justify-start gap-0 md:group-hover/sidebar:gap-4 border-l-4 border-transparent group/link">
                            <div class="w-6 h-6 flex items-center justify-center shrink-0">
                                <i class="fas fa-chart-bar text-base text-slate-400 group-hover/link:text-sky-500 transition-colors"></i>
                            </div>
                            <span class="opacity-100 md:opacity-0 md:group-hover/sidebar:opacity-100 text-xs tracking-wide whitespace-nowrap transition-opacity duration-200 origin-left">Reports</span>
                        </a>
                    </li>
                    <li>
                        <a href="users.php" class="flex items-center rounded-xl font-semibold transition-all duration-150 bg-sky-50 dark:bg-sky-500/10 text-sky-600 dark:text-sky-400 border-l-4 border-sky-500 p-3 justify-center md:justify-start gap-0 md:group-hover/sidebar:gap-4">
                            <div class="w-6 h-6 flex items-center justify-center shrink-0">
                                <i class="fas fa-users-cog text-base"></i>
                            </div>
                            <span class="opacity-100 md:opacity-0 md:group-hover/sidebar:opacity-100 text-xs tracking-wide whitespace-nowrap transition-opacity duration-200 origin-left">User Management</span>
                        </a>
                    </li>
                    <li>
                        <a href="login-monitor.php" class="flex items-center rounded-xl font-medium transition-all duration-150 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700/50 p-3 justify-center md:justify-start gap-0 md:group-hover/sidebar:gap-4 border-l-4 border-transparent group/link">
                            <div class="w-6 h-6 flex items-center justify-center shrink-0">
                                <i class="fas fa-history text-base text-slate-400 group-hover/link:text-sky-500 transition-colors"></i>
                            </div>
                            <span class="opacity-100 md:opacity-0 md:group-hover/sidebar:opacity-100 text-xs tracking-wide whitespace-nowrap transition-opacity duration-200 origin-left">Login Monitor</span>
                        </a>
                    </li>
                </ul>
            </nav>
        </div>
    </aside>

    <!-- Main Workspace Area Framework -->
    <main class="min-h-screen ml-0 md:ml-[80px] px-6 sm:px-12 py-8 md:pl-14 lg:pl-16 transition-all duration-300 max-w-[1680px] mx-auto">
        
        <!-- Header Controls Panel Section -->
        <header class="flex flex-col sm:flex-row justify-between sm:items-center mb-8 pb-5 border-b border-slate-300/90 dark:border-slate-700/80 gap-4">
            <div class="flex items-center gap-4">
                <button id="mobileMenuBtn" class="md:hidden p-2.5 text-slate-600 dark:text-slate-300 bg-white dark:bg-[#1f2937] border border-slate-300 rounded-xl shadow-sm">
                    <i class="fas fa-bars text-xl"></i>
                </button>
                <div>
                    <h1 class="text-slate-900 dark:text-white text-2xl md:text-3xl font-extrabold tracking-tight mb-0.5">User Management</h1>
                    <p class="text-slate-500 dark:text-slate-400 text-xs md:text-sm font-medium">Manage system credentials, staff rosters, and clinical access rights</p>
                </div>
            </div>
            
            <div class="flex items-center justify-between sm:justify-end gap-4 relative">
                <div class="text-right text-xs hidden sm:block">
                    <div class="text-slate-700 dark:text-slate-300 font-bold" id="currentDate"></div>
                    <div class="text-sky-600 dark:text-sky-400 font-bold font-mono text-sm mt-0.5" id="currentTime"></div>
                </div>

                <button id="themeToggleBtn" class="w-10 h-10 flex items-center justify-center bg-white dark:bg-[#1f2937] border border-slate-300 dark:border-slate-700 rounded-xl transition-all shadow-sm" title="Toggle Visual Mode">
                    <i id="themeToggleIcon" class="fas fa-moon text-base"></i>
                </button>

                <div class="relative">
                    <button id="profileMenuBtn" class="w-11 h-11 bg-white dark:bg-[#1f2937] rounded-full flex items-center justify-center text-sky-600 dark:text-sky-400 border border-slate-300 dark:border-slate-700 shadow-sm hover:border-sky-500 dark:hover:border-sky-400 focus:outline-none transition-all duration-150">
                        <i class="fas fa-user-md text-lg"></i>
                    </button>
                    
                    <div id="profileDropdown" class="hidden absolute right-0 mt-2.5 w-60 bg-white dark:bg-[#1f2937] border border-slate-300 dark:border-slate-700 rounded-xl shadow-xl z-[1100] animate-[modalFadeIn_0.15s_ease-out]">
                        <div class="p-4 border-b border-slate-200 dark:border-slate-700/60 bg-slate-50 dark:bg-slate-800/40 rounded-t-xl">
                            <p class="text-xs font-bold text-slate-900 dark:text-white truncate">System Administrator</p>
                            <p class="text-[0.65rem] font-bold text-slate-400 uppercase tracking-wider truncate mt-0.5">HOSP-HQ COM</p>
                        </div>
                        <div class="p-1.5">
                            <a href="../logout.php" onclick="return confirm('Confirm Dashboard Exit?')" class="flex items-center gap-2.5 w-full text-left px-3 py-2.5 text-xs font-bold text-rose-500 hover:bg-rose-50 dark:hover:bg-rose-500/10 rounded-lg transition-colors">
                                <i class="fas fa-power-off text-sm"></i>
                                <span>Logout Session</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <!-- System Alert Messages Header -->
        <?php if (isset($message)): ?>
            <div id="alertNotification" class="bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/30 text-emerald-700 dark:text-emerald-400 px-4 py-3.5 rounded-xl text-xs font-bold uppercase tracking-wide mb-6 flex items-center gap-2.5 shadow-sm transition-opacity duration-300">
                <i class="fas fa-check-circle text-sm"></i> <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <!-- Balanced Comprehensive Performance Statistics Grid Overview -->
        <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-7 gap-4 mb-6">
            <div class="bg-white dark:bg-[#1f2937] border border-slate-300 dark:border-slate-700/70 rounded-xl p-4 shadow-sm flex flex-col items-center justify-center text-center">
                <div class="w-9 h-9 bg-slate-100 dark:bg-slate-700/50 rounded-xl flex items-center justify-center text-slate-500 dark:text-slate-400 text-sm mb-2"><i class="fas fa-users"></i></div>
                <div class="text-xl font-extrabold text-slate-900 dark:text-white font-mono leading-none"><?php echo $total_users; ?></div>
                <div class="text-slate-400 dark:text-slate-500 text-[9px] font-bold uppercase tracking-wider mt-1">Total Users</div>
            </div>
            <div class="bg-white dark:bg-[#1f2937] border border-slate-300 dark:border-slate-700/70 rounded-xl p-4 shadow-sm flex flex-col items-center justify-center text-center">
                <div class="w-9 h-9 bg-emerald-50 dark:bg-emerald-500/10 rounded-xl flex items-center justify-center text-emerald-500 text-sm mb-2"><i class="fas fa-check-circle"></i></div>
                <div class="text-xl font-extrabold text-emerald-600 dark:text-emerald-400 font-mono leading-none"><?php echo $total_active; ?></div>
                <div class="text-slate-400 dark:text-slate-500 text-[9px] font-bold uppercase tracking-wider mt-1">Active Accounts</div>
            </div>
            <div class="bg-white dark:bg-[#1f2937] border border-slate-300 dark:border-slate-700/70 rounded-xl p-4 shadow-sm flex flex-col items-center justify-center text-center">
                <div class="w-9 h-9 bg-rose-50 dark:bg-rose-500/10 rounded-xl flex items-center justify-center text-rose-500 text-sm mb-2"><i class="fas fa-ban"></i></div>
                <div class="text-xl font-extrabold text-rose-600 dark:text-rose-400 font-mono leading-none"><?php echo $total_inactive; ?></div>
                <div class="text-slate-400 dark:text-slate-500 text-[9px] font-bold uppercase tracking-wider mt-1">Inactive Locked</div>
            </div>
            <div class="bg-white dark:bg-[#1f2937] border border-slate-300 dark:border-slate-700/70 rounded-xl p-4 shadow-sm flex flex-col items-center justify-center text-center">
                <div class="w-9 h-9 bg-red-50 dark:bg-red-500/10 rounded-xl flex items-center justify-center text-red-500 text-sm mb-2"><i class="fas fa-crown"></i></div>
                <div class="text-xl font-extrabold text-red-600 dark:text-red-400 font-mono leading-none"><?php echo $admin_count; ?></div>
                <div class="text-slate-400 dark:text-slate-500 text-[9px] font-bold uppercase tracking-wider mt-1">Administrators</div>
            </div>
            <div class="bg-white dark:bg-[#1f2937] border border-slate-300 dark:border-slate-700/70 rounded-xl p-4 shadow-sm flex flex-col items-center justify-center text-center">
                <div class="w-9 h-9 bg-sky-50 dark:bg-sky-500/10 rounded-xl flex items-center justify-center text-sky-500 text-sm mb-2"><i class="fas fa-stethoscope"></i></div>
                <div class="text-xl font-extrabold text-sky-600 dark:text-sky-400 font-mono leading-none"><?php echo $doctor_count; ?></div>
                <div class="text-slate-400 dark:text-slate-500 text-[9px] font-bold uppercase tracking-wider mt-1">Physicians</div>
            </div>
            <div class="bg-white dark:bg-[#1f2937] border border-slate-300 dark:border-slate-700/70 rounded-xl p-4 shadow-sm flex flex-col items-center justify-center text-center">
                <div class="w-9 h-9 bg-indigo-50 dark:bg-indigo-500/10 rounded-xl flex items-center justify-center text-indigo-500 text-sm mb-2"><i class="fas fa-heartbeat"></i></div>
                <div class="text-xl font-extrabold text-indigo-600 dark:text-indigo-400 font-mono leading-none"><?php echo $nurse_count; ?></div>
                <div class="text-slate-400 dark:text-slate-500 text-[9px] font-bold uppercase tracking-wider mt-1">Nursing Staff</div>
            </div>
            <div class="bg-white dark:bg-[#1f2937] border border-slate-300 dark:border-slate-700/70 rounded-xl p-4 shadow-sm flex flex-col items-center justify-center text-center">
                <div class="w-9 h-9 bg-amber-50 dark:bg-amber-500/10 rounded-xl flex items-center justify-center text-amber-500 text-sm mb-2"><i class="fas fa-flask"></i></div>
                <div class="text-xl font-extrabold text-amber-600 dark:text-amber-500 font-mono leading-none"><?php echo ($technician_count + $staff_count); ?></div>
                <div class="text-slate-400 dark:text-slate-500 text-[9px] font-bold uppercase tracking-wider mt-1">Tech / Clerks</div>
            </div>
        </div>

        <!-- Add User Panel Controller Area -->
        <section class="bg-white dark:bg-[#1f2937] border border-slate-300 dark:border-slate-700/70 rounded-xl shadow-sm overflow-hidden mb-6">
            <div class="p-4 border-b border-slate-200 dark:border-slate-700/60 bg-slate-50/50 dark:bg-slate-800/20">
                <h3 class="text-xs font-bold uppercase text-slate-900 dark:text-white tracking-wider flex items-center gap-2"><i class="fas fa-user-plus text-sky-500 text-sm"></i> Create System Profile</h3>
            </div>
            <div class="p-5">
                <form method="POST" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-7 gap-4 items-end">
                    <div class="lg:col-span-1">
                        <label class="block text-slate-500 dark:text-slate-400 text-[10px] font-bold uppercase tracking-wider mb-2">Username</label>
                        <input type="text" name="username" placeholder="Profile ID" required class="w-full px-3 py-2 bg-slate-50 dark:bg-[#111827] border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white rounded-xl focus:outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500 text-xs transition-all">
                    </div>
                    <div class="lg:col-span-1">
                        <label class="block text-slate-500 dark:text-slate-400 text-[10px] font-bold uppercase tracking-wider mb-2">Password</label>
                        <input type="password" name="password" placeholder="••••••••" required class="w-full px-3 py-2 bg-slate-50 dark:bg-[#111827] border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white rounded-xl focus:outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500 text-xs transition-all">
                    </div>
                    <div class="lg:col-span-1">
                        <label class="block text-slate-500 dark:text-slate-400 text-[10px] font-bold uppercase tracking-wider mb-2">Full Name</label>
                        <input type="text" name="full_name" placeholder="Surname, First" required class="w-full px-3 py-2 bg-slate-50 dark:bg-[#111827] border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white rounded-xl focus:outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500 text-xs transition-all">
                    </div>
                    <div class="lg:col-span-1">
                        <label class="block text-slate-500 dark:text-slate-400 text-[10px] font-bold uppercase tracking-wider mb-2">Email Address</label>
                        <input type="email" name="email" placeholder="Optional" class="w-full px-3 py-2 bg-slate-50 dark:bg-[#111827] border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white rounded-xl focus:outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500 text-xs transition-all">
                    </div>
                    <div class="lg:col-span-1">
                        <label class="block text-slate-500 dark:text-slate-400 text-[10px] font-bold uppercase tracking-wider mb-2">System Role</label>
                        <select name="role" required class="w-full px-3 py-2 bg-slate-50 dark:bg-[#111827] border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white rounded-xl focus:outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500 text-xs transition-all">
                            <option value="">Select Role</option>
                            <option value="admin">Administrator</option>
                            <option value="doctor">Doctor</option>
                            <option value="nurse">Nurse</option>
                            <option value="technician">Technician</option>
                            <option value="staff">Staff</option>
                        </select>
                    </div>
                    <div class="lg:col-span-1">
                        <label class="block text-slate-500 dark:text-slate-400 text-[10px] font-bold uppercase tracking-wider mb-2">Assigned Unit</label>
                        <select name="clinic_id" class="w-full px-3 py-2 bg-slate-50 dark:bg-[#111827] border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white rounded-xl focus:outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500 text-xs transition-all">
                            <option value="">All Clinics</option>
                            <?php foreach ($clinics as $clinic): ?>
                                <option value="<?php echo $clinic['id']; ?>"><?php echo htmlspecialchars($clinic['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="lg:col-span-1">
                        <button type="submit" name="add_user" class="w-full bg-emerald-600 dark:bg-emerald-500 text-white font-bold text-[10px] tracking-wide uppercase px-4 py-2.5 rounded-xl hover:bg-emerald-700 dark:hover:bg-emerald-600 transition-all flex items-center justify-center gap-1.5 shadow-sm">
                            <i class="fas fa-plus text-xs"></i> Add Profile
                        </button>
                    </div>
                </form>
            </div>
        </section>

        <!-- Dynamic Filter Framework Assembly Module -->
        <section class="bg-white dark:bg-[#1f2937] border border-slate-300 dark:border-slate-700/70 rounded-xl shadow-sm overflow-hidden mb-6">
            <div class="p-4 border-b border-slate-200 dark:border-slate-700/60 bg-slate-50/50 dark:bg-slate-800/20 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <h3 class="text-xs font-bold uppercase text-slate-900 dark:text-white tracking-wider flex items-center gap-2"><i class="fas fa-filter text-sky-500 text-sm"></i> Data Stream Filters</h3>
                
                <div class="flex flex-wrap items-center gap-3">
                    <div class="flex items-center bg-slate-50 dark:bg-[#111827] border border-slate-300 dark:border-slate-700 rounded-xl px-3 py-1.5 gap-2 w-full sm:w-64">
                        <i class="fas fa-search text-slate-400 text-xs"></i>
                        <input type="text" id="searchInput" placeholder="Search accounts..." class="bg-transparent border-none text-slate-900 dark:text-white text-xs outline-none w-full focus:ring-0">
                    </div>
                    
                    <select id="roleFilter" class="bg-slate-50 dark:bg-[#111827] border border-slate-300 dark:border-slate-700 text-slate-800 dark:text-white text-xs rounded-xl px-3 py-2 outline-none focus:border-sky-500">
                        <option value="">All Roles</option>
                        <option value="admin">Admin</option>
                        <option value="doctor">Doctor</option>
                        <option value="nurse">Nurse</option>
                        <option value="technician">Technician</option>
                        <option value="staff">Staff</option>
                    </select>
                    
                    <select id="statusFilter" class="bg-slate-50 dark:bg-[#111827] border border-slate-300 dark:border-slate-700 text-slate-800 dark:text-white text-xs rounded-xl px-3 py-2 outline-none focus:border-sky-500">
                        <option value="">All Status</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                    
                    <button id="applyFilters" class="bg-sky-600 dark:bg-sky-500 hover:bg-sky-700 text-white font-bold text-[10px] uppercase tracking-wider px-4 py-2 rounded-xl transition-colors shadow-sm flex items-center gap-1.5">
                        <i class="fas fa-filter"></i> Apply Filters
                    </button>
                    <a href="users.php" class="bg-slate-100 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-300 text-[10px] font-bold uppercase tracking-wider px-4 py-2 rounded-xl hover:bg-slate-200 transition-colors">
                        Clear
                    </a>
                </div>
            </div>
        </section>

        <!-- Main Records Ledger Matrix Table -->
        <section class="bg-white dark:bg-[#1f2937] border border-slate-300 dark:border-slate-700/70 rounded-xl shadow-sm overflow-hidden mb-8">
            <div class="p-4 border-b border-slate-200 dark:border-slate-700/60 bg-slate-50/50 dark:bg-slate-800/20">
                <h3 class="text-xs font-bold uppercase text-slate-900 dark:text-white tracking-wider flex items-center gap-2"><i class="fas fa-list-ul text-sky-500 text-sm"></i> Authenticated Personnel Registry</h3>
            </div>
            
            <div class="overflow-x-auto">
                <?php if (empty($users)): ?>
                    <div class="p-12 text-center text-slate-400 dark:text-slate-500 flex flex-col items-center justify-center">
                        <i class="fas fa-users-slash text-4xl mb-3 text-slate-300 dark:text-slate-700"></i>
                        <p class="font-bold uppercase tracking-wider text-xs">No Secure Profiles Located</p>
                        <small class="text-[11px] text-slate-400 mt-1">Utilize the creation panel above to initialize new records.</small>
                    </div>
                <?php else: ?>
                    <table class="w-full border-collapse text-left" id="usersTable">
                        <thead>
                            <tr class="bg-slate-50 dark:bg-slate-800/50 border-b border-slate-300 dark:border-slate-700/80 text-slate-400 text-[10px] font-bold uppercase tracking-wider">
                                <th class="py-3.5 px-4 text-center">ID</th>
                                <th class="py-3.5 px-4">Username</th>
                                <th class="py-3.5 px-4">Full Name</th>
                                <th class="py-3.5 px-4">Email</th>
                                <th class="py-3.5 px-4">Role Designation</th>
                                <th class="py-3.5 px-4">Clinic Anchor</th>
                                <th class="py-3.5 px-4">Security Status</th>
                                <th class="py-3.5 px-4 text-right">Operational Infrastructure</th>
                            </tr>
                        </thead>
                        <tbody id="usersList" class="divide-y divide-slate-200 dark:divide-slate-700/60 text-xs font-medium text-slate-700 dark:text-slate-300">
                            <?php foreach ($users as $user): ?>
                            <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-800/30 transition-colors" data-id="<?php echo $user['id']; ?>" data-username="<?php echo strtolower($user['username']); ?>" data-name="<?php echo strtolower($user['full_name']); ?>" data-email="<?php echo strtolower($user['email'] ?? ''); ?>" data-role="<?php echo $user['role']; ?>" data-status="<?php echo $user['is_active'] ? 'active' : 'inactive'; ?>">
                                <td class="py-3 px-4 text-center font-mono text-slate-400 font-bold"><?php echo $user['id']; ?></td>
                                <td class="py-3 px-4 font-mono font-bold text-slate-900 dark:text-white"><?php echo htmlspecialchars($user['username']); ?></td>
                                <td class="py-3 px-4 font-semibold text-slate-800 dark:text-slate-200"><?php echo htmlspecialchars($user['full_name']); ?></td>
                                <td class="py-3 px-4 text-slate-500 dark:text-slate-400 font-mono"><?php echo htmlspecialchars($user['email'] ?? '—'); ?></td>
                                <td class="py-3 px-4">
                                    <?php
                                        $roleStyles = [
                                            'admin' => 'bg-rose-50 dark:bg-rose-500/10 text-rose-600 dark:text-rose-400',
                                            'doctor' => 'bg-sky-50 dark:bg-sky-500/10 text-sky-600 dark:text-sky-400',
                                            'nurse' => 'bg-emerald-50 dark:bg-emerald-500/10 text-emerald-600 dark:text-emerald-400',
                                            'technician' => 'bg-amber-50 dark:bg-amber-500/10 text-amber-600 dark:text-amber-400',
                                            'staff' => 'bg-slate-100 dark:bg-slate-700/60 text-slate-600 dark:text-slate-300'
                                        ];
                                        $badgeClass = $roleStyles[$user['role']] ?? $roleStyles['staff'];
                                    ?>
                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold tracking-wide uppercase <?php echo $badgeClass; ?>">
                                        <?php echo ucfirst($user['role']); ?>
                                    </span>
                                </td>
                                <td class="py-3 px-4">
                                    <?php if ($user['clinic_name']): ?>
                                        <!-- Clean Clean Text-Only Dynamic Output -->
                                        <span class="text-slate-600 dark:text-slate-300 font-semibold"><?php echo htmlspecialchars($user['clinic_name']); ?></span>
                                    <?php else: ?>
                                        <span class="text-slate-400 dark:text-slate-500 font-bold uppercase tracking-wider text-[10px]">All Clinics</span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3 px-4">
                                    <?php if ($user['is_active']): ?>
                                        <span class="inline-flex items-center gap-1 text-emerald-600 dark:text-emerald-400 font-bold uppercase tracking-wide text-[10px] bg-emerald-50 dark:bg-emerald-500/10 px-2 py-0.5 rounded-md"><i class="fas fa-check-circle text-[9px]"></i> Active</span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center gap-1 text-slate-400 dark:text-slate-500 font-bold uppercase tracking-wide text-[10px] bg-slate-100 dark:bg-slate-800 px-2 py-0.5 rounded-md"><i class="fas fa-ban text-[9px]"></i> Locked</span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3 px-4 text-right">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <?php if ($user['id'] != 1): ?>
                                            <button onclick="openEditModal(<?php echo $user['id']; ?>)" class="w-8 h-8 flex items-center justify-center rounded-lg bg-amber-500/10 text-amber-600 dark:text-amber-400 hover:bg-amber-500 hover:text-white transition-colors" title="Modify Parameters">
                                                <i class="fas fa-edit text-xs"></i>
                                            </button>
                                            
                                            <?php if ($user['is_active']): ?>
                                                <a href="?deactivate=<?php echo $user['id']; ?>" class="w-8 h-8 flex items-center justify-center rounded-lg bg-rose-500/10 text-rose-500 hover:bg-rose-500 hover:text-white transition-colors" title="Lock System Profile" onclick="return confirm('Deactivate account structural access rules?')">
                                                    <i class="fas fa-pause-circle text-xs"></i>
                                                </a>
                                            <?php else: ?>
                                                <a href="?activate=<?php echo $user['id']; ?>" class="w-8 h-8 flex items-center justify-center rounded-lg bg-emerald-500/10 text-emerald-500 hover:bg-emerald-500 hover:text-white transition-colors" title="Unlock System Profile" onclick="return confirm('Activate user terminal operations access?')">
                                                    <i class="fas fa-play-circle text-xs"></i>
                                                </a>
                                            <?php endif; ?>
                                            
                                            <a href="?reset_password=<?php echo $user['id']; ?>" class="w-8 h-8 flex items-center justify-center rounded-lg bg-sky-500/10 text-sky-600 dark:text-sky-400 hover:bg-sky-500 hover:text-white transition-colors" title="Reset Encryption Key" onclick="return confirm('Reset authorization credential matrix to default password (clinic123)?')">
                                                <i class="fas fa-key text-xs"></i>
                                            </a>
                                            
                                            <a href="?delete=<?php echo $user['id']; ?>" class="w-8 h-8 flex items-center justify-center rounded-lg bg-red-600/10 text-red-500 hover:bg-red-600 hover:text-white transition-colors" title="Purge Sequence Record" onclick="return confirm('Purge data sequence permanently? Cannot be reversed.')">
                                                <i class="fas fa-trash-alt text-xs"></i>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest bg-slate-100 dark:bg-slate-800 px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 flex items-center gap-1.5"><i class="fas fa-shield-alt text-sky-500"></i> Guarded Admin</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </section>
    </main>

    <!-- Tailwind Redesigned Edit Profile Modal Context -->
    <div id="editModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-[2000] hidden items-center justify-center p-4">
        <div class="bg-white dark:bg-[#1f2937] border border-slate-300 dark:border-slate-700 rounded-2xl w-full max-w-md shadow-2xl overflow-hidden">
            <div class="p-4 border-b border-slate-200 dark:border-slate-700/60 bg-slate-50/50 dark:bg-slate-800/20 flex justify-between items-center">
                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-900 dark:text-white flex items-center gap-2"><i class="fas fa-user-edit text-sky-500"></i> Modify Account Parameters</h3>
                <span class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 font-bold cursor-pointer text-xl leading-none" onclick="closeEditModal()">&times;</span>
            </div>
            <form method="POST">
                <div class="p-6 space-y-4">
                    <input type="hidden" name="user_id" id="edit_user_id">
                    <input type="hidden" name="edit_user" value="1">
                    
                    <div>
                        <label class="block text-slate-400 dark:text-slate-400 text-[10px] font-bold uppercase tracking-wider mb-2">Legal Identity / Full Name</label>
                        <input type="text" name="full_name" id="edit_full_name" required class="w-full px-4 py-2.5 bg-slate-50 dark:bg-[#111827] border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white rounded-xl focus:outline-none focus:border-sky-500 text-xs font-semibold transition-all">
                    </div>
                    <div>
                        <label class="block text-slate-400 dark:text-slate-400 text-[10px] font-bold uppercase tracking-wider mb-2">Electronic Mail Address</label>
                        <input type="email" name="email" id="edit_email" class="w-full px-4 py-2.5 bg-slate-50 dark:bg-[#111827] border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white rounded-xl focus:outline-none focus:border-sky-500 text-xs transition-all">
                    </div>
                    <div>
                        <label class="block text-slate-400 dark:text-slate-400 text-[10px] font-bold uppercase tracking-wider mb-2">Security Clearance Role</label>
                        <select name="role" id="edit_role" required class="w-full px-4 py-2.5 bg-slate-50 dark:bg-[#111827] border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white rounded-xl focus:outline-none focus:border-sky-500 text-xs font-semibold transition-all">
                            <option value="admin">Administrator</option>
                            <option value="doctor">Doctor</option>
                            <option value="nurse">Nurse</option>
                            <option value="technician">Technician</option>
                            <option value="staff">Staff</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-slate-400 dark:text-slate-400 text-[10px] font-bold uppercase tracking-wider mb-2">Clinical Attachment Unit</label>
                        <select name="clinic_id" id="edit_clinic_id" class="w-full px-4 py-2.5 bg-slate-50 dark:bg-[#111827] border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white rounded-xl focus:outline-none focus:border-sky-500 text-xs transition-all">
                            <option value="">All Clinics</option>
                            <?php foreach ($clinics as $clinic): ?>
                                <option value="<?php echo $clinic['id']; ?>"><?php echo htmlspecialchars($clinic['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="p-4 bg-slate-50 dark:bg-slate-800/40 border-t border-slate-200 dark:border-slate-700/60 flex justify-end gap-3">
                    <button type="button" class="bg-white dark:bg-slate-800 border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-300 text-[10px] font-bold uppercase tracking-wider px-4 py-2.5 rounded-xl hover:bg-slate-100 transition-colors" onclick="closeEditModal()">Cancel</button>
                    <button type="submit" class="bg-sky-600 dark:bg-sky-500 text-white font-bold text-[10px] uppercase tracking-wider px-5 py-2.5 rounded-xl hover:bg-sky-700 dark:hover:bg-sky-600 transition-colors shadow-sm">Commit Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Essential Security Automation & UI Interactions -->
    <script>
        // System Clock Integration
        function updateDateTime() {
            const now = new Date();
            const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
            document.getElementById('currentDate').textContent = now.toLocaleDateString('en-US', options);
            document.getElementById('currentTime').textContent = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
        }
        updateDateTime();
        setInterval(updateDateTime, 1000);

        // Sidebar Responsive Drawer Toggle Logic
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

        // Header Profile Context Menu Dropdown
        const profileMenuBtn = document.getElementById('profileMenuBtn');
        const profileDropdown = document.getElementById('profileDropdown');
        if (profileMenuBtn && profileDropdown) {
            profileMenuBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                profileDropdown.classList.toggle('hidden');
            });
            document.addEventListener('click', () => profileDropdown.classList.add('hidden'));
        }

        // Dark/Light Mode Theme Architecture
        const themeToggleBtn = document.getElementById('themeToggleBtn');
        const themeToggleIcon = document.getElementById('themeToggleIcon');
        
        if (localStorage.getItem('theme') === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark'); 
            if(themeToggleIcon) themeToggleIcon.className = 'fas fa-sun text-base text-amber-400';
        } else {
            if(themeToggleIcon) themeToggleIcon.className = 'fas fa-moon text-base text-slate-500';
        }
        
        if(themeToggleBtn) {
            themeToggleBtn.addEventListener('click', () => {
                if (document.documentElement.classList.contains('dark')) {
                    document.documentElement.classList.remove('dark'); 
                    localStorage.setItem('theme', 'light'); 
                    if(themeToggleIcon) themeToggleIcon.className = 'fas fa-moon text-base text-slate-500';
                } else {
                    document.documentElement.classList.add('dark'); 
                    localStorage.setItem('theme', 'dark'); 
                    if(themeToggleIcon) themeToggleIcon.className = 'fas fa-sun text-base text-amber-400';
                }
            });
        }

        // Edit Profile Context Modal Controller Links
        function openEditModal(userId) {
            const row = document.querySelector(`tr[data-id="${userId}"]`);
            if (row) {
                document.getElementById('edit_user_id').value = userId;
                document.getElementById('edit_full_name').value = row.cells[2]?.innerText.trim() || '';
                document.getElementById('edit_email').value = row.cells[3]?.innerText.trim() || '';
                const roleCell = row.cells[4]?.innerText.trim().toLowerCase() || 'staff';
                document.getElementById('edit_role').value = roleCell;
                
                const targetModal = document.getElementById('editModal');
                targetModal.style.display = 'flex';
                targetModal.classList.remove('hidden');
            }
        }
        function closeEditModal() { 
            const targetModal = document.getElementById('editModal');
            targetModal.style.display = 'none'; 
            targetModal.classList.add('hidden');
        }
        window.onclick = function(e) { if (e.target == document.getElementById('editModal')) closeEditModal(); }

        // Dynamic Instant Matrix Filter Framework Engine
        function filterTable() {
            const searchTerm = document.getElementById('searchInput')?.value.toLowerCase() || '';
            const roleFilter = document.getElementById('roleFilter')?.value || '';
            const statusFilter = document.getElementById('statusFilter')?.value || '';
            const rows = document.querySelectorAll('#usersList tr');
            rows.forEach(row => {
                const username = row.dataset.username || '';
                const name = row.dataset.name || '';
                const email = row.dataset.email || '';
                const role = row.dataset.role || '';
                const status = row.dataset.status || '';
                let show = true;
                if (searchTerm && !username.includes(searchTerm) && !name.includes(searchTerm) && !email.includes(searchTerm)) show = false;
                if (roleFilter && role !== roleFilter) show = false;
                if (statusFilter && status !== statusFilter) show = false;
                row.style.display = show ? '' : 'none';
            });
        }
        document.getElementById('applyFilters')?.addEventListener('click', filterTable);
        document.getElementById('searchInput')?.addEventListener('keyup', filterTable);
        document.getElementById('roleFilter')?.addEventListener('change', filterTable);
        document.getElementById('statusFilter')?.addEventListener('change', filterTable);

        // System Alert Autohide
        const structuralAlert = document.getElementById('alertNotification');
        if(structuralAlert) {
            setTimeout(() => { 
                structuralAlert.style.opacity = '0'; 
                setTimeout(() => structuralAlert.remove(), 400); 
            }, 4000);
        }

        // ============================================
        // PRESERVED SECURITY & INACTIVITY TIMEOUT FUNCTIONS
        // ============================================
        const INACTIVITY_TIMEOUT = 30 * 60 * 1000; // 30 minutes
        let inactivityTimer;
        let warningTimer;
        let warningShown = false;

        function resetInactivityTimer() {
            if (inactivityTimer) clearTimeout(inactivityTimer);
            if (warningTimer) clearTimeout(warningTimer);
            warningShown = false;
            hideWarningModal();
            
            inactivityTimer = setTimeout(logoutUser, INACTIVITY_TIMEOUT);
            warningTimer = setTimeout(showWarningModal, INACTIVITY_TIMEOUT - (2 * 60 * 1000));
            sendHeartbeat();
        }

        function sendHeartbeat() {
            fetch('heartbeat.php', {
                method: 'POST',
                credentials: 'same-origin'
            }).catch(err => console.log('Heartbeat failed:', err));
        }

        function logoutUser() {
            const logoutMsg = document.createElement('div');
            logoutMsg.innerHTML = `
                <div style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; 
                            background: rgba(15, 23, 42, 0.85); z-index: 9999; display: flex; 
                            align-items: center; justify-content: center; backdrop-filter: blur(4px);">
                    <div style="background: white; padding: 36px; border-radius: 16px; text-align: center; max-width: 400px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1);">
                        <i class="fas fa-clock" style="font-size: 48px; color: #EF4444; margin-bottom: 20px;"></i>
                        <h3 style="font-size: 1.25rem; font-weight: 700; color: #0F172A; margin-bottom: 8px;">Session Expired</h3>
                        <p style="color: #64748B; font-size: 0.95rem;">You have been logged out due to inactivity.</p>
                        <div style="margin-top: 24px;">
                            <p style="font-size: 0.85rem; color: #94A3B8;">Redirecting to login page...</p>
                        </div>
                    </div>
                </div>
            `;
            document.body.appendChild(logoutMsg);
            setTimeout(function() { window.location.href = '../logout.php'; }, 2000);
        }

        function showWarningModal() {
            if (warningShown) return;
            warningShown = true;
            
            const modal = document.createElement('div');
            modal.id = 'sessionWarningModal';
            modal.innerHTML = `
                <div style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; 
                            background: rgba(15, 23, 42, 0.6); z-index: 10000; display: flex; 
                            align-items: center; justify-content: center; backdrop-filter: blur(4px);">
                    <div style="background: white; padding: 36px; border-radius: 16px; text-align: center; max-width: 400px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1);">
                        <i class="fas fa-hourglass-half" style="font-size: 44px; color: #F59E0B; margin-bottom: 20px;"></i>
                        <h3 style="font-size: 1.25rem; font-weight: 700; color: #0F172A; margin-bottom: 8px;">Session Terminating</h3>
                        <p style="color: #64748B; font-size: 0.95rem; margin-bottom: 4px;">You will be safely logged out due to systemic inactivity.</p>
                        <p id="countdownText" style="font-size: 1.75rem; font-weight: 700; color: #0F172A; margin: 16px 0;">2:00</p>
                        <button onclick="keepSessionAlive()" style="background: #0ea5e9; color: white; border: none; padding: 12px 24px; border-radius: 8px; cursor: pointer; font-weight: 600; font-size: 0.9rem; width: 100%;">
                            <i class="fas fa-mouse-pointer" style="margin-right: 6px;"></i> Extend Session
                        </button>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);
            
            let secondsLeft = 120;
            const countdownElement = document.getElementById('countdownText');
            const countdownInterval = setInterval(function() {
                secondsLeft--;
                const minutes = Math.floor(secondsLeft / 60);
                const seconds = secondsLeft % 60;
                if (countdownElement) {
                    countdownElement.textContent = `${minutes}:${seconds.toString().padStart(2, '0')}`;
                }
                if (secondsLeft <= 0) clearInterval(countdownInterval);
            }, 1000);
        }

        function keepSessionAlive() {
            hideWarningModal();
            fetch('heartbeat.php', { method: 'POST', credentials: 'same-origin' })
            .then(function() { resetInactivityTimer(); })
            .catch(function(err) {
                console.log('Heartbeat failed:', err);
                resetInactivityTimer();
            });
        }

        function hideWarningModal() {
            const modal = document.getElementById('sessionWarningModal');
            if (modal) modal.remove();
        }

        const events = ['mousedown', 'mousemove', 'keypress', 'scroll', 'touchstart', 'click', 'keydown'];
        events.forEach(function(event) { document.addEventListener(event, resetInactivityTimer, false); });

        resetInactivityTimer();
        setInterval(function() { if (!warningShown) sendHeartbeat(); }, 5 * 60 * 1000);
    </script>
</body>
</html>