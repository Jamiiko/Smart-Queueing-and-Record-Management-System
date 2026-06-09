<?php
// staff/patient-queue.php - All Clinics Queue Overview
// Camp Evangelista Station Hospital

require_once dirname(__DIR__) . '/config/database.php';

session_start();

// ============================================
// AUTHENTICATION & ROLE-BASED ACCESS CONTROL
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
$sessionManager->logActivity('Viewed patient queue page');

// Get all clinics with queue counts
$query = "SELECT 
            c.*,
            COUNT(CASE WHEN q.status IN ('waiting', 'called', 'in-progress') 
                  AND DATE(q.registered_at) = CURDATE() THEN 1 END) as current_patients,
            COUNT(CASE WHEN q.status = 'waiting' 
                  AND DATE(q.registered_at) = CURDATE() THEN 1 END) as waiting_count,
            COUNT(CASE WHEN q.status = 'in-progress' 
                  AND DATE(q.registered_at) = CURDATE() THEN 1 END) as in_progress_count,
            COUNT(CASE WHEN q.status = 'completed' 
                  AND DATE(q.registered_at) = CURDATE() THEN 1 END) as completed_today,
            AVG(CASE WHEN q.completed_at IS NOT NULL AND DATE(q.registered_at) = CURDATE() 
                THEN TIMESTAMPDIFF(MINUTE, q.registered_at, q.completed_at) END) as avg_wait_time
          FROM clinics c
          LEFT JOIN queue_entries q ON c.id = q.clinic_id
          WHERE c.is_active = 1
          GROUP BY c.id
          ORDER BY c.name";

$clinics = $db->query($query)->fetchAll(PDO::FETCH_ASSOC);

$total_waiting = 0;
$total_active = 0;
$total_completed = 0;

