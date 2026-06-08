<?php
// admin/queue-monitor.php - Queue Monitoring Dashboard
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

// Get queue status for all clinics
$query = "SELECT 
            c.*,
            COUNT(CASE WHEN q.status IN ('waiting', 'called') AND DATE(q.registered_at) = CURDATE() THEN 1 END) as waiting,
            COUNT(CASE WHEN q.status = 'in-progress' AND DATE(q.registered_at) = CURDATE() THEN 1 END) as in_progress,
            COUNT(CASE WHEN q.status = 'completed' AND DATE(q.registered_at) = CURDATE() THEN 1 END) as completed,
            COUNT(CASE WHEN DATE(q.registered_at) = CURDATE() THEN 1 END) as total_today,
            AVG(CASE WHEN q.completed_at IS NOT NULL AND DATE(q.registered_at) = CURDATE() 
                THEN TIMESTAMPDIFF(MINUTE, q.registered_at, q.completed_at) END) as avg_wait_time
          FROM clinics c
          LEFT JOIN queue_entries q ON c.id = q.clinic_id
          WHERE c.is_active = 1
          GROUP BY c.id
          ORDER BY c.name";

$clinics = $db->query($query)->fetchAll(PDO::FETCH_ASSOC);

// Get real-time queue entries
$queue_query = "SELECT 
                  q.*, 
                  p.first_name, 
                  p.last_name, 
                  p.patient_type,
                  p.is_pwd, 
                  p.is_senior, 
                  p.is_pregnant,
                  c.name as clinic_name,
                  TIMESTAMPDIFF(MINUTE, q.registered_at, NOW()) as waiting_minutes
                FROM queue_entries q
                JOIN patients p ON q.patient_id = p.id
                JOIN clinics c ON q.clinic_id = c.id
                WHERE q.status IN ('waiting', 'called', 'in-progress')
                AND DATE(q.registered_at) = CURDATE()
                ORDER BY 
                  FIELD(q.priority_level, 'PR1', 'PR2', 'PR3'),
                  q.registered_at ASC
                LIMIT 50";

$queue_entries = $db->query($queue_query)->fetchAll(PDO::FETCH_ASSOC);

// Get hourly statistics
$hourly_query = "SELECT 
                   HOUR(registered_at) as hour,
                   COUNT(*) as total,
                   SUM(CASE WHEN priority_level = 'PR1' THEN 1 ELSE 0 END) as pr1,
                   SUM(CASE WHEN priority_level = 'PR2' THEN 1 ELSE 0 END) as pr2,
                   SUM(CASE WHEN priority_level = 'PR3' THEN 1 ELSE 0 END) as pr3
                 FROM queue_entries
                 WHERE DATE(registered_at) = CURDATE()
                 GROUP BY HOUR(registered_at)
                 ORDER BY hour";

$hourly_stats = $db->query($hourly_query)->fetchAll(PDO::FETCH_ASSOC);

