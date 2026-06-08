<?php
// admin/patients.php - Patient Master Directory & Management
// Camp Evangelista Station Hospital

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/SessionManager.php';

session_start();

// Access Validation Controls
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: ../index.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

$message = '';
$error = '';

// Catch messages passed from the registration redirect
if (isset($_GET['msg'])) {
    $message = $_GET['msg'];
}

// --- BACKEND PROCESSING: HANDLE PATIENT DELETION ---
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    
    // Safety verification check: verify if historical or pending logs exist
    $check = "SELECT COUNT(*) as count FROM queue_entries WHERE patient_id = ?";
    $stmt = $db->prepare($check);
    $stmt->execute([$id]);
    $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    if ($count == 0) {
        $query = "DELETE FROM patients WHERE id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$id]);
        $message = "Patient record dropped and purged successfully.";
    } else {
        $error = "Security Violation: Cannot drop patient with existing queue records attached.";
    }
}

// --- DATA INTAKE LOGIC: SEARCH FILTER ROUTINES ---
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$type = isset($_GET['type']) ? trim($_GET['type']) : '';

// Base query matching schema variables
$query = "SELECT p.*, 
          (SELECT COUNT(*) FROM queue_entries WHERE patient_id = p.id) as total_visits 
          FROM patients p WHERE 1=1";
$params = [];

if (!empty($search)) {
    $query .= " AND (p.first_name LIKE ? OR p.last_name LIKE ? OR p.mrn LIKE ? OR p.contact_number LIKE ?)";
    $search_param = "%{$search}%";
    array_push($params, $search_param, $search_param, $search_param, $search_param);
}

if (!empty($type)) {
    $query .= " AND p.patient_type = ?";
    array_push($params, $type);
}

