<?php
// admin/reports.php - Reports & Analytics
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

// Get date range from request
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-d', strtotime('-30 days'));
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');
$report_type = isset($_GET['report_type']) ? $_GET['report_type'] : 'daily';

// Get summary statistics
$query = "SELECT COUNT(DISTINCT patient_id) as total_patients,
                 COUNT(*) as total_visits,
                 AVG(TIMESTAMPDIFF(MINUTE, registered_at, completed_at)) as avg_wait_time,
                 SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed
          FROM queue_entries 
          WHERE DATE(registered_at) BETWEEN ? AND ?";
$stmt = $db->prepare($query);
$stmt->execute([$date_from, $date_to]);
$summary = $stmt->fetch(PDO::FETCH_ASSOC);

// Get priority distribution
$priority_query = "SELECT priority_level, COUNT(*) as count
                   FROM queue_entries
                   WHERE DATE(registered_at) BETWEEN ? AND ?
                   GROUP BY priority_level";
$stmt = $db->prepare($priority_query);
$stmt->execute([$date_from, $date_to]);
$priority_dist = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get clinic performance
$clinic_query = "SELECT 
                    c.name,
                    COUNT(q.id) as total_patients,
                    SUM(CASE WHEN q.status = 'completed' THEN 1 ELSE 0 END) as completed,
                    AVG(TIMESTAMPDIFF(MINUTE, q.registered_at, q.completed_at)) as avg_time,
                    COUNT(DISTINCT q.patient_id) as unique_patients
                 FROM clinics c
                 LEFT JOIN queue_entries q ON c.id = q.clinic_id 
                    AND DATE(q.registered_at) BETWEEN ? AND ?
                 WHERE c.is_active = 1
                 GROUP BY c.id
                 ORDER BY total_patients DESC";
$stmt = $db->prepare($clinic_query);
$stmt->execute([$date_from, $date_to]);
$clinic_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get daily trends
if ($report_type == 'daily') {
    $trend_query = "SELECT 
                        DATE(registered_at) as date,
                        COUNT(*) as total,
                        SUM(CASE WHEN priority_level = 'PR1' THEN 1 ELSE 0 END) as pr1,
                        SUM(CASE WHEN priority_level = 'PR2' THEN 1 ELSE 0 END) as pr2,
                        SUM(CASE WHEN priority_level = 'PR3' THEN 1 ELSE 0 END) as pr3,
                        AVG(TIMESTAMPDIFF(MINUTE, registered_at, completed_at)) as avg_wait
                    FROM queue_entries
                    WHERE DATE(registered_at) BETWEEN ? AND ?
                    GROUP BY DATE(registered_at)
                    ORDER BY date DESC";
} else {
    $trend_query = "SELECT 
                        DATE_FORMAT(registered_at, '%Y-%m') as month,
                        COUNT(*) as total,
                        SUM(CASE WHEN priority_level = 'PR1' THEN 1 ELSE 0 END) as pr1,
                        SUM(CASE WHEN priority_level = 'PR2' THEN 1 ELSE 0 END) as pr2,
                        SUM(CASE WHEN priority_level = 'PR3' THEN 1 ELSE 0 END) as pr3,
                        AVG(TIMESTAMPDIFF(MINUTE, registered_at, completed_at)) as avg_wait
                    FROM queue_entries
                    WHERE DATE(registered_at) BETWEEN ? AND ?
                    GROUP BY DATE_FORMAT(registered_at, '%Y-%m')
                    ORDER BY month DESC";
}

$stmt = $db->prepare($trend_query);
$stmt->execute([$date_from, $date_to]);
$trends = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Prepare data for charts
$priority_labels = [];
$priority_data = [];
foreach ($priority_dist as $p) {
    $priority_labels[] = $p['priority_level'];
    $priority_data[] = $p['count'];
}

$trend_labels = [];
$trend_data = [];
foreach ($trends as $t) {
    $trend_labels[] = $report_type == 'daily' ? date('M d', strtotime($t['date'])) : $t['month'];
    $trend_data[] = $t['total'];
}
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports & Analytics | 4ID Station Hospital | Camp Evangelista</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

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
        @media print {
            .no-print, #sidebar, #themeToggleBtn, #profileMenuBtn { display: none !important; }
            main { margin-left: 0 !important; padding: 0 !important; max-width: 100% !important; }
            body { background: white !important; color: black !important; }
            .bg-white { border: 1px solid #CBD5E1 !important; box-shadow: none !important; break-inside: avoid; margin-bottom: 24px !important; }
            canvas { max-height: 250px !important; }
        }
    </style>
</head>
<body class="bg-slate-50 dark:bg-[#111827] text-slate-800 dark:text-slate-100 font-sans antialiased min-h-full transition-colors duration-200">

    <aside id="sidebar" class="fixed top-0 left-0 h-screen bg-white dark:bg-[#1f2937] border-r border-slate-300/90 dark:border-slate-700/80 shadow-xl md:shadow-none z-[1000] flex flex-col justify-between overflow-x-hidden transition-all duration-300 ease-in-out group/sidebar -translate-x-full md:translate-x-0 w-[260px] md:w-[80px] md:hover:w-[260px]">
        <div>
            <div class="p-4 border-b border-slate-300/90 dark:border-slate-700/60 mb-5 flex flex-col items-center justify-center min-h-[160px]">
                <div class="hidden md:flex md:group-hover/sidebar:hidden flex-col items-center justify-center font-extrabold text-2xl tracking-wider text-sky-600 dark:text-sky-400 leading-tight select-none animate-[fadeIn_0.15s_ease-in-out]">
                    <span>C</span><span>E</span><span>S</span><span>H</span>
                </div>
                <div class="flex md:hidden md:group-hover/sidebar:flex flex-col items-center animate-[fadeIn_0.2s_ease-in-out]">
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
                        <a href="reports.php" class="flex items-center rounded-xl font-semibold transition-all duration-150 bg-sky-50 dark:bg-sky-500/10 text-sky-600 dark:text-sky-400 border-l-4 border-sky-500 p-3 justify-center md:justify-start gap-0 md:group-hover/sidebar:gap-4">
                            <div class="w-6 h-6 flex items-center justify-center shrink-0">
                                <i class="fas fa-chart-bar text-base"></i>
                            </div>
                            <span class="opacity-100 md:opacity-0 md:group-hover/sidebar:opacity-100 text-xs tracking-wide whitespace-nowrap transition-opacity duration-200 origin-left">Reports</span>
                        </a>
                    </li>
                    <li>
                        <a href="users.php" class="flex items-center rounded-xl font-medium transition-all duration-150 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700/50 p-3 justify-center md:justify-start gap-0 md:group-hover/sidebar:gap-4 border-l-4 border-transparent group/link">
                            <div class="w-6 h-6 flex items-center justify-center shrink-0">
                                <i class="fas fa-users-cog text-base text-slate-400 group-hover/link:text-sky-500 transition-colors"></i>
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

    <main class="min-h-screen ml-0 md:ml-[80px] px-6 sm:px-12 py-8 md:pl-14 lg:pl-16 transition-all duration-300 max-w-[1680px] mx-auto">
        
        <header class="flex flex-col sm:flex-row justify-between sm:items-center mb-8 pb-5 border-b border-slate-300/90 dark:border-slate-700/80 gap-4">
            <div class="flex items-center gap-4">
                <button id="mobileMenuBtn" class="md:hidden p-2.5 text-slate-600 dark:text-slate-300 bg-white dark:bg-[#1f2937] border border-slate-300 rounded-xl shadow-sm">
                    <i class="fas fa-bars text-xl"></i>
                </button>
                <div>
                    <h1 class="text-slate-900 dark:text-white text-2xl md:text-3xl font-extrabold tracking-tight mb-0.5">Reports & Analytics</h1>
                    <p class="text-slate-500 dark:text-slate-400 text-xs md:text-sm font-medium">Comprehensive analytics and performance insights</p>
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

        <section class="bg-white dark:bg-[#1f2937] border border-slate-300 dark:border-slate-700/70 rounded-xl p-5 shadow-sm mb-6 no-print">
            <form method="GET" class="flex flex-col lg:flex-row items-end gap-5">
                <div class="w-full lg:w-auto flex-1 grid grid-cols-1 sm:grid-cols-3 gap-5">
                    <div>
                        <label class="block text-slate-500 dark:text-slate-400 text-[10px] font-bold uppercase tracking-wider mb-2"><i class="fas fa-calendar-alt text-sky-500 mr-1"></i> Date From</label>
                        <input type="date" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>" class="w-full px-4 py-2.5 bg-slate-50 dark:bg-[#111827] border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white rounded-xl focus:outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500 text-sm transition-all">
                    </div>
                    <div>
                        <label class="block text-slate-500 dark:text-slate-400 text-[10px] font-bold uppercase tracking-wider mb-2"><i class="fas fa-calendar-check text-sky-500 mr-1"></i> Date To</label>
                        <input type="date" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>" class="w-full px-4 py-2.5 bg-slate-50 dark:bg-[#111827] border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white rounded-xl focus:outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500 text-sm transition-all">
                    </div>
                    <div>
                        <label class="block text-slate-500 dark:text-slate-400 text-[10px] font-bold uppercase tracking-wider mb-2"><i class="fas fa-layer-group text-sky-500 mr-1"></i> Report Type</label>
                        <select name="report_type" class="w-full px-4 py-2.5 bg-slate-50 dark:bg-[#111827] border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white rounded-xl focus:outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500 text-sm transition-all">
                            <option value="daily" <?php echo $report_type == 'daily' ? 'selected' : ''; ?>>Daily</option>
                            <option value="monthly" <?php echo $report_type == 'monthly' ? 'selected' : ''; ?>>Monthly</option>
                        </select>
                    </div>
                </div>
                <div class="w-full lg:w-auto">
                    <button type="submit" class="bg-sky-600 hover:bg-sky-700 text-white font-semibold py-2.5 px-6 rounded-xl transition-all shadow-sm flex justify-center items-center gap-2 w-full sm:w-auto">
    <i class="fas fa-file-export"></i> <span>Generate Report</span>
</button>
                </div>
            </form>
        </section>

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div class="bg-white dark:bg-[#1f2937] border border-slate-300 dark:border-slate-700/70 rounded-xl p-5 shadow-sm flex items-center gap-4">
                <div class="w-12 h-12 bg-sky-50 dark:bg-sky-500/10 rounded-xl flex items-center justify-center text-sky-600 dark:text-sky-400 text-xl shrink-0">
                    <i class="fas fa-users"></i>
                </div>
                <div>
                    <div class="text-2xl font-bold text-slate-900 dark:text-white font-mono leading-tight"><?php echo number_format($summary['total_patients'] ?? 0); ?></div>
                    <div class="text-slate-500 dark:text-slate-400 text-[10px] font-bold uppercase tracking-wider mt-0.5">Unique Patients</div>
                </div>
            </div>

            <div class="bg-white dark:bg-[#1f2937] border border-slate-300 dark:border-slate-700/70 rounded-xl p-5 shadow-sm flex items-center gap-4">
                <div class="w-12 h-12 bg-indigo-50 dark:bg-indigo-500/10 rounded-xl flex items-center justify-center text-indigo-600 dark:text-indigo-400 text-xl shrink-0">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <div>
                    <div class="text-2xl font-bold text-slate-900 dark:text-white font-mono leading-tight"><?php echo number_format($summary['total_visits'] ?? 0); ?></div>
                    <div class="text-slate-500 dark:text-slate-400 text-[10px] font-bold uppercase tracking-wider mt-0.5">Total Visits</div>
                </div>
            </div>

            <div class="bg-white dark:bg-[#1f2937] border border-slate-300 dark:border-slate-700/70 rounded-xl p-5 shadow-sm flex items-center gap-4">
                <div class="w-12 h-12 bg-amber-50 dark:bg-amber-500/10 rounded-xl flex items-center justify-center text-amber-500 text-xl shrink-0">
                    <i class="fas fa-clock"></i>
                </div>
                <div>
                    <div class="text-2xl font-bold text-slate-900 dark:text-white font-mono leading-tight"><?php echo round($summary['avg_wait_time'] ?? 0); ?><span class="text-sm font-medium text-slate-400 ml-1">m</span></div>
                    <div class="text-slate-500 dark:text-slate-400 text-[10px] font-bold uppercase tracking-wider mt-0.5">Average Wait Time</div>
                </div>
            </div>

            <div class="bg-white dark:bg-[#1f2937] border border-slate-300 dark:border-slate-700/70 rounded-xl p-5 shadow-sm flex items-center gap-4">
                <div class="w-12 h-12 bg-emerald-50 dark:bg-emerald-500/10 rounded-xl flex items-center justify-center text-emerald-600 dark:text-emerald-400 text-xl shrink-0">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div>
                    <div class="text-2xl font-bold text-slate-900 dark:text-white font-mono leading-tight"><?php echo number_format($summary['completed'] ?? 0); ?></div>
                    <div class="text-slate-500 dark:text-slate-400 text-[10px] font-bold uppercase tracking-wider mt-0.5">Completed Visits</div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            <div class="bg-white dark:bg-[#1f2937] border border-slate-300 dark:border-slate-700/70 rounded-xl shadow-sm overflow-hidden flex flex-col">
                <div class="p-4 border-b border-slate-200 dark:border-slate-700/60 bg-slate-50/50 dark:bg-slate-800/20">
                    <h3 class="text-xs font-bold uppercase text-slate-900 dark:text-white tracking-wider flex items-center gap-2"><i class="fas fa-chart-pie text-sky-500 text-sm"></i> Priority Distribution</h3>
                </div>
                <div class="p-6 flex-1 flex items-center justify-center h-[300px]">
                    <canvas id="priorityChart"></canvas>
                </div>
            </div>

            <div class="bg-white dark:bg-[#1f2937] border border-slate-300 dark:border-slate-700/70 rounded-xl shadow-sm overflow-hidden flex flex-col">
                <div class="p-4 border-b border-slate-200 dark:border-slate-700/60 bg-slate-50/50 dark:bg-slate-800/20">
                    <h3 class="text-xs font-bold uppercase text-slate-900 dark:text-white tracking-wider flex items-center gap-2"><i class="fas fa-chart-line text-sky-500 text-sm"></i> Patient Volume Trend</h3>
                </div>
                <div class="p-6 flex-1 flex items-center justify-center h-[300px]">
                    <canvas id="trendChart"></canvas>
                </div>
            </div>
        </div>

        <section class="bg-white dark:bg-[#1f2937] border border-slate-300 dark:border-slate-700/70 rounded-xl shadow-sm overflow-hidden mb-8">
            <div class="p-4 border-b border-slate-200 dark:border-slate-700/60 bg-slate-50/50 dark:bg-slate-800/20 flex items-center justify-between">
                <div>
                    <h3 class="text-xs font-bold uppercase text-slate-900 dark:text-white tracking-wider flex items-center gap-2"><i class="fas fa-hospital-user text-sky-500 text-sm"></i> Clinic Performance</h3>
                </div>
                <button onclick="window.print()" class="no-print bg-white dark:bg-slate-800 border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 text-[10px] font-bold uppercase tracking-wider px-3 py-1.5 rounded-lg transition-colors flex items-center gap-2">
                    <i class="fas fa-print"></i> Print
                </button>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full border-collapse text-left">
                    <thead>
                        <tr class="bg-slate-50 dark:bg-slate-800/50 border-b border-slate-300 dark:border-slate-700/80 text-slate-400 text-[10px] font-bold uppercase tracking-wider">
                            <th class="py-3.5 px-6">Clinic</th>
                            <th class="py-3.5 px-6 text-center">Total Patients</th>
                            <th class="py-3.5 px-6 text-center">Unique Patients</th>
                            <th class="py-3.5 px-6 text-center">Completed</th>
                            <th class="py-3.5 px-6 text-center">Completion Rate</th>
                            <th class="py-3.5 px-6 text-right">Avg Time</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-700/60 text-xs font-medium text-slate-700 dark:text-slate-300">
                        <?php if(empty($clinic_stats)): ?>
                            <tr>
                                <td colspan="6" class="py-8 text-center text-slate-400 font-bold uppercase tracking-wider">No performance data found for the selected period.</td>
                            </tr>
                        <?php endif; ?>
                        <?php foreach ($clinic_stats as $clinic): ?>
                        <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-800/30 transition-colors">
                            <td class="py-4 px-6 font-semibold text-slate-900 dark:text-white"><?php echo htmlspecialchars($clinic['name']); ?></td>
                            <td class="py-4 px-6 text-center font-mono text-slate-600 dark:text-slate-400"><?php echo $clinic['total_patients'] ?? 0; ?></td>
                            <td class="py-4 px-6 text-center font-mono text-slate-600 dark:text-slate-400"><?php echo $clinic['unique_patients'] ?? 0; ?></td>
                            <td class="py-4 px-6 text-center font-mono text-emerald-600 dark:text-emerald-400 font-bold"><?php echo $clinic['completed'] ?? 0; ?></td>
                            <td class="py-4 px-6 text-center">
                                <?php 
                                $rate = ($clinic['total_patients'] > 0) ? round(($clinic['completed'] / $clinic['total_patients']) * 100) : 0;
                                ?>
                                <span class="px-2.5 py-1 rounded-full text-[10px] font-extrabold tracking-wide bg-emerald-50 dark:bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
                                    <?php echo $rate; ?>%
                                </span>
                            </td>
                            <td class="py-4 px-6 text-right font-mono text-slate-500 dark:text-slate-400"><?php echo round($clinic['avg_time'] ?? 0); ?> mins</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="bg-white dark:bg-[#1f2937] border border-slate-300 dark:border-slate-700/70 rounded-xl shadow-sm overflow-hidden mb-8">
            <div class="p-4 border-b border-slate-200 dark:border-slate-700/60 bg-slate-50/50 dark:bg-slate-800/20">
                <h3 class="text-xs font-bold uppercase text-slate-900 dark:text-white tracking-wider flex items-center gap-2"><i class="fas fa-table-list text-sky-500 text-sm"></i> <?php echo $report_type == 'daily' ? 'Daily' : 'Monthly'; ?> Breakdown</h3>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full border-collapse text-left">
                    <thead>
                        <tr class="bg-slate-50 dark:bg-slate-800/50 border-b border-slate-300 dark:border-slate-700/80 text-slate-400 text-[10px] font-bold uppercase tracking-wider">
                            <th class="py-3.5 px-6"><?php echo $report_type == 'daily' ? 'Date' : 'Month'; ?></th>
                            <th class="py-3.5 px-6 text-center">Total Visits</th>
                            <th class="py-3.5 px-6 text-center text-rose-500">PR1 (Military)</th>
                            <th class="py-3.5 px-6 text-center text-amber-500">PR2 (Priority)</th>
                            <th class="py-3.5 px-6 text-center text-sky-500">PR3 (Regular)</th>
                            <th class="py-3.5 px-6 text-right">Avg Wait</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-700/60 text-xs font-medium text-slate-700 dark:text-slate-300">
                        <?php if(empty($trends)): ?>
                            <tr>
                                <td colspan="6" class="py-8 text-center text-slate-400 font-bold uppercase tracking-wider">No breakdown data found for the selected period.</td>
                            </tr>
                        <?php endif; ?>
                        <?php foreach ($trends as $trend): ?>
                        <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-800/30 transition-colors">
                            <td class="py-4 px-6 font-semibold text-slate-900 dark:text-white">
                                <?php echo $report_type == 'daily' ? date('M d, Y', strtotime($trend['date'])) : htmlspecialchars($trend['month']); ?>
                            </td>
                            <td class="py-4 px-6 text-center font-mono font-bold text-slate-700 dark:text-slate-300"><?php echo $trend['total']; ?></td>
                            <td class="py-4 px-6 text-center font-mono text-rose-500"><?php echo $trend['pr1']; ?></td>
                            <td class="py-4 px-6 text-center font-mono text-amber-500"><?php echo $trend['pr2']; ?></td>
                            <td class="py-4 px-6 text-center font-mono text-sky-500"><?php echo $trend['pr3']; ?></td>
                            <td class="py-4 px-6 text-right font-mono text-slate-500 dark:text-slate-400"><?php echo round($trend['avg_wait'] ?? 0); ?> mins</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>

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

        // Sidebar Responsive Drawer Logic
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

        // Header Profile Dropdown
        const profileMenuBtn = document.getElementById('profileMenuBtn');
        const profileDropdown = document.getElementById('profileDropdown');
        if (profileMenuBtn && profileDropdown) {
            profileMenuBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                profileDropdown.classList.toggle('hidden');
            });
            document.addEventListener('click', () => profileDropdown.classList.add('hidden'));
        }

        // Theme Switcher Sync & Chart Config Detection
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
                // Reload window to re-draw charts with correct theme colors
                window.location.reload();
            });
        }

        // ============================================
        // CHART RENDERING SCRIPTS (PRESERVING PHP DATA)
        // ============================================
        const isDark = document.documentElement.classList.contains('dark');
        const textMuted = isDark ? '#94A3B8' : '#64748B'; // slate-400 : slate-500
        const gridColor = isDark ? '#334155' : '#E2E8F0'; // slate-700 : slate-200
        const fontFamily = '"Plus Jakarta Sans", sans-serif';

        // 1. Priority Chart
        const priorityCtx = document.getElementById('priorityChart').getContext('2d');
        new Chart(priorityCtx, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode($priority_labels); ?>,
                datasets: [{
                    data: <?php echo json_encode($priority_data); ?>,
                    backgroundColor: ['#EF4444', '#F59E0B', '#10B981', '#3B82F6', '#8B5CF6'], // Rose, Amber, Emerald, Blue, Violet fallback
                    borderWidth: 0,
                    hoverOffset: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '70%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            font: { family: fontFamily, size: 11, weight: 600 },
                            color: textMuted,
                            usePointStyle: true,
                            boxWidth: 8,
                            padding: 20
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.raw || 0;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = total > 0 ? Math.round((value / total) * 100) : 0;
                                return ` ${label}: ${value} (${percentage}%)`;
                            }
                        }
                    }
                }
            }
        });

        // 2. Trend Line Chart
        const trendCtx = document.getElementById('trendChart').getContext('2d');
        new Chart(trendCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($trend_labels); ?>,
                datasets: [{
                    label: 'Patient Volume',
                    data: <?php echo json_encode($trend_data); ?>,
                    borderColor: '#0ea5e9', // sky-500
                    backgroundColor: isDark ? 'rgba(14, 165, 233, 0.15)' : 'rgba(14, 165, 233, 0.1)',
                    tension: 0.35,
                    fill: true,
                    pointBackgroundColor: '#0ea5e9',
                    pointBorderColor: isDark ? '#1f2937' : '#ffffff',
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    borderWidth: 2.5
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(context) { return ` Patients: ${context.raw}`; }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { color: gridColor },
                        ticks: { stepSize: 1, font: { family: fontFamily, size: 10, weight: 600 }, color: textMuted }
                    },
                    x: {
                        grid: { display: false },
                        ticks: { font: { family: fontFamily, size: 10, weight: 600 }, color: textMuted, maxRotation: 45 }
                    }
                }
            }
        });

        // ============================================
        // PRESERVED SECURITY & INACTIVITY FUNCTIONS
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