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

// Catch redirection messages from the registration script
if (isset($_GET['success']) && $_GET['success'] == 1) {
    $message = "New patient profile successfully registered to system master ledger.";
}

// --- DATA INTAKE LOGIC: SEARCH FILTER ROUTINES ---
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$type = isset($_GET['type']) ? trim($_GET['type']) : '';

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

    <aside id="sidebar" class="fixed top-0 left-0 h-screen bg-white dark:bg-[#1f2937] border-r border-slate-300/90 dark:border-slate-700/80 shadow-xl md:shadow-none z-[1000] flex flex-col justify-between overflow-x-hidden transition-all duration-300 ease-in-out group/sidebar -translate-x-full md:translate-x-0 w-[270px] md:w-[80px] md:hover:w-[270px]">
        <div>
            <div class="p-4 border-b border-slate-300/90 dark:border-slate-700/60 mb-6 flex flex-col items-center justify-center min-h-[150px]">
                <div class="hidden md:flex md:group-hover/sidebar:hidden flex-col items-center justify-center font-extrabold text-xl tracking-wider text-sky-600 dark:text-sky-400 leading-tight select-none">
                    <span>C</span><span>E</span><span>S</span><span>H</span>
                </div>
                <div class="flex md:hidden md:group-hover/sidebar:flex flex-col items-center animate-[fadeIn_0.2s_ease-in-out]">
                    <img src="../assets/images/logo.png" alt="Logo" class="max-w-[110px] h-auto rounded mb-3 opacity-90 transition-all duration-200 dark:brightness-110 dark:contrast-125 dark:drop-shadow-[0_0_8px_rgba(56,189,248,0.3)]" onerror="this.style.display='none'">
                    <h2 class="text-slate-800 dark:text-slate-100 text-sm font-extrabold tracking-tight text-center whitespace-nowrap">4ID Station Hospital</h2>
                    <p class="text-slate-400 dark:text-slate-400 text-[0.65rem] font-bold uppercase tracking-widest text-center whitespace-nowrap mt-0.5">Camp Evangelista</p>
                </div>
            </div>
            
            <nav class="px-3 md:group-hover/sidebar:px-4 transition-all duration-200">
                <ul class="list-none p-0 space-y-1">
                    <li>
                        <a href="dashboard.php" class="flex items-center rounded-xl font-medium transition-all duration-150 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700/50 p-3 justify-center md:justify-start gap-0 md:group-hover/sidebar:gap-3 border-l-4 border-transparent group/link">
                            <div class="w-6 h-6 flex items-center justify-center shrink-0">
                                <i class="fas fa-tachometer-alt text-base text-slate-400 group-hover/link:text-sky-500 transition-colors"></i>
                            </div>
                            <span class="opacity-100 md:opacity-0 md:group-hover/sidebar:opacity-100 text-[0.85rem] tracking-wide whitespace-nowrap transition-opacity duration-200 origin-left">Dashboard</span>
                        </a>
                    </li>
                    <li>
                        <a href="patients.php" class="flex items-center rounded-xl font-semibold transition-all duration-150 bg-sky-50 dark:bg-sky-500/10 text-sky-600 dark:text-sky-400 border-l-4 border-sky-500 p-3 justify-center md:justify-start gap-0 md:group-hover/sidebar:gap-3">
                            <div class="w-6 h-6 flex items-center justify-center shrink-0">
                                <i class="fas fa-users text-base"></i>
                            </div>
                            <span class="opacity-100 md:opacity-0 md:group-hover/sidebar:opacity-100 text-[0.85rem] tracking-wide whitespace-nowrap transition-opacity duration-200 origin-left">Patients</span>
                        </a>
                    </li>
                    <li>
                        <a href="queue-monitor.php" class="flex items-center rounded-xl font-medium transition-all duration-150 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700/50 p-3 justify-center md:justify-start gap-0 md:group-hover/sidebar:gap-3 border-l-4 border-transparent group/link">
                            <div class="w-6 h-6 flex items-center justify-center shrink-0">
                                <i class="fas fa-chart-line text-base text-slate-400 group-hover/link:text-sky-500 transition-colors"></i>
                            </div>
                            <span class="opacity-100 md:opacity-0 md:group-hover/sidebar:opacity-100 text-[0.85rem] tracking-wide whitespace-nowrap transition-opacity duration-200 origin-left">Queue Monitor</span>
                        </a>
                    </li>
                    <li>
                        <a href="clinic-congestion.php" class="flex items-center rounded-xl font-medium transition-all duration-150 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700/50 p-3 justify-center md:justify-start gap-0 md:group-hover/sidebar:gap-3 border-l-4 border-transparent group/link">
                            <div class="w-6 h-6 flex items-center justify-center shrink-0">
                                <i class="fas fa-chart-pie text-base text-slate-400 group-hover/link:text-sky-500 transition-colors"></i>
                            </div>
                            <span class="opacity-100 md:opacity-0 md:group-hover/sidebar:opacity-100 text-[0.85rem] tracking-wide whitespace-nowrap transition-opacity duration-200 origin-left">Clinic Congestion</span>
                        </a>
                    </li>
                    <li>
                        <a href="reports.php" class="flex items-center rounded-xl font-medium transition-all duration-150 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700/50 p-3 justify-center md:justify-start gap-0 md:group-hover/sidebar:gap-3 border-l-4 border-transparent group/link">
                            <div class="w-6 h-6 flex items-center justify-center shrink-0">
                                <i class="fas fa-chart-bar text-base text-slate-400 group-hover/link:text-sky-500 transition-colors"></i>
                            </div>
                            <span class="opacity-100 md:opacity-0 md:group-hover/sidebar:opacity-100 text-[0.85rem] tracking-wide whitespace-nowrap transition-opacity duration-200 origin-left">Reports</span>
                        </a>
                    </li>
                    <li>
                        <a href="users.php" class="flex items-center rounded-xl font-medium transition-all duration-150 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700/50 p-3 justify-center md:justify-start gap-0 md:group-hover/sidebar:gap-3 border-l-4 border-transparent group/link">
                            <div class="w-6 h-6 flex items-center justify-center shrink-0">
                                <i class="fas fa-users-cog text-base text-slate-400 group-hover/link:text-sky-500 transition-colors"></i>
                            </div>
                            <span class="opacity-100 md:opacity-0 md:group-hover/sidebar:opacity-100 text-[0.85rem] tracking-wide whitespace-nowrap transition-opacity duration-200 origin-left">User Management</span>
                        </a>
                    </li>
                    <li>
                        <a href="login-monitor.php" class="flex items-center rounded-xl font-medium transition-all duration-150 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700/50 p-3 justify-center md:justify-start gap-0 md:group-hover/sidebar:gap-3 border-l-4 border-transparent group/link">
                            <div class="w-6 h-6 flex items-center justify-center shrink-0">
                                <i class="fas fa-history text-base text-slate-400 group-hover/link:text-sky-500 transition-colors"></i>
                            </div>
                            <span class="opacity-100 md:opacity-0 md:group-hover/sidebar:opacity-100 text-[0.85rem] tracking-wide whitespace-nowrap transition-opacity duration-200 origin-left">Login Monitor</span>
                        </a>
                    </li>
                </ul>
            </nav>
        </div>
    </aside>

    <main class="min-h-screen ml-0 md:ml-[80px] p-5 md:p-8 transition-all duration-300">
        
        <header class="flex flex-col sm:flex-row justify-between sm:items-center mb-8 pb-5 border-b border-slate-300/90 dark:border-slate-700/80 gap-4">
            <div class="flex items-center gap-4">
                <button id="mobileMenuBtn" class="md:hidden p-2 text-slate-600 dark:text-slate-300 bg-white dark:bg-[#1f2937] border border-slate-300 rounded-xl">
                    <i class="fas fa-bars text-xl"></i>
                </button>
                <div>
                    <h1 class="text-slate-900 dark:text-white text-2xl md:text-3xl font-extrabold tracking-tight mb-0.5">Patients Directory</h1>
                    <p class="text-slate-500 dark:text-slate-400 text-xs md:text-sm font-medium">Authorized clinical demographic registers and medical reference logs</p>
                </div>
            </div>
            
            <div class="flex items-center gap-3 md:gap-5 relative">
                <div class="text-right text-xs hidden sm:block">
                    <div class="text-slate-700 dark:text-slate-300 font-semibold" id="currentDate"></div>
                    <div class="text-sky-600 dark:text-sky-400 font-bold font-mono text-sm mt-0.5" id="currentTime"></div>
                </div>

                <button id="themeToggleBtn" class="w-10 h-10 flex items-center justify-center bg-white dark:bg-[#1f2937] text-slate-500 dark:text-slate-400 border border-slate-300 dark:border-slate-700 rounded-xl hover:text-sky-500 transition-all shadow-sm" title="Toggle Visual Mode">
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

        <?php if (!empty($message)): ?>
            <div class="alert p-4 mb-6 bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-300 dark:border-emerald-500/20 text-emerald-600 dark:text-emerald-400 rounded-2xl flex items-center justify-between shadow-sm">
                <div class="flex items-center"><i class="fas fa-check-circle mr-3 text-base"></i> <span class="text-xs font-bold uppercase tracking-wide"><?= htmlspecialchars($message) ?></span></div>
                <button onclick="this.parentElement.remove()" class="text-slate-400 hover:text-emerald-500"><i class="fas fa-times"></i></button>
            </div>
        <?php endif; ?>
        <?php if (!empty($error)): ?>
            <div class="alert p-4 mb-6 bg-rose-50 dark:bg-rose-500/10 border border-rose-300 dark:border-rose-500/20 text-rose-600 dark:text-rose-400 rounded-2xl flex items-center justify-between shadow-sm">
                <div class="flex items-center"><i class="fas fa-exclamation-triangle mr-3 text-base"></i> <span class="text-xs font-bold uppercase tracking-wide"><?= htmlspecialchars($error) ?></span></div>
                <button onclick="this.parentElement.remove()" class="text-slate-400 hover:text-rose-500"><i class="fas fa-times"></i></button>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-2 lg:grid-cols-5 gap-6 mb-8">
            <div class="bg-white dark:bg-[#1f2937] border border-slate-300 dark:border-slate-700/70 rounded-2xl p-5 shadow-sm hover:shadow-md transition-all">
                <div class="flex justify-between items-center mb-4">
                    <div class="w-11 h-11 bg-emerald-50 dark:bg-emerald-500/10 rounded-xl flex items-center justify-center text-emerald-600 dark:text-emerald-400 text-lg">
                        <i class="fas fa-user-shield"></i>
                    </div>
                </div>
                <div class="text-3xl font-bold text-slate-900 dark:text-white mb-1 font-mono"><?= number_format($military_count); ?></div>
                <div class="text-slate-400 dark:text-slate-400 text-[0.7rem] font-bold uppercase tracking-wider">Military Troops</div>
            </div>

            <div class="bg-white dark:bg-[#1f2937] border border-slate-300 dark:border-slate-700/70 rounded-2xl p-5 shadow-sm hover:shadow-md transition-all">
                <div class="flex justify-between items-center mb-4">
                    <div class="w-11 h-11 bg-sky-50 dark:bg-sky-500/10 rounded-xl flex items-center justify-center text-sky-600 dark:text-sky-400 text-lg">
                        <i class="fas fa-users"></i>
                    </div>
                </div>
                <div class="text-3xl font-bold text-slate-900 dark:text-white mb-1 font-mono"><?= number_format($dependent_count); ?></div>
                <div class="text-slate-400 dark:text-slate-400 text-[0.7rem] font-bold uppercase tracking-wider">Dependents</div>
            </div>

            <div class="bg-white dark:bg-[#1f2937] border border-slate-300 dark:border-slate-700/70 rounded-2xl p-5 shadow-sm hover:shadow-md transition-all">
                <div class="flex justify-between items-center mb-4">
                    <div class="w-11 h-11 bg-indigo-50 dark:bg-indigo-500/10 rounded-xl flex items-center justify-center text-indigo-600 dark:text-indigo-400 text-lg">
                        <i class="fas fa-wheelchair"></i>
                    </div>
                </div>
                <div class="text-3xl font-bold text-slate-900 dark:text-white mb-1 font-mono"><?= number_format($pwd_count); ?></div>
                <div class="text-slate-400 dark:text-slate-400 text-[0.7rem] font-bold uppercase tracking-wider">PWD Profiles</div>
            </div>

            <div class="bg-white dark:bg-[#1f2937] border border-slate-300 dark:border-slate-700/70 rounded-2xl p-5 shadow-sm hover:shadow-md transition-all">
                <div class="flex justify-between items-center mb-4">
                    <div class="w-11 h-11 bg-amber-50 dark:bg-amber-500/10 rounded-xl flex items-center justify-center text-amber-600 dark:text-amber-400 text-lg">
                        <i class="fas fa-blind"></i>
                    </div>
                </div>
                <div class="text-3xl font-bold text-slate-900 dark:text-white mb-1 font-mono"><?= number_format($senior_count); ?></div>
                <div class="text-slate-400 dark:text-slate-400 text-[0.7rem] font-bold uppercase tracking-wider">Senior Citizens</div>
            </div>

            <div class="bg-white dark:bg-[#1f2937] border border-slate-300 dark:border-slate-700/70 rounded-2xl p-5 shadow-sm hover:shadow-md transition-all col-span-2 lg:col-span-1">
                <div class="flex justify-between items-center mb-4">
                    <div class="w-11 h-11 bg-rose-50 dark:bg-rose-500/10 rounded-xl flex items-center justify-center text-rose-600 dark:text-rose-400 text-lg">
                        <i class="fas fa-baby-carriage"></i>
                    </div>
                </div>
                <div class="text-3xl font-bold text-slate-900 dark:text-white mb-1 font-mono"><?= number_format($pregnant_count); ?></div>
                <div class="text-slate-400 dark:text-slate-400 text-[0.7rem] font-bold uppercase tracking-wider">Maternal Profiles</div>
            </div>
        </div>

        <div class="bg-white dark:bg-[#1f2937] p-5 rounded-2xl border border-slate-300 dark:border-slate-700/70 shadow-sm mb-8">
            <form method="GET" action="patients.php" class="flex flex-col lg:flex-row gap-4 items-center justify-between">
                
                <div class="relative w-full lg:max-w-xl flex-1">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 pointer-events-none text-slate-400">
                        <i class="fas fa-search"></i>
                    </span>
                    <input type="text" name="search" value="<?= htmlspecialchars($search); ?>" 
                           placeholder="Search parameters by Name, Medical Record Number (MRN), or Phone..." 
                           class="w-full pl-10 pr-4 py-2.5 bg-slate-50 dark:bg-[#111827] border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white placeholder-slate-400 rounded-xl focus:outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500 text-sm transition-all">
                </div>

                <div class="flex flex-col sm:flex-row items-center gap-3 w-full lg:w-auto justify-end">
                    <select name="type" class="w-full sm:w-48 px-3 py-2.5 bg-slate-50 dark:bg-[#111827] border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white rounded-xl focus:outline-none focus:border-sky-500 text-sm">
                        <option value="">All Classifications</option>
                        <option value="Military Personnel" <?= $type === 'Military Personnel' ? 'selected' : ''; ?>>Military Personnel</option>
                        <option value="Dependent" <?= $type === 'Dependent' ? 'selected' : ''; ?>>Dependent</option>
                    </select>
                    
                    <button type="submit" class="w-full sm:w-auto bg-slate-100 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700 font-bold px-5 py-2.5 rounded-xl text-xs uppercase tracking-wider transition-all shrink-0">
                        Filter
                    </button>

                    <a href="register-patient.php" class="w-full sm:w-auto bg-sky-600 hover:bg-sky-700 dark:bg-sky-500 dark:hover:bg-sky-600 text-white font-bold text-xs uppercase tracking-wider py-2.5 px-5 rounded-xl transition-all shadow-sm flex items-center justify-center gap-2 shrink-0">
                        <i class="fas fa-user-plus"></i>
                        <span>Add Patient Record</span>
                    </a>
                    
                    <?php if(!empty($search) || !empty($type)): ?>
                        <a href="patients.php" class="text-xs uppercase font-bold tracking-widest text-rose-500 hover:text-rose-600 transition-colors shrink-0 px-2">Reset</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <section class="bg-white dark:bg-[#1f2937] border border-slate-300 dark:border-slate-700/70 rounded-2xl overflow-hidden shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse table-fixed min-w-[900px]">
                    <thead>
                        <tr class="bg-slate-50 dark:bg-slate-800/60 border-b border-slate-300 dark:border-slate-700/80 text-[0.65rem] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-400">
                            <th class="py-4 px-6 w-[28%]">Assigned MRN & Patient Profile Name</th>
                            <th class="py-4 px-6 w-[18%]">Classification Scope</th>
                            <th class="py-4 px-6 w-[18%]">Triage Condition Identifiers</th>
                            <th class="py-4 px-6 w-[20%]">Contact / Date of Birth Specs</th>
                            <th class="py-4 px-6 w-[16%] text-center">Operational Directives</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-300/80 dark:divide-slate-700/50 text-xs md:text-sm text-slate-700 dark:text-slate-200">
                        <?php if (count($patients) > 0): ?>
                            <?php foreach ($patients as $row): 
                                $lastName   = $row['last_name']   ?? '';
                                $firstName  = $row['first_name']  ?? '';
                                $middleName = $row['middle_name'] ?? '';
                                $mrnValue   = $row['mrn']         ?? 'N/A';
                                $displayName = trim($lastName . ', ' . $firstName . ' ' . $middleName);

                                $typeClass = "bg-slate-50 dark:bg-slate-800 text-slate-500 dark:text-slate-400 border border-slate-300 dark:border-slate-700";
                                if (isset($row['patient_type']) && $row['patient_type'] === 'Military Personnel') {
                                    $typeClass = "bg-emerald-50 dark:bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-300 dark:border-emerald-400/20";
                                } elseif (isset($row['patient_type']) && $row['patient_type'] === 'Dependent') {
                                    $typeClass = "bg-sky-50 dark:bg-sky-500/10 text-sky-600 dark:text-sky-400 border border-sky-300 dark:border-sky-400/20";
                                }
                            ?>
                                <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition-colors duration-150 group/row">
                                    <td class="py-4 px-6 overflow-hidden">
                                        <div class="font-semibold text-slate-900 dark:text-white group-hover/row:text-sky-600 dark:group-hover/row:text-sky-400 transition-colors truncate">
                                            <?= htmlspecialchars($displayName); ?>
                                        </div>
                                        <div class="text-[10px] font-mono font-bold text-sky-600 dark:text-sky-400 bg-sky-50 dark:bg-sky-500/5 px-2 py-0.5 rounded border border-sky-300 dark:border-sky-500/10 inline-block mt-1">
                                            MRN-<?= htmlspecialchars($mrnValue); ?>
                                        </div>
                                    </td>
                                    <td class="py-4 px-6 overflow-hidden">
                                        <span class="px-2.5 py-1 rounded-full text-[0.65rem] font-bold tracking-wide inline-block uppercase truncate max-w-full <?= $typeClass ?>">
                                            <?= htmlspecialchars($row['patient_type'] ?? 'Civilian Associated'); ?>
                                        </span>
                                    </td>
                                    <td class="py-4 px-6">
                                        <div class="flex flex-wrap gap-1.5 max-w-xs">
                                            <?php if(!empty($row['is_senior'])): ?>
                                                <span class="bg-amber-50 dark:bg-amber-500/10 text-amber-600 dark:text-amber-400 text-[9px] font-black uppercase tracking-wider px-2 py-0.5 rounded-full border border-amber-300 dark:border-amber-400/20">Senior</span>
                                            <?php endif; ?>
                                            <?php if(!empty($row['is_pwd'])): ?>
                                                <span class="bg-indigo-50 dark:bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 text-[9px] font-black uppercase tracking-wider px-2 py-0.5 rounded-full border border-indigo-300 dark:border-indigo-400/20">PWD</span>
                                            <?php endif; ?>
                                            <?php if(!empty($row['is_pregnant'])): ?>
                                                <span class="bg-rose-50 dark:bg-rose-500/10 text-rose-600 dark:text-rose-400 text-[9px] font-black uppercase tracking-wider px-2 py-0.5 rounded-full border border-rose-300 dark:border-rose-400/20">Pregnant</span>
                                            <?php endif; ?>
                                            <?php if(empty($row['is_senior']) && empty($row['is_pwd']) && empty($row['is_pregnant'])): ?>
                                                <span class="text-slate-400 dark:text-slate-600 text-xs font-mono">—</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="py-4 px-6 overflow-hidden">
                                        <div class="text-slate-900 dark:text-white font-medium truncate"><?= htmlspecialchars($row['contact_number'] ?? 'No Number Registered'); ?></div>
                                        <div class="text-xs text-slate-400 dark:text-slate-500 mt-0.5 font-mono truncate">
                                            DOB: <?= !empty($row['date_of_birth']) ? date('m/d/Y', strtotime($row['date_of_birth'])) : 'Unspecified'; ?>
                                            <span class="mx-1 text-slate-300 dark:text-slate-700">|</span>
                                            Visits: <?= intval($row['total_visits'] ?? 0); ?>
                                        </div>
                                    </td>
                                    <td class="py-4 px-6 text-center">
                                        <div class="flex items-center justify-center gap-1.5">
                                            <a href="patient-view.php?id=<?= $row['id']; ?>" class="w-9 h-9 flex items-center justify-center text-slate-500 dark:text-slate-400 hover:text-sky-600 dark:hover:text-sky-400 bg-slate-100/70 dark:bg-slate-800/80 hover:bg-sky-100 dark:hover:bg-sky-500/20 border border-slate-300 dark:border-slate-700/80 rounded-xl transition-all shadow-sm" title="Review Dossier Log">
                                                <i class="fas fa-folder-open text-base"></i>
                                            </a>
                                            <a href="patient-results.php?id=<?= $row['id']; ?>" class="w-9 h-9 flex items-center justify-center text-slate-500 dark:text-slate-400 hover:text-emerald-600 dark:hover:text-emerald-400 bg-slate-100/70 dark:bg-slate-800/80 hover:bg-emerald-100 dark:hover:bg-emerald-500/20 border border-slate-300 dark:border-slate-700/80 rounded-xl transition-all shadow-sm" title="Examine Lab Findings">
                                                <i class="fas fa-file-medical text-base"></i>
                                            </a>
                                            <a href="patient-edit.php?id=<?= $row['id']; ?>" class="w-9 h-9 flex items-center justify-center text-slate-500 dark:text-slate-400 hover:text-amber-600 dark:hover:text-amber-400 bg-slate-100/70 dark:bg-slate-800/80 hover:bg-amber-100 dark:hover:bg-amber-500/20 border border-slate-300 dark:border-slate-700/80 rounded-xl transition-all shadow-sm" title="Modify Demographic File">
                                                <i class="fas fa-edit text-base"></i>
                                            </a>
                                            <button onclick="triggerPurgeRecord(<?= $row['id']; ?>, '<?= htmlspecialchars(addslashes(($lastName).', '.($firstName))); ?>')" class="w-9 h-9 flex items-center justify-center text-slate-500 dark:text-slate-400 hover:text-rose-600 dark:hover:text-rose-400 bg-slate-100/70 dark:bg-slate-800/80 hover:bg-rose-100 dark:hover:bg-rose-500/20 border border-slate-300 dark:border-slate-700/80 rounded-xl transition-all shadow-sm" title="Purge Profile Reference Asset">
                                                <i class="fas fa-trash-alt text-base"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center py-16 text-slate-400">
                                    <i class="fas fa-inbox text-3xl mb-2.5 block text-slate-300 dark:text-slate-600"></i>
                                    <p class="text-xs font-bold text-slate-400">No patient files found matching criteria parameters.</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>

    <script>
        // System Theme Handler Logic Layer
        const themeToggleBtn = document.getElementById('themeToggleBtn');
        const themeToggleIcon = document.getElementById('themeToggleIcon');
        const htmlElement = document.documentElement;

        if (localStorage.getItem('theme') === 'dark' || (!localStorage.getItem('theme') && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            htmlElement.classList.add('dark');
            themeToggleIcon.className = 'fas fa-sun text-base text-amber-400';
        } else {
            htmlElement.classList.remove('dark');
            themeToggleIcon.className = 'fas fa-moon text-base';
        }

        themeToggleBtn.addEventListener('click', () => {
            if (htmlElement.classList.contains('dark')) {
                htmlElement.classList.remove('dark');
                themeToggleIcon.className = 'fas fa-moon text-base';
                localStorage.setItem('theme', 'light');
            } else {
                htmlElement.classList.add('dark');
                themeToggleIcon.className = 'fas fa-sun text-base text-amber-400';
                localStorage.setItem('theme', 'dark');
            }
        });

        // Top Right Account Profile Dropdown Logic Layer
        const profileMenuBtn = document.getElementById('profileMenuBtn');
        const profileDropdown = document.getElementById('profileDropdown');

        profileMenuBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            profileDropdown.classList.toggle('hidden');
        });

        document.addEventListener('click', (e) => {
            if (!profileDropdown.contains(e.target) && e.target !== profileMenuBtn) {
                profileDropdown.classList.add('hidden');
            }
        });

        // Calendar Metadata Timer Engines
        function updateDateTime() {
            const now = new Date();
            const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
            document.getElementById('currentDate').textContent = now.toLocaleDateString('en-US', options);
            document.getElementById('currentTime').textContent = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
        }
        updateDateTime();
        setInterval(updateDateTime, 1000);

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
    </script>
</body>
</html>