$query .= " ORDER BY p.last_name ASC, p.first_name ASC";
$stmt = $db->prepare($query);
$stmt->execute($params);
$patients = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- SCALAR SUMMARY STATISTICS METRICS ---
$total_patients_count = $db->query("SELECT COUNT(*) FROM patients")->fetchColumn() ?? 0;
$military_count  = $db->query("SELECT COUNT(*) FROM patients WHERE patient_type = 'Military Personnel'")->fetchColumn() ?? 0;
$dependent_count = $db->query("SELECT COUNT(*) FROM patients WHERE patient_type = 'Dependent'")->fetchColumn() ?? 0;
$pwd_count       = $db->query("SELECT COUNT(*) FROM patients WHERE is_pwd = 1")->fetchColumn() ?? 0;
$senior_count    = $db->query("SELECT COUNT(*) FROM patients WHERE is_senior = 1")->fetchColumn() ?? 0;
$pregnant_count  = $db->query("SELECT COUNT(*) FROM patients WHERE is_pregnant = 1")->fetchColumn() ?? 0;
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patients Registry | 4ID Station Hospital | Camp Evangelista</title>
    
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
</head>
<body class="bg-slate-50 dark:bg-[#111827] text-slate-800 dark:text-slate-100 font-sans antialiased min-h-full transition-colors duration-200">

    <!-- Left Navigation Sidebar Panel -->
    <aside id="sidebar" class="fixed top-0 left-0 h-screen bg-white dark:bg-[#1f2937] border-r border-slate-300/90 dark:border-slate-700/80 shadow-xl md:shadow-none z-[1000] flex flex-col justify-between overflow-x-hidden transition-all duration-300 ease-in-out group/sidebar -translate-x-full md:translate-x-0 w-[260px] md:w-[80px] md:hover:w-[260px]">
        <div>
            <!-- Sidebar Header Layout -->
            <div class="p-4 border-b border-slate-300/90 dark:border-slate-700/60 mb-5 flex flex-col items-center justify-center min-h-[160px]">
                <!-- Permanent Compact view text block (Remains visible on desktop until sidebar expands) -->
                <div class="hidden md:flex md:group-hover/sidebar:hidden flex-col items-center justify-center font-extrabold text-2xl tracking-wider text-sky-600 dark:text-sky-400 leading-tight select-none animate-[fadeIn_0.15s_ease-in-out]">
                    <span>C</span><span>E</span><span>S</span><span>H</span>
                </div>
                <!-- Dynamic Hover/Mobile layout view (Displays logo and full text when expanded) -->
                <div class="flex md:hidden md:group-hover/sidebar:flex flex-col items-center animate-[fadeIn_0.2s_ease-in-out]">
                    <img src="../assets/images/logo.png" alt="CESH Logo" class="w-21 h-21 object-contain rounded-xl mb-2.5">
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
                        <a href="patients.php" class="flex items-center rounded-xl font-semibold transition-all duration-150 bg-sky-50 dark:bg-sky-500/10 text-sky-600 dark:text-sky-400 border-l-4 border-sky-500 p-3 justify-center md:justify-start gap-0 md:group-hover/sidebar:gap-4">
                            <div class="w-6 h-6 flex items-center justify-center shrink-0">
                                <i class="fas fa-users text-base"></i>
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
                        <a href="users.php" class="flex items-center rounded-xl font-medium transition-all duration-150 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700/50 p-3 justify-center md:justify-start gap-0 md:group-hover/sidebar:gap-4 border-l-4 border-transparent group/link">
                            <div class="w-6 h-6 flex items-center justify-center shrink-0">
                                <i class="fas fa-users-cog text-base"></i>
                            </div>
                            <span class="opacity-100 md:opacity-0 md:group-hover/sidebar:opacity-100 text-xs tracking-wide whitespace-nowrap transition-opacity duration-200 origin-left">User Management</span>
                        </a>
                    </li>
                    <li>
                        <a href="login-monitor.php" class="flex items-center rounded-xl font-medium transition-all duration-150 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700/50 p-3 justify-center md:justify-start gap-0 md:group-hover/sidebar:gap-4 border-l-4 border-transparent group/link">
                            <div class="w-6 h-6 flex items-center justify-center shrink-0">
                                <i class="fas fa-history text-base"></i>
                            </div>
                            <span class="opacity-100 md:opacity-0 md:group-hover/sidebar:opacity-100 text-xs tracking-wide whitespace-nowrap transition-opacity duration-200 origin-left">Login Monitor</span>
                        </a>
                    </li>
                </ul>
            </nav>
        </div>
    </aside>

    <!-- Main Workspace Frame -->
    <main class="min-h-screen ml-0 md:ml-[80px] px-6 sm:px-12 py-8 md:pl-14 lg:pl-16 transition-all duration-300 max-w-[1680px] mx-auto">
        
        <header class="flex flex-col sm:flex-row justify-between sm:items-center mb-8 pb-5 border-b border-slate-300/90 dark:border-slate-700/80 gap-4">
            <div class="flex items-center gap-4">
                <button id="mobileMenuBtn" class="md:hidden p-2.5 text-slate-600 dark:text-slate-300 bg-white dark:bg-[#1f2937] border border-slate-300 rounded-xl shadow-sm">
                    <i class="fas fa-bars text-xl"></i>
                </button>
                <div>
                    <h1 class="text-slate-900 dark:text-white text-2xl md:text-3xl font-extrabold tracking-tight mb-0.5">Patients Directory</h1>
                    <p class="text-slate-500 dark:text-slate-400 text-xs md:text-sm font-medium">Authorized clinical demographic registers and medical reference logs</p>
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

        <!-- Notification Alerts -->
        <?php if (!empty($message)): ?>
            <div class="alert p-4 mb-6 bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-300 dark:border-emerald-500/20 text-emerald-600 dark:text-emerald-400 rounded-xl flex items-center justify-between shadow-sm">
                <div class="flex items-center"><i class="fas fa-check-circle mr-3 text-base"></i> <span class="text-xs font-bold uppercase tracking-wide"><?= htmlspecialchars($message) ?></span></div>
                <button onclick="this.parentElement.remove()" class="text-slate-400 hover:text-emerald-500"><i class="fas fa-times text-base"></i></button>
            </div>
        <?php endif; ?>
        <?php if (!empty($error)): ?>
            <div class="alert p-4 mb-6 bg-rose-50 dark:bg-rose-500/10 border border-rose-300 dark:border-rose-500/20 text-rose-600 dark:text-rose-400 rounded-xl flex items-center justify-between shadow-sm">
                <div class="flex items-center"><i class="fas fa-exclamation-triangle mr-3 text-base"></i> <span class="text-xs font-bold uppercase tracking-wide"><?= htmlspecialchars($error) ?></span></div>
                <button onclick="this.parentElement.remove()" class="text-slate-400 hover:text-rose-500"><i class="fas fa-times text-base"></i></button>
            </div>
        <?php endif; ?>

        <!-- Balanced Summary Statistics Dashboard Overview -->
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4 mb-6">
            <div class="bg-white dark:bg-[#1f2937] border border-slate-300 dark:border-slate-700/70 rounded-xl p-4 shadow-sm">
                <div class="flex justify-between items-center mb-2">
                    <div class="w-9 h-9 bg-sky-100 dark:bg-sky-500/20 rounded-lg flex items-center justify-center text-sky-600 dark:text-sky-400 text-base">
                        <i class="fas fa-hospital-user"></i>
                    </div>
                </div>
                <div class="text-2xl font-bold text-slate-900 dark:text-white mb-1 font-mono"><?= number_format($total_patients_count); ?></div>
                <div class="text-slate-400 dark:text-slate-400 text-[10px] font-bold uppercase tracking-wider">Total Registry</div>
            </div>

            <div class="bg-white dark:bg-[#1f2937] border border-slate-300 dark:border-slate-700/70 rounded-xl p-4 shadow-sm">
                <div class="flex justify-between items-center mb-2">
                    <div class="w-9 h-9 bg-emerald-50 dark:bg-emerald-500/10 rounded-lg flex items-center justify-center text-emerald-600 dark:text-emerald-400 text-base">
                        <i class="fas fa-user-shield"></i>
                    </div>
                </div>
                <div class="text-2xl font-bold text-slate-900 dark:text-white mb-1 font-mono"><?= number_format($military_count); ?></div>
                <div class="text-slate-400 dark:text-slate-400 text-[10px] font-bold uppercase tracking-wider">Military Troops</div>
            </div>

            <div class="bg-white dark:bg-[#1f2937] border border-slate-300 dark:border-slate-700/70 rounded-xl p-4 shadow-sm">
                <div class="flex justify-between items-center mb-2">
                    <div class="w-9 h-9 bg-sky-50 dark:bg-sky-500/10 rounded-lg flex items-center justify-center text-sky-600 dark:text-sky-400 text-base">
                        <i class="fas fa-users"></i>
                    </div>
                </div>
                <div class="text-2xl font-bold text-slate-900 dark:text-white mb-1 font-mono"><?= number_format($dependent_count); ?></div>
                <div class="text-slate-400 dark:text-slate-400 text-[10px] font-bold uppercase tracking-wider">Dependents</div>
            </div>

            <div class="bg-white dark:bg-[#1f2937] border border-slate-300 dark:border-slate-700/70 rounded-xl p-4 shadow-sm">
                <div class="flex justify-between items-center mb-2">
                    <div class="w-9 h-9 bg-indigo-50 dark:bg-indigo-500/10 rounded-lg flex items-center justify-center text-indigo-600 dark:text-indigo-400 text-base">
                        <i class="fas fa-wheelchair"></i>
                    </div>
                </div>
                <div class="text-2xl font-bold text-slate-900 dark:text-white mb-1 font-mono"><?= number_format($pwd_count); ?></div>
                <div class="text-slate-400 dark:text-slate-400 text-[10px] font-bold uppercase tracking-wider">PWD Profiles</div>
            </div>

            <div class="bg-white dark:bg-[#1f2937] border border-slate-300 dark:border-slate-700/70 rounded-xl p-4 shadow-sm">
                <div class="flex justify-between items-center mb-2">
                    <div class="w-9 h-9 bg-amber-50 dark:bg-amber-500/10 rounded-lg flex items-center justify-center text-amber-600 dark:text-amber-400 text-base">
                        <i class="fas fa-blind"></i>
                    </div>
                </div>
                <div class="text-2xl font-bold text-slate-900 dark:text-white mb-1 font-mono"><?= number_format($senior_count); ?></div>
                <div class="text-slate-400 dark:text-slate-400 text-[10px] font-bold uppercase tracking-wider">Senior Citizens</div>
            </div>

            <div class="bg-white dark:bg-[#1f2937] border border-slate-300 dark:border-slate-700/70 rounded-xl p-4 shadow-sm col-span-2 sm:col-span-1">
                <div class="flex justify-between items-center mb-2">
                    <div class="w-9 h-9 bg-rose-50 dark:bg-rose-500/10 rounded-lg flex items-center justify-center text-rose-600 dark:text-rose-400 text-base">
                        <i class="fas fa-baby-carriage"></i>
                    </div>
                </div>
                <div class="text-2xl font-bold text-slate-900 dark:text-white mb-1 font-mono"><?= number_format($pregnant_count); ?></div>
                <div class="text-slate-400 dark:text-slate-400 text-[10px] font-bold uppercase tracking-wider">Maternal Profiles</div>
            </div>
        </div>

        <!-- Filter Controls Block Component -->
        <div class="bg-white dark:bg-[#1f2937] p-4 rounded-xl border border-slate-300 dark:border-slate-700/70 shadow-sm mb-6">
            <form method="GET" action="patients.php" class="flex flex-col lg:flex-row gap-3 items-center justify-between">
                <div class="relative w-full lg:max-w-xl flex-1">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-slate-400">
                        <i class="fas fa-search text-sm"></i>
                    </span>
                    <input type="text" name="search" value="<?= htmlspecialchars($search); ?>" placeholder="Search parameters by Name, Medical Record Number (MRN), or Phone..." class="w-full pl-9 pr-4 py-2 bg-slate-50 dark:bg-[#111827] border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white placeholder-slate-400 rounded-xl focus:outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500 text-xs transition-all">
                </div>
                
                <div class="flex flex-col sm:flex-row items-center gap-3 w-full lg:w-auto justify-end">
                    <select name="type" class="w-full sm:w-48 px-3 py-2 bg-slate-50 dark:bg-[#111827] border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white rounded-xl focus:outline-none focus:border-sky-500 text-xs">
                        <option value="">All Classifications</option>
                        <option value="Military Personnel" <?= $type === 'Military Personnel' ? 'selected' : ''; ?>>Military Personnel</option>
                        <option value="Dependent" <?= $type === 'Dependent' ? 'selected' : ''; ?>>Dependent</option>
                    </select>
                    
                    <button type="submit" class="w-full sm:w-auto bg-sky-50 dark:bg-sky-500/10 border border-sky-300 dark:border-sky-500/20 text-sky-600 dark:text-sky-400 hover:bg-sky-100 dark:hover:bg-sky-500/20 font-bold px-5 py-2 rounded-xl text-[11px] uppercase tracking-wider transition-all shrink-0">
                        Execute Query
                    </button>
                    
                    <?php if(!empty($search) || !empty($type)): ?>
                        <a href="patients.php" class="text-[11px] uppercase font-bold tracking-widest text-rose-500 hover:text-rose-600 transition-colors shrink-0 px-2 py-1">Reset</a>
                    <?php endif; ?>
                    
                    <button type="button" onclick="openPatientModal()" class="w-full sm:w-auto inline-flex items-center justify-center px-4 py-2 bg-sky-600 dark:bg-sky-500 text-white font-bold text-[11px] uppercase tracking-wider rounded-xl hover:bg-sky-700 dark:hover:bg-sky-600 transition-all shadow-sm shrink-0">
                        <i class="fas fa-user-plus mr-1.5 text-xs"></i> Register Patient
                    </button>
                </div>
            </form>
        </div>

        <!-- Directory Registry Records Table Ledger -->
        <section class="bg-white dark:bg-[#1f2937] border border-slate-300 dark:border-slate-700/70 rounded-xl shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full border-collapse text-left">
                    <thead>
                        <tr class="bg-slate-50 dark:bg-slate-800/50 border-b border-slate-300 dark:border-slate-700/80 text-slate-400 dark:text-slate-400 text-[10px] font-bold uppercase tracking-wider">
                            <th class="py-3 px-4">Medical Record Number (MRN)</th>
                            <th class="py-3 px-4">Patient Name</th>
                            <th class="py-3 px-4">Classification Scope</th>
                            <th class="py-3 px-4 text-center">Priority Scope</th>
                            <th class="py-3 px-4">Contact Details</th>
                            <th class="py-3 px-4 text-center">Historical Logs</th>
                            <th class="py-3 px-4 text-center">System Configuration Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-700/60 text-xs font-medium">
                        <?php if (empty($patients)): ?>
                            <tr>
                                <td colspan="7" class="py-8 text-center text-slate-400 font-bold uppercase tracking-wider">No active patient files recorded match parameters.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($patients as $p): ?>
                                <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-800/30 transition-colors">
                                    <td class="py-3 px-4 font-mono font-bold text-xs text-sky-600 dark:text-sky-400 select-all"><?= htmlspecialchars($p['mrn']); ?></td>
                                    <td class="py-3 px-4 text-slate-900 dark:text-white font-semibold">
                                        <?= htmlspecialchars($p['last_name'] . ', ' . $p['first_name'] . ' ' . ($p['middle_name'] ?? '')); ?>
                                    </td>
                                    <td class="py-3 px-4">
                                        <span class="px-2 py-0.5 rounded-full text-[9px] font-bold tracking-wide <?= $p['patient_type'] === 'Military Personnel' ? 'bg-emerald-50 dark:bg-emerald-500/10 text-emerald-600 dark:text-emerald-400' : 'bg-sky-50 dark:bg-sky-500/10 text-sky-600 dark:text-sky-400'; ?>">
                                            <?= htmlspecialchars($p['patient_type']); ?>
                                        </span>
                                    </td>
                                    <td class="py-3 px-4 text-center">
                                        <div class="flex gap-1.5 justify-center">
                                            <?php if($p['is_pwd']): ?><span class="px-1.5 py-0.5 bg-indigo-50 dark:bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 text-[9px] font-bold uppercase rounded tracking-wide">PWD</span><?php endif; ?>
                                            <?php if($p['is_senior']): ?><span class="px-1.5 py-0.5 bg-amber-50 dark:bg-amber-500/10 text-amber-600 dark:text-amber-400 text-[9px] font-bold uppercase rounded tracking-wide">Senior</span><?php endif; ?>
                                            <?php if($p['is_pregnant']): ?><span class="px-1.5 py-0.5 bg-rose-50 dark:bg-rose-500/10 text-rose-600 dark:text-rose-400 text-[9px] font-bold uppercase rounded tracking-wide">Maternal</span><?php endif; ?>
                                            <?php if(!$p['is_pwd'] && !$p['is_senior'] && !$p['is_pregnant']): ?>
                                                <span class="text-slate-400 dark:text-slate-500 text-[10px] font-normal italic">—</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="py-3 px-4 text-slate-500 dark:text-slate-400 font-medium">
                                        <div class="text-slate-700 dark:text-slate-200"><?= htmlspecialchars($p['contact_number'] ?: 'N/A'); ?></div>
                                        <div class="text-[10px] text-slate-400 font-normal truncate max-w-[200px] mt-0.5"><?= htmlspecialchars($p['address'] ?: 'No address specified'); ?></div>
                                    </td>
                                    <td class="py-3 px-4 text-center font-mono font-bold text-slate-700 dark:text-slate-300"><?= number_format($p['total_visits']); ?></td>
                                    <td class="py-3 px-4 text-center whitespace-nowrap">
                                        <div class="flex items-center justify-center gap-1.5">
                                            <a href="patient-results.php?id=<?= $p['id']; ?>" class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-sky-50 dark:bg-sky-500/10 text-sky-600 dark:text-sky-400 hover:bg-sky-600 dark:hover:bg-sky-500 hover:text-white dark:hover:text-white border border-sky-200 dark:border-sky-700/60 transition-colors" title="View Patient Results"><i class="fas fa-file-waveform text-xs"></i></a>
                                            <a href="patient-view.php?id=<?= $p['id']; ?>" class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400 hover:text-sky-500 dark:hover:text-sky-400 border border-slate-200 dark:border-slate-700/60 transition-colors" title="View Patient File"><i class="fas fa-eye text-xs"></i></a>
                                            <a href="patient-edit.php?id=<?= $p['id']; ?>" class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400 hover:text-amber-500 dark:hover:text-amber-400 border border-slate-200 dark:border-slate-700/60 transition-colors" title="Edit Demographics"><i class="fas fa-edit text-xs"></i></a>
                                            <button onclick="triggerPurgeRecord(<?= $p['id']; ?>, '<?= htmlspecialchars(addslashes($p['first_name'] . ' ' . $p['last_name'])); ?>')" class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-400 hover:text-rose-500 border border-slate-200 dark:border-slate-700/60 transition-colors" title="Purge Record Ledger"><i class="fas fa-trash-alt text-xs"></i></button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
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

        const profileMenuBtn = document.getElementById('profileMenuBtn');
        const profileDropdown = document.getElementById('profileDropdown');
        if (profileMenuBtn && profileDropdown) {
            profileMenuBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                profileDropdown.classList.toggle('hidden');
            });
            document.addEventListener('click', () => profileDropdown.classList.add('hidden'));
        }

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

        function triggerPurgeRecord(id, label) {
            if (confirm(`CRITICAL SYSTEM CONFIRMATION:\nAre you absolutely certain you want to purge the account file reference for "${label}"?\n\nThis execution script can fail if outstanding queue operations rely on this file.`)) {
                window.location.href = `patients.php?delete=${id}`;
            }
        }

        setTimeout(() => {
            document.querySelectorAll('.alert').forEach(alert => {
                alert.style.transition = 'all 0.4s ease-in-out';
                alert.style.opacity = '0';
                alert.style.transform = 'translateY(-10px)';
                setTimeout(() => alert.remove(), 400);
            });
        }, 5000);

        const INACTIVITY_TIMEOUT = 30 * 60 * 1000;
        let inactivityTimer;
        function resetInactivityTimer() {
            if (inactivityTimer) clearTimeout(inactivityTimer);
            inactivityTimer = setTimeout(function() {
                window.location.href = '../logout.php';
            }, INACTIVITY_TIMEOUT);
        }
        const events = ['mousedown', 'mousemove', 'keypress', 'scroll', 'click'];
        events.forEach(event => document.addEventListener(event, resetInactivityTimer, false));
        resetInactivityTimer();

        const themeToggleBtn = document.getElementById('themeToggleBtn');
        const themeToggleIcon = document.getElementById('themeToggleIcon');
        
        // Initial setup on page load
        if (localStorage.getItem('theme') === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark'); 
            if(themeToggleIcon) {
                themeToggleIcon.className = 'fas fa-sun text-base text-amber-400';
            }
        } else {
            if(themeToggleIcon) {
                themeToggleIcon.className = 'fas fa-moon text-base text-slate-500';
            }
        }
        
        // Click Event Listener
        if(themeToggleBtn) {
            themeToggleBtn.addEventListener('click', () => {
                if (document.documentElement.classList.contains('dark')) {
                    document.documentElement.classList.remove('dark'); 
                    localStorage.setItem('theme', 'light'); 
                    if(themeToggleIcon) {
                        themeToggleIcon.className = 'fas fa-moon text-base text-slate-500';
                    }
                } else {
                    document.documentElement.classList.add('dark'); 
                    localStorage.setItem('theme', 'dark'); 
                    if(themeToggleIcon) {
                        themeToggleIcon.className = 'fas fa-sun text-base text-amber-400';
                    }
                }
            });
        }

        function openPatientModal() {
            window.location.href = '../staff/registration.php';
        }
    </script>
</body>
</html>