// Calculate totals safely
$total_waiting = array_sum(array_column($clinics, 'waiting'));
$total_in_progress = array_sum(array_column($clinics, 'in_progress'));
$total_completed = array_sum(array_column($clinics, 'completed'));
$avg_wait_time = round(array_sum(array_column($clinics, 'avg_wait_time')) / max(count($clinics), 1));
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Queue Monitor | 4ID Station Hospital</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <meta http-equiv="refresh" content="30">
    
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
<body class="bg-[#f8fafc] dark:bg-[#0f172a] text-slate-700 dark:text-slate-300 font-sans antialiased min-h-full transition-colors duration-200">

    <!-- Collapsible Sidebar Container -->
    <aside id="sidebar" class="fixed top-0 left-0 h-screen bg-white dark:bg-[#1e293b] border-r border-slate-200 dark:border-slate-800 shadow-sm z-[1000] flex flex-col justify-between overflow-x-hidden transition-all duration-300 ease-in-out group/sidebar -translate-x-full md:translate-x-0 w-[270px] md:w-[80px] md:hover:w-[270px]">
        <div>
            <!-- Header Identity Dock -->
            <div class="p-4 border-b border-slate-100 dark:border-slate-800/60 mb-6 flex flex-col items-center justify-center min-h-[150px]">
                <div class="hidden md:flex md:group-hover/sidebar:hidden flex-col items-center justify-center font-extrabold text-xl tracking-wider text-sky-600 dark:text-sky-400 select-none">
                    <span>C</span><span>E</span><span>S</span><span>H</span>
                </div>
                <div class="flex md:hidden md:group-hover/sidebar:flex flex-col items-center animate-[fadeIn_0.2s_ease-in-out]">
                    <img src="../assets/images/logo.png" alt="Logo" class="max-w-[200px] h-auto rounded mb-3 dark:brightness-110 dark:drop-shadow-[0_0_8px_rgba(56,189,248,0.2)]" onerror="this.style.display='none'">
                    <h2 class="text-slate-800 dark:text-slate-200 text-sm font-extrabold tracking-tight text-center whitespace-nowrap">4ID Station Hospital</h2>
                    <p class="text-slate-400 dark:text-slate-500 text-[0.65rem] font-bold uppercase tracking-widest text-center whitespace-nowrap mt-0.5">Camp Evangelista</p>
                </div>
            </div>
            
            <!-- Navigation System Links -->
            <nav class="px-3 md:group-hover/sidebar:px-4 transition-all duration-200">
                <ul class="list-none p-0 space-y-1">
                    <li>
                        <a href="dashboard.php" class="flex items-center rounded-xl font-medium transition-all duration-150 text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800/40 p-3 justify-center md:justify-start gap-0 md:group-hover/sidebar:gap-3 group/link">
                            <div class="w-6 h-6 flex items-center justify-center shrink-0">
                                <i class="fas fa-tachometer-alt text-base text-slate-400 group-hover/link:text-sky-500 transition-colors"></i>
                            </div>
                            <span class="opacity-100 md:opacity-0 md:group-hover/sidebar:opacity-100 text-[0.85rem] tracking-wide whitespace-nowrap transition-opacity duration-200 origin-left">Dashboard</span>
                        </a>
                    </li>
                    <li>
                        <a href="patients.php" class="flex items-center rounded-xl font-medium transition-all duration-150 text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800/40 p-3 justify-center md:justify-start gap-0 md:group-hover/sidebar:gap-3 group/link">
                            <div class="w-6 h-6 flex items-center justify-center shrink-0">
                                <i class="fas fa-users text-base text-slate-400 group-hover/link:text-sky-500 transition-colors"></i>
                            </div>
                            <span class="opacity-100 md:opacity-0 md:group-hover/sidebar:opacity-100 text-[0.85rem] tracking-wide whitespace-nowrap transition-opacity duration-200 origin-left">Patients</span>
                        </a>
                    </li>
                    <li>
                        <a href="queue-monitor.php" class="flex items-center rounded-xl font-semibold transition-all duration-150 bg-sky-50 dark:bg-sky-500/10 text-sky-600 dark:text-sky-400 border-l-4 border-sky-500 p-3 justify-center md:justify-start gap-0 md:group-hover/sidebar:gap-3">
                            <div class="w-6 h-6 flex items-center justify-center shrink-0">
                                <i class="fas fa-chart-line text-base"></i>
                            </div>
                            <span class="opacity-100 md:opacity-0 md:group-hover/sidebar:opacity-100 text-[0.85rem] tracking-wide whitespace-nowrap transition-opacity duration-200 origin-left">Queue Monitor</span>
                        </a>
                    </li>
                    <li>
                        <a href="clinic-congestion.php" class="flex items-center rounded-xl font-medium transition-all duration-150 text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800/40 p-3 justify-center md:justify-start gap-0 md:group-hover/sidebar:gap-3 group/link">
                            <div class="w-6 h-6 flex items-center justify-center shrink-0">
                                <i class="fas fa-chart-pie text-base text-slate-400 group-hover/link:text-sky-500 transition-colors"></i>
                            </div>
                            <span class="opacity-100 md:opacity-0 md:group-hover/sidebar:opacity-100 text-[0.85rem] tracking-wide whitespace-nowrap transition-opacity duration-200 origin-left">Clinic Congestion</span>
                        </a>
                    </li>
                    <li>
                        <a href="reports.php" class="flex items-center rounded-xl font-medium transition-all duration-150 text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800/40 p-3 justify-center md:justify-start gap-0 md:group-hover/sidebar:gap-3 group/link">
                            <div class="w-6 h-6 flex items-center justify-center shrink-0">
                                <i class="fas fa-chart-bar text-base text-slate-400 group-hover/link:text-sky-500 transition-colors"></i>
                            </div>
                            <span class="opacity-100 md:opacity-0 md:group-hover/sidebar:opacity-100 text-[0.85rem] tracking-wide whitespace-nowrap transition-opacity duration-200 origin-left">Reports</span>
                        </a>
                    </li>
                    <li>
                        <a href="users.php" class="flex items-center rounded-xl font-medium transition-all duration-150 text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800/40 p-3 justify-center md:justify-start gap-0 md:group-hover/sidebar:gap-3 group/link">
                            <div class="w-6 h-6 flex items-center justify-center shrink-0">
                                <i class="fas fa-users-cog text-base text-slate-400 group-hover/link:text-sky-500 transition-colors"></i>
                            </div>
                            <span class="opacity-100 md:opacity-0 md:group-hover/sidebar:opacity-100 text-[0.85rem] tracking-wide whitespace-nowrap transition-opacity duration-200 origin-left">User Management</span>
                        </a>
                    </li>
                    <li>
                        <a href="login-monitor.php" class="flex items-center rounded-xl font-medium transition-all duration-150 text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800/40 p-3 justify-center md:justify-start gap-0 md:group-hover/sidebar:gap-3 group/link">
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

    <!-- Main Content Grid -->
    <main class="min-h-screen ml-0 md:ml-[80px] p-4 md:p-6 transition-all duration-300">
        
        <!-- Header Controls Layout Ribbon -->
        <header class="flex flex-col sm:flex-row justify-between sm:items-center mb-6 pb-4 border-b border-slate-200 dark:border-slate-800 gap-3">
            <div class="flex items-center gap-3">
                <button id="mobileMenuBtn" class="md:hidden p-2 text-slate-600 dark:text-slate-400 bg-white dark:bg-[#1e293b] border border-slate-200 dark:border-slate-700 rounded-xl">
                    <i class="fas fa-bars text-lg"></i>
                </button>
                <div>
                    <h1 class="text-slate-800 dark:text-slate-100 text-2xl font-extrabold tracking-tight mb-0.5">Queue Monitor</h1>
                    <p class="text-slate-400 dark:text-slate-500 text-xs font-medium">Real-time tactical triage tracking and current clinic throughput stats</p>
                </div>
            </div>
            
            <div class="flex items-center gap-2 md:gap-4 relative">
                <div class="inline-flex items-center gap-1.5 bg-slate-100 dark:bg-slate-800/60 px-2.5 py-1 rounded-lg border border-slate-200 dark:border-slate-700 text-slate-500 dark:text-slate-400 text-[10px] font-bold uppercase tracking-wider">
                    <i class="fas fa-sync-alt animate-spin text-[9px] text-sky-500"></i> Auto: 30s
                </div>
                <div class="text-right text-[11px] hidden sm:block">
                    <div class="text-slate-600 dark:text-slate-400 font-semibold" id="currentDate"></div>
                    <div class="text-sky-600 dark:text-sky-400 font-bold font-mono text-xs mt-0.5" id="currentTime"></div>
                </div>

                <button id="themeToggleBtn" class="w-9 h-9 flex items-center justify-center bg-white dark:bg-[#1e293b] text-slate-400 border border-slate-200 dark:border-slate-700 rounded-xl hover:text-sky-500 transition-all" title="Toggle Visual Mode">
                    <i id="themeToggleIcon" class="fas fa-moon text-sm"></i>
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

        <!-- 📊 TOP TIER MINI STATS MATRIX -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-5">
            <div class="bg-white dark:bg-[#1e293b] border border-slate-150 dark:border-slate-800/60 rounded-xl p-4 shadow-sm flex items-center justify-between">
                <div>
                    <div class="text-slate-400 dark:text-slate-500 text-xs font-semibold uppercase tracking-wider mb-0.5">Total Waiting</div>
                    <div class="text-2xl font-black text-slate-800 dark:text-slate-100 font-mono tracking-tight"><?= number_format($total_waiting); ?></div>
                </div>
                <div class="w-10 h-10 bg-amber-500/5 rounded-lg flex items-center justify-center text-amber-500 text-base shrink-0">
                    <i class="fas fa-clock"></i>
                </div>
            </div>

            <div class="bg-white dark:bg-[#1e293b] border border-slate-150 dark:border-slate-800/60 rounded-xl p-4 shadow-sm flex items-center justify-between">
                <div>
                    <div class="text-slate-400 dark:text-slate-500 text-xs font-semibold uppercase tracking-wider mb-0.5">In Progress</div>
                    <div class="text-2xl font-black text-slate-800 dark:text-slate-100 font-mono tracking-tight"><?= number_format($total_in_progress); ?></div>
                </div>
                <div class="w-10 h-10 bg-sky-500/5 rounded-lg flex items-center justify-center text-sky-500 text-base shrink-0">
                    <i class="fas fa-play-circle"></i>
                </div>
            </div>

            <div class="bg-white dark:bg-[#1e293b] border border-slate-150 dark:border-slate-800/60 rounded-xl p-4 shadow-sm flex items-center justify-between">
                <div>
                    <div class="text-slate-400 dark:text-slate-500 text-xs font-semibold uppercase tracking-wider mb-0.5">Completed Today</div>
                    <div class="text-2xl font-black text-slate-800 dark:text-slate-100 font-mono tracking-tight"><?= number_format($total_completed); ?></div>
                </div>
                <div class="w-10 h-10 bg-emerald-500/5 rounded-lg flex items-center justify-center text-emerald-500 text-base shrink-0">
                    <i class="fas fa-check-circle"></i>
                </div>
            </div>

            <div class="bg-white dark:bg-[#1e293b] border border-slate-150 dark:border-slate-800/60 rounded-xl p-4 shadow-sm flex items-center justify-between">
                <div>
                    <div class="text-slate-400 dark:text-slate-500 text-xs font-semibold uppercase tracking-wider mb-0.5">Avg Wait Time</div>
                    <div class="text-2xl font-black text-slate-800 dark:text-slate-100 font-mono tracking-tight"><?= $avg_wait_time; ?><span class="text-sm ml-0.5 font-sans font-medium text-slate-400">m</span></div>
                </div>
                <div class="w-10 h-10 bg-indigo-500/5 rounded-lg flex items-center justify-center text-indigo-500 text-base shrink-0">
                    <i class="fas fa-hourglass-half"></i>
                </div>
            </div>
        </div>

        <!-- 📊 CLINIC THROUGHPUT GRID BLOCK -->
        <h3 class="text-slate-400 dark:text-slate-500 text-[10px] font-bold uppercase tracking-widest mb-3 flex items-center gap-1.5">
            <i class="fas fa-hospital text-sky-500/80"></i> Active Department Loads
        </h3>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <?php foreach ($clinics as $clinic): 
                $load_percentage = (($clinic['waiting'] + $clinic['in_progress']) / max($clinic['capacity_per_hour'], 1)) * 100;
                $load_percentage = min(100, $load_percentage);
                
                $borderTheme = "border-l-emerald-400 dark:border-l-emerald-500";
                $barFill = "bg-emerald-400 dark:bg-emerald-500";
                $txtTheme = "text-emerald-500 bg-emerald-500/5 border-emerald-500/10";
                $loadText = "Low Load";

                if ($load_percentage > 60) {
                    $borderTheme = "border-l-rose-400 dark:border-l-rose-500";
                    $barFill = "bg-rose-400 dark:bg-rose-500";
                    $txtTheme = "text-rose-500 bg-rose-500/5 border-rose-500/10";
                    $loadText = "High Load";
                } elseif ($load_percentage > 30) {
                    $borderTheme = "border-l-amber-400 dark:border-l-amber-500";
                    $barFill = "bg-amber-400 dark:bg-amber-500";
                    $txtTheme = "text-amber-500 bg-amber-500/5 border-amber-500/10";
                    $loadText = "Moderate";
                }
            ?>
            <div class="bg-white dark:bg-[#1e293b] border border-slate-150 dark:border-slate-800/60 border-l-4 <?= $borderTheme ?> rounded-xl p-3.5 shadow-sm">
                <div class="flex items-center justify-between mb-2 overflow-hidden gap-1">
                    <h4 class="font-bold text-slate-800 dark:text-slate-200 text-xs uppercase tracking-wider truncate flex-1"><?= htmlspecialchars($clinic['name']); ?></h4>
                    <span class="text-[9px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-full border <?= $txtTheme ?> shrink-0 select-none"><?= $loadText; ?></span>
                </div>
                
                <div class="grid grid-cols-3 gap-1 py-1 border-y border-slate-100 dark:border-slate-800/40 my-2 text-center bg-slate-50/30 dark:bg-slate-800/10 rounded-md">
                    <div>
                        <div class="font-bold text-slate-800 dark:text-slate-200 font-mono text-sm leading-tight"><?= $clinic['waiting']; ?></div>
                        <div class="text-[9px] font-medium tracking-wider text-slate-400 uppercase">Wait</div>
                    </div>
                    <div>
                        <div class="font-bold text-slate-800 dark:text-slate-200 font-mono text-sm leading-tight"><?= $clinic['in_progress']; ?></div>
                        <div class="text-[9px] font-medium tracking-wider text-slate-400 uppercase">Active</div>
                    </div>
                    <div>
                        <div class="font-medium text-slate-400 dark:text-slate-500 font-mono text-sm leading-tight"><?= $clinic['completed']; ?></div>
                        <div class="text-[9px] font-medium tracking-wider text-slate-400 dark:text-slate-500 uppercase">Done</div>
                    </div>
                </div>

                <div class="flex items-center justify-between text-[10px] font-medium text-slate-400 mt-1">
                    <span>Cap: <b class="text-slate-600 dark:text-slate-300 font-mono font-bold"><?= ($clinic['waiting'] + $clinic['in_progress']); ?>/<?= $clinic['capacity_per_hour']; ?></b></span>
                    <div class="w-16 bg-slate-100 dark:bg-slate-800 h-1 rounded-full overflow-hidden">
                        <div class="h-full <?= $barFill ?>" style="width: <?= $load_percentage ?>%"></div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- 🗂️ MAIN WORKSPACE GRID INTERFACE -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
            
            <!-- LIVE SOFT-CONTRAST STREAM CONTAINER -->
            <div class="lg:col-span-2 bg-white dark:bg-[#1e293b] border border-slate-150 dark:border-slate-800/60 rounded-xl overflow-hidden shadow-sm">
                <div class="px-4 py-3 border-b border-slate-100 dark:border-slate-800/60 bg-white dark:bg-[#1e293b] flex items-center justify-between">
                    <h3 class="text-slate-800 dark:text-slate-200 text-xs font-bold uppercase tracking-wider flex items-center gap-1.5">
                        <i class="fas fa-list-ul text-sky-500/80"></i> Real-time Active Stream
                    </h3>
                    <div class="flex items-center gap-1 text-[9px] font-bold text-rose-400 dark:text-rose-500 uppercase tracking-widest bg-rose-500/5 border border-rose-500/10 px-2 py-0.5 rounded-full">
                        <span class="w-1.5 h-1.5 bg-rose-400 dark:bg-rose-500 rounded-full animate-pulse"></span> Broadcast Feed
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <!-- Adjusted for low visual stress: zebra striping + ultra-faint borders -->
                    <table class="w-full text-left border-collapse table-fixed min-w-[600px]">
                        <thead>
                            <tr class="bg-slate-50/60 dark:bg-slate-800/30 border-b border-slate-100 dark:border-slate-800 text-xs font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500">
                                <th class="py-2.5 px-4 w-[16%]">Ticket No</th>
                                <th class="py-2.5 px-3 w-[36%]">Patient Name & Timing Specs</th>
                                <th class="py-2.5 px-3 w-[22%]">Assigned Clinic</th>
                                <th class="py-2.5 px-3 w-[13%]">Priority</th>
                                <th class="py-2.5 px-3 w-[13%]">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800/40 text-sm text-slate-600 dark:text-slate-300 odd:bg-white even:bg-slate-50/30 dark:odd:bg-transparent dark:even:bg-slate-800/10">
                            <?php if (empty($queue_entries)): ?>
                                <tr>
                                    <td colspan="5" class="text-center py-12 text-slate-400">
                                        <i class="fas fa-check-circle text-xl mb-1.5 block text-slate-350"></i>
                                        <p class="text-xs font-medium text-slate-400">No active operational sessions logged.</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($queue_entries as $entry): 
                                    // Desaturated, soft-contrast tag maps
                                    $prClass = "bg-slate-100 text-slate-500 border-slate-200 dark:bg-slate-800 dark:text-slate-400 dark:border-slate-700";
                                    if ($entry['priority_level'] === 'PR1') $prClass = "bg-rose-500/5 text-rose-500 border-rose-500/10 dark:bg-rose-500/10";
                                    elseif ($entry['priority_level'] === 'PR2') $prClass = "bg-amber-500/5 text-amber-500 border-amber-500/10 dark:bg-amber-500/10";
                                    elseif ($entry['priority_level'] === 'PR3') $prClass = "bg-emerald-500/5 text-emerald-500 border-emerald-500/10 dark:bg-emerald-500/10";

                                    $stClass = "bg-slate-100 text-slate-500 border-slate-200 dark:bg-slate-800 dark:text-slate-400 dark:border-slate-700";
                                    if ($entry['status'] === 'waiting') $stClass = "bg-amber-500/5 text-amber-500 border-amber-500/10 dark:bg-amber-500/10";
                                    elseif ($entry['status'] === 'called') $stClass = "bg-sky-500/5 text-sky-500 border-sky-500/10 dark:bg-sky-500/10";
                                    elseif ($entry['status'] === 'in-progress') $stClass = "bg-emerald-500/5 text-emerald-500 border-emerald-500/10 dark:bg-emerald-500/10";
                                ?>
                                    <tr class="hover:bg-sky-500/[0.02] dark:hover:bg-sky-400/[0.02] transition-colors duration-700">
                                        <td class="py-2 px-4 font-mono font-bold text-sky-600 dark:text-sky-400 text-sm tracking-tight">
                                            <?= htmlspecialchars($entry['queue_number']); ?>
                                        </td>
                                        <td class="py-2 px-3 overflow-hidden">
                                            <!-- Muted pure black to soft charcoal slate for less eye fatigue -->
                                            <div class="font-bold text-slate-750 dark:text-slate-200 text-base tracking-tight truncate">
                                                <?= htmlspecialchars(($entry['last_name'] ?? '') . ', ' . ($entry['first_name'] ?? '')); ?>
                                            </div>
                                            <div class="text-[10px] font-mono text-slate-400 dark:text-slate-500 mt-0.5">
                                                In: <?= date('h:i A', strtotime($entry['registered_at'])); ?> 
                                                <span class="mx-1 text-slate-200 dark:text-slate-700">|</span> 
                                                <span class="font-semibold text-amber-500/90 font-sans"><?= $entry['waiting_minutes']; ?>m back</span>
                                            </div>
                                        </td>
                                        <td class="py-2 px-3 font-semibold text-slate-500 dark:text-slate-400 text-sm truncate">
                                            <?= htmlspecialchars($entry['clinic_name']); ?>
                                        </td>
                                        <td class="py-2 px-3">
                                            <span class="px-2 py-0.5 rounded text-xs font-bold tracking-wide border uppercase inline-block <?= $prClass ?>">
                                                <?= $entry['priority_level']; ?>
                                            </span>
                                        </td>
                                        <td class="py-2 px-3">
                                            <span class="px-2 py-0.5 rounded text-xs font-bold tracking-wide border uppercase inline-block <?= $stClass ?>">
                                                <?= str_replace('-', ' ', $entry['status']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- COMPACT HISTOGRAM HISTORICAL HISTORICAL CARD -->
            <div class="bg-white dark:bg-[#1e293b] border border-slate-150 dark:border-slate-800/60 rounded-xl overflow-hidden shadow-sm">
                <div class="px-4 py-3 border-b border-slate-100 dark:border-slate-800/60 bg-white dark:bg-[#1e293b]">
                    <h3 class="text-slate-800 dark:text-slate-200 text-xs font-bold uppercase tracking-wider flex items-center gap-1.5">
                        <i class="fas fa-chart-line text-sky-500/80"></i> Hourly Distribution
                    </h3>
                </div>
                
                <div class="p-3 max-h-[520px] overflow-y-auto space-y-2">
                    <?php if (empty($hourly_stats)): ?>
                        <p class="text-center py-6 text-xs text-slate-400 font-medium">No system actions registered yet.</p>
                    <?php else: ?>
                        <?php foreach ($hourly_stats as $hour): 
                            $formatted_hour = sprintf('%02d:00 - %02d:00', $hour['hour'], $hour['hour']+1);
                        ?>
                            <div class="bg-slate-50/50 dark:bg-slate-800/20 border border-slate-100 dark:border-slate-800/40 rounded-lg p-2.5 flex flex-col gap-1.5">
                                <div class="flex items-center justify-between font-bold text-xs text-slate-400">
                                    <span class="font-mono text-slate-500 dark:text-slate-400"><?= $formatted_hour ?></span>
                                    <span class="font-bold text-sky-500 bg-sky-500/5 border border-sky-500/10 px-2 py-0.5 rounded font-mono text-xs">Vol: <?= $hour['total'] ?></span>
                                </div>
                                
                                <div class="flex items-center gap-0.5">
                                    <div class="flex-1 bg-rose-500/10 h-1.5 rounded-sm overflow-hidden">
                                        <div class="h-full bg-rose-400/80" style="width: <?= $hour['total'] > 0 ? ($hour['pr1'] / $hour['total']) * 100 : 0; ?>%"></div>
                                    </div>
                                    <div class="flex-1 bg-amber-500/10 h-1.5 rounded-sm overflow-hidden">
                                        <div class="h-full bg-amber-400/80" style="width: <?= $hour['total'] > 0 ? ($hour['pr2'] / $hour['total']) * 100 : 0; ?>%"></div>
                                    </div>
                                    <div class="flex-1 bg-emerald-500/10 h-1.5 rounded-sm overflow-hidden">
                                        <div class="h-full bg-emerald-400/80" style="width: <?= $hour['total'] > 0 ? ($hour['pr3'] / $hour['total']) * 100 : 0; ?>%"></div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </main>

    <script>
        // System Theme Configuration Script Injection
        const themeToggleBtn = document.getElementById('themeToggleBtn');
        const themeToggleIcon = document.getElementById('themeToggleIcon');
        const htmlElement = document.documentElement;

        if (localStorage.getItem('theme') === 'dark' || (!localStorage.getItem('theme') && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            htmlElement.classList.add('dark');
            themeToggleIcon.className = 'fas fa-sun text-sm text-amber-400';
        } else {
            htmlElement.classList.remove('dark');
            themeToggleIcon.className = 'fas fa-moon text-sm';
        }

        themeToggleBtn.addEventListener('click', () => {
            if (htmlElement.classList.contains('dark')) {
                htmlElement.classList.remove('dark');
                themeToggleIcon.className = 'fas fa-moon text-sm';
                localStorage.setItem('theme', 'light');
            } else {
                htmlElement.classList.add('dark');
                themeToggleIcon.className = 'fas fa-sun text-sm text-amber-400';
                localStorage.setItem('theme', 'dark');
            }
        });

        // Dropdown Controls
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

        // Live Clock Tick
        function updateDateTime() {
            const now = new Date();
            const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
            document.getElementById('currentDate').textContent = now.toLocaleDateString('en-US', options);
            document.getElementById('currentTime').textContent = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
        }
        updateDateTime();
        setInterval(updateDateTime, 1000);

        // Sidebar Actions
        const sidebar = document.getElementById('sidebar');
        const mobileMenuBtn = document.getElementById('mobileMenuBtn');

        mobileMenuBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            sidebar.classList.toggle('-translate-x-full');
        });

        document.addEventListener('click', (e) => {
            if (window.innerWidth < 768) {
                if (!sidebar.contains(e.target) && e.target !== mobileMenuBtn && !mobileMenuBtn.contains(e.target)) {
                    sidebar.classList.add('-translate-x-full');
                }
            }
        });

        // Auto Logout Trigger
        const INACTIVITY_TIMEOUT = 30 * 60 * 1000;
        let inactivityTimer;
        function resetInactivityTimer() {
            if (inactivityTimer) clearTimeout(inactivityTimer);
            inactivityTimer = setTimeout(function() {
                window.location.href = '../logout.php';
            }, INACTIVITY_TIMEOUT);
        }
        const events = ['mousedown', 'mousemove', 'keypress', 'scroll', 'touchstart', 'click', 'keydown'];
        events.forEach(event => document.addEventListener(event, resetInactivityTimer, false));
        resetInactivityTimer();
    </script>
</body>
</html>