foreach ($clinics as $c) {
    $total_waiting += isset($c['waiting_count']) ? (int)$c['waiting_count'] : 0;
    $total_active += isset($c['in_progress_count']) ? (int)$c['in_progress_count'] : 0;
    $total_completed += isset($c['completed_today']) ? (int)$c['completed_today'] : 0;
}
$total_patients = $total_waiting + $total_active + $total_completed;
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clinic Queues | Staff | Camp Evangelista Hospital</title>
    
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
                    <span>C</span><span>E</span><span>S</span><span>H</span>
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
                        <a href="../admin/patients.php" class="flex items-center rounded-xl font-medium transition-all duration-150 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700/50 p-3 justify-center md:justify-start gap-0 md:group-hover/sidebar:gap-3 border-l-4 border-transparent group/link">
                            <div class="w-6 h-6 flex items-center justify-center shrink-0"><i class="fas fa-arrow-left text-base text-slate-400 group-hover/link:text-sky-500 transition-colors"></i></div>
                            <span class="opacity-100 md:opacity-0 md:group-hover/sidebar:opacity-100 text-[0.85rem] tracking-wide whitespace-nowrap transition-opacity duration-200 origin-left">Patient Directory</span>
                        </a>
                    </li>
                    <?php endif; ?>
                    <li>
                        <a href="clinic-dashboard.php?clinic_id=<?php echo isset($_SESSION['clinic_id']) ? $_SESSION['clinic_id'] : 1; ?>" class="flex items-center rounded-xl font-medium transition-all duration-150 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700/50 p-3 justify-center md:justify-start gap-0 md:group-hover/sidebar:gap-3 border-l-4 border-transparent group/link">
                            <div class="w-6 h-6 flex items-center justify-center shrink-0"><i class="fas fa-desktop text-base text-slate-400 group-hover/link:text-sky-500 transition-colors"></i></div>
                            <span class="opacity-100 md:opacity-0 md:group-hover/sidebar:opacity-100 text-[0.85rem] tracking-wide whitespace-nowrap transition-opacity duration-200 origin-left">Dashboard</span>
                        </a>
                    </li>
                    <li>
                        <a href="registration.php" class="flex items-center rounded-xl font-medium transition-all duration-150 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700/50 p-3 justify-center md:justify-start gap-0 md:group-hover/sidebar:gap-3 border-l-4 border-transparent group/link">
                            <div class="w-6 h-6 flex items-center justify-center shrink-0"><i class="fas fa-user-plus text-base text-slate-400 group-hover/link:text-sky-500 transition-colors"></i></div>
                            <span class="opacity-100 md:opacity-0 md:group-hover/sidebar:opacity-100 text-[0.85rem] tracking-wide whitespace-nowrap transition-opacity duration-200 origin-left">Registration</span>
                        </a>
                    </li>
                    <li>
                        <a href="patient-queue.php" class="flex items-center rounded-xl font-semibold transition-all duration-150 bg-sky-50 dark:bg-sky-500/10 text-sky-600 dark:text-sky-400 border-l-4 border-sky-500 p-3 justify-center md:justify-start gap-0 md:group-hover/sidebar:gap-3">
                            <div class="w-6 h-6 flex items-center justify-center shrink-0"><i class="fas fa-list text-base"></i></div>
                            <span class="opacity-100 md:opacity-0 md:group-hover/sidebar:opacity-100 text-[0.85rem] tracking-wide whitespace-nowrap transition-opacity duration-200 origin-left">All Clinics Queue</span>
                        </a>
                    </li>
                    <li>
                        <a href="../patient-portal/track-queue.php" target="_blank" class="flex items-center rounded-xl font-medium transition-all duration-150 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700/50 p-3 justify-center md:justify-start gap-0 md:group-hover/sidebar:gap-3 border-l-4 border-transparent group/link">
                            <div class="w-6 h-6 flex items-center justify-center shrink-0"><i class="fas fa-search text-base text-slate-400 group-hover/link:text-sky-500 transition-colors"></i></div>
                            <span class="opacity-100 md:opacity-0 md:group-hover/sidebar:opacity-100 text-[0.85rem] tracking-wide whitespace-nowrap transition-opacity duration-200 origin-left">Patient Portal</span>
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
                    <p class="text-sm font-bold text-slate-900 dark:text-white truncate leading-tight whitespace-nowrap"><?php echo htmlspecialchars(isset($_SESSION['full_name']) ? $_SESSION['full_name'] : $_SESSION['username']); ?></p>
                    <p class="text-[11px] text-sky-600 dark:text-sky-400 font-medium capitalize mt-0.5 whitespace-nowrap"><?php echo htmlspecialchars($_SESSION['role']); ?></p>
                </div>
            </div>
        </div>
    </aside>

    <main class="min-h-screen ml-0 md:ml-[80px] p-6 md:p-8 transition-all duration-300">
        
        <header class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-8 pb-6 border-b border-slate-200 dark:border-slate-800">
            <div class="flex items-center gap-4">
                <button id="mobileMenuBtn" class="md:hidden w-11 h-11 flex items-center justify-center text-slate-600 dark:text-slate-300 bg-white dark:bg-[#1f2937] border border-slate-200 dark:border-slate-800 rounded-xl shadow-sm hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                    <i class="fas fa-bars text-lg"></i>
                </button>
                <div>
                    <h1 class="text-2xl md:text-3xl font-extrabold text-slate-900 dark:text-white tracking-tight">Clinic Queues Overview</h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Central monitor system matrix for all open station healthcare departments</p>
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
                            <a href="profile.php" class="flex items-center gap-3 w-full text-left px-3 py-2.5 text-sm font-semibold text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-xl transition-colors">
                                <i class="fas fa-id-badge text-slate-400"></i> My Profile
                            </a>
                            <a href="../logout.php" onclick="return confirm('Confirm Logout?')" class="flex items-center gap-3 w-full text-left px-3 py-2.5 text-sm font-semibold text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-500/10 rounded-xl transition-colors">
                                <i class="fas fa-sign-out-alt"></i> Logout Session
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="bg-white dark:bg-[#1f2937] border border-slate-200 dark:border-slate-800 rounded-2xl p-6 shadow-sm flex items-center gap-5 transition-transform hover:-translate-y-1 duration-200">
                <div class="w-14 h-14 rounded-2xl bg-sky-50 dark:bg-sky-500/10 flex items-center justify-center text-sky-600 dark:text-sky-400 text-2xl shrink-0"><i class="fas fa-users"></i></div>
                <div class="min-w-0">
                    <p class="text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1">Total Patients</p>
                    <p class="text-3xl font-black text-slate-900 dark:text-white font-mono leading-none"><?php echo $total_patients; ?></p>
                </div>
            </div>
            <div class="bg-white dark:bg-[#1f2937] border border-slate-200 dark:border-slate-800 rounded-2xl p-6 shadow-sm flex items-center gap-5 transition-transform hover:-translate-y-1 duration-200">
                <div class="w-14 h-14 rounded-2xl bg-amber-50 dark:bg-amber-500/10 flex items-center justify-center text-amber-500 text-2xl shrink-0"><i class="fas fa-clock"></i></div>
                <div class="min-w-0">
                    <p class="text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1">Waiting Call</p>
                    <p class="text-3xl font-black text-slate-900 dark:text-white font-mono leading-none"><?php echo $total_waiting; ?></p>
                </div>
            </div>
            <div class="bg-white dark:bg-[#1f2937] border border-slate-200 dark:border-slate-800 rounded-2xl p-6 shadow-sm flex items-center gap-5 transition-transform hover:-translate-y-1 duration-200">
                <div class="w-14 h-14 rounded-2xl bg-indigo-50 dark:bg-indigo-500/10 flex items-center justify-center text-indigo-500 text-2xl shrink-0"><i class="fas fa-play-circle"></i></div>
                <div class="min-w-0">
                    <p class="text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1">In Progress</p>
                    <p class="text-3xl font-black text-slate-900 dark:text-white font-mono leading-none"><?php echo $total_active; ?></p>
                </div>
            </div>
            <div class="bg-white dark:bg-[#1f2937] border border-slate-200 dark:border-slate-800 rounded-2xl p-6 shadow-sm flex items-center gap-5 transition-transform hover:-translate-y-1 duration-200">
                <div class="w-14 h-14 rounded-2xl bg-emerald-50 dark:bg-emerald-500/10 flex items-center justify-center text-emerald-500 text-2xl shrink-0"><i class="fas fa-check-circle"></i></div>
                <div class="min-w-0">
                    <p class="text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1">Completed</p>
                    <p class="text-3xl font-black text-slate-900 dark:text-white font-mono leading-none"><?php echo $total_completed; ?></p>
                </div>
            </div>
        </div>

        <h2 class="text-sm font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-5">Active Departments</h2>

        <?php if (empty($clinics)): ?>
            <div class="bg-white dark:bg-[#1f2937] border border-slate-200 dark:border-slate-800 rounded-2xl p-16 text-center text-slate-400 shadow-sm">
                <i class="fas fa-clinic-medical text-6xl mb-4 opacity-30 text-slate-500 dark:text-slate-400"></i>
                <p class="font-extrabold uppercase tracking-wider text-lg text-slate-600 dark:text-slate-300">No Active Clinics Linked</p>
                <p class="text-sm mt-2 opacity-75">Initialize a clinic configuration via the system settings.</p>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 mb-8">
                <?php foreach ($clinics as $clinic): 
                    $waiting = isset($clinic['waiting_count']) ? (int)$clinic['waiting_count'] : 0;
                    $in_progress = isset($clinic['in_progress_count']) ? (int)$clinic['in_progress_count'] : 0;
                    $completed = isset($clinic['completed_today']) ? (int)$clinic['completed_today'] : 0;
                    $capacity = isset($clinic['capacity_per_hour']) ? (int)$clinic['capacity_per_hour'] : 10;
                    
                    $current_load = $waiting + $in_progress;
                    $load_percentage = min(100, ($current_load / max($capacity, 1)) * 100);
                    
                    $bar_color = 'bg-emerald-500';
                    $text_color = 'text-emerald-600 dark:text-emerald-400';
                    if ($load_percentage >= 60) {
                        $bar_color = 'bg-rose-500';
                        $text_color = 'text-rose-600 dark:text-rose-400';
                    } elseif ($load_percentage >= 30) {
                        $bar_color = 'bg-amber-500';
                        $text_color = 'text-amber-600 dark:text-amber-400';
                    }
                    
                    $avg_wait = isset($clinic['avg_wait_time']) ? round($clinic['avg_wait_time']) : 0;
                    
                    $waiting_color = 'text-slate-800 dark:text-slate-200';
                    if ($waiting > 10) {
                        $waiting_color = 'text-rose-600 dark:text-rose-400';
                    } elseif ($waiting > 5) {
                        $waiting_color = 'text-amber-600 dark:text-amber-400';
                    }
                ?>
                <a href="clinic-dashboard.php?clinic_id=<?php echo $clinic['id']; ?>" class="flex flex-col bg-white dark:bg-[#1f2937] border border-slate-200 dark:border-slate-800 rounded-2xl shadow-sm hover:shadow-md hover:border-sky-300 dark:hover:border-sky-700 transition-all duration-300 overflow-hidden group hover:-translate-y-0.5 h-full">
                    <div class="p-4 border-b border-slate-100 dark:border-slate-800 flex justify-between items-start bg-slate-50/50 dark:bg-slate-800/30 min-h-[76px]">
                        <div class="pr-2 min-w-0">
                            <h3 class="font-extrabold text-slate-900 dark:text-white text-base tracking-tight group-hover:text-sky-600 dark:group-hover:text-sky-400 transition-colors truncate"><?php echo htmlspecialchars($clinic['name']); ?></h3>
                            <p class="text-slate-500 dark:text-slate-400 text-xs mt-0.5 truncate"><?php echo htmlspecialchars($clinic['description'] ?? 'Medical Clinic Unit'); ?></p>
                        </div>
                        <div class="w-9 h-9 shrink-0 rounded-xl bg-white dark:bg-slate-800 flex items-center justify-center text-slate-400 shadow-sm border border-slate-200 dark:border-slate-700 group-hover:bg-sky-50 dark:group-hover:bg-sky-500/10 group-hover:text-sky-500 transition-colors">
                            <i class="fas fa-hospital-user text-sm"></i>
                        </div>
                    </div>

                    <div class="grid grid-cols-3 divide-x divide-slate-100 dark:divide-slate-800 p-4 text-center bg-white dark:bg-[#1f2937]">
                        <div>
                            <div class="font-mono font-black text-2xl <?php echo $waiting_color; ?>"><?php echo $waiting; ?></div>
                            <div class="text-slate-500 dark:text-slate-400 text-[10px] font-bold uppercase tracking-wider mt-1">Waiting</div>
                        </div>
                        <div>
                            <div class="font-mono font-black text-2xl text-sky-600 dark:text-sky-400"><?php echo $in_progress; ?></div>
                            <div class="text-slate-500 dark:text-slate-400 text-[10px] font-bold uppercase tracking-wider mt-1">Active</div>
                        </div>
                        <div>
                            <div class="font-mono font-black text-2xl text-emerald-600 dark:text-emerald-400"><?php echo $completed; ?></div>
                            <div class="text-slate-500 dark:text-slate-400 text-[10px] font-bold uppercase tracking-wider mt-1">Cleared</div>
                        </div>
                    </div>

                    <div class="px-4 pb-4 mt-auto">
                        <div class="flex justify-between items-center text-[10px] font-bold mb-1.5 uppercase tracking-wide">
                            <span class="text-slate-500 dark:text-slate-400">Load Capacity</span>
                            <span class="<?php echo $text_color; ?>"><?php echo round($load_percentage); ?>%</span>
                        </div>
                        <div class="w-full h-1.5 bg-slate-100 dark:bg-slate-800 rounded-full overflow-hidden mb-3">
                            <div class="h-full <?php echo $bar_color; ?> rounded-full transition-all duration-700 ease-out" style="width: <?php echo $load_percentage; ?>%"></div>
                        </div>
                        <div class="pt-3 border-t border-slate-100 dark:border-slate-800 flex justify-between items-center text-[10px] font-semibold text-slate-500 dark:text-slate-400">
                            <div class="flex items-center gap-2.5">
                                <span class="flex items-center gap-1"><i class="fas fa-hourglass-half text-sky-500"></i> Est: <span class="font-mono text-slate-700 dark:text-slate-300"><?php echo $waiting * 8; ?>m</span></span>
                                <span class="flex items-center gap-1"><i class="fas fa-chart-line text-emerald-500"></i> Avg: <span class="font-mono text-slate-700 dark:text-slate-300"><?php echo $avg_wait; ?>m</span></span>
                            </div>
                            <div class="text-sky-600 dark:text-sky-400 flex items-center gap-0.5 group-hover:translate-x-0.5 transition-transform">
                                View <i class="fas fa-arrow-right text-[9px]"></i>
                            </div>
                        </div>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="bg-white dark:bg-[#1f2937] border border-slate-200 dark:border-slate-800 rounded-2xl shadow-sm overflow-hidden mb-8">
            <div class="p-4 border-b border-slate-100 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-800/30 flex items-center justify-between">
                <h3 class="text-xs font-extrabold uppercase text-slate-900 dark:text-white tracking-wider flex items-center gap-3">
                    <div class="w-7 h-7 rounded-lg bg-sky-500/10 flex items-center justify-center text-sky-500 text-xs"><i class="fas fa-chart-pie"></i></div>
                    Hospital Operations Summary
                </h3>
            </div>
            <div class="p-6 flex flex-col md:flex-row md:items-center justify-between gap-6">
                <div class="grid grid-cols-3 gap-6 text-center md:text-left divide-x divide-slate-100 dark:divide-slate-800 w-full md:w-auto">
                    <div class="px-4">
                        <div class="font-mono font-black text-3xl text-slate-900 dark:text-white leading-none"><?php echo $total_patients; ?></div>
                        <div class="text-slate-500 dark:text-slate-400 text-[10px] font-bold uppercase tracking-wider mt-2">Total Processed</div>
                    </div>
                    <div class="px-4">
                        <div class="font-mono font-black text-3xl text-amber-500 leading-none"><?php echo $total_waiting; ?></div>
                        <div class="text-slate-500 dark:text-slate-400 text-[10px] font-bold uppercase tracking-wider mt-2">Total Pending</div>
                    </div>
                    <div class="px-4">
                        <div class="font-mono font-black text-3xl text-emerald-500 leading-none"><?php echo $total_completed; ?></div>
                        <div class="text-slate-500 dark:text-slate-400 text-[10px] font-bold uppercase tracking-wider mt-2">Success Cleared</div>
                    </div>
                </div>
                
                <div class="w-full md:w-80 shrink-0 bg-slate-50 dark:bg-[#111827] rounded-xl p-4 border border-slate-100 dark:border-slate-800">
                    <div class="flex justify-between items-center text-[10px] font-bold text-slate-600 dark:text-slate-300 mb-2 uppercase tracking-wider">
                        <span>Hospital Clearance Rate</span>
                        <span class="text-sky-600 dark:text-sky-400 font-mono"><?php echo $total_patients > 0 ? round(($total_completed / $total_patients) * 100) : 0; ?>%</span>
                    </div>
                    <div class="w-full h-2 bg-slate-200 dark:bg-slate-700/60 rounded-full overflow-hidden">
                        <div class="h-full bg-sky-500 rounded-full transition-all duration-1000 ease-out" style="width: <?php echo $total_patients > 0 ? ($total_completed / $total_patients) * 100 : 0; ?>%"></div>
                    </div>
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
            document.addEventListener('click', (e) => {
                if (!sidebar.classList.contains('-translate-x-full') && !sidebar.contains(e.target) && !mobileMenuBtn.contains(e.target)) {
                    sidebar.classList.add('-translate-x-full');
                }
            });
        }

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
        if (localStorage.getItem('theme') === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark'); 
            if(themeToggleIcon) themeToggleIcon.className = 'fas fa-sun text-lg text-amber-400';
        } else {
            if(themeToggleIcon) themeToggleIcon.className = 'fas fa-moon text-lg text-slate-500';
        }
        if(themeToggleBtn) {
            themeToggleBtn.addEventListener('click', () => {
                if (document.documentElement.classList.contains('dark')) {
                    document.documentElement.classList.remove('dark'); 
                    localStorage.setItem('theme', 'light'); 
                    if(themeToggleIcon) themeToggleIcon.className = 'fas fa-moon text-lg text-slate-500';
                } else {
                    document.documentElement.classList.add('dark'); 
                    localStorage.setItem('theme', 'dark'); 
                    if(themeToggleIcon) themeToggleIcon.className = 'fas fa-sun text-lg text-amber-400';
                }
            });
        }
    </script>
</body>
</html>