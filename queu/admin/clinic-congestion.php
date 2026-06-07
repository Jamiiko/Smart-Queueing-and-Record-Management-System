<?php
// admin/clinic-congestion.php - Clinic Congestion Monitor
// Camp Evangelista Station Hospital

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/QueueManager.php';

session_start();

// ============================================
// AUTHENTICATION CHECK
// ============================================
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: ../index.php');
    exit();
}

// ============================================
// DATABASE CONNECTION
// ============================================
$database = new Database();
$db = $database->getConnection();
$queueManager = new QueueManager($db);

// ============================================
// SESSION TIMEOUT
// ============================================
require_once dirname(__DIR__) . '/includes/SessionManager.php';
$sessionManager = new SessionManager($db);
if (!$sessionManager->checkTimeout()) {
    exit(); // Already redirected
}
$sessionManager->logActivity('Viewed clinic congestion page');

$clinic_stats = $queueManager->getAllClinicsQueueStats();
$least_congested = $queueManager->findLeastCongestedClinic();

// Get detailed clinic stats with in_progress counts
$detailed_query = "SELECT 
    c.id,
    c.name,
    c.capacity_per_hour,
    COUNT(CASE WHEN q.status = 'pending' THEN 1 END) as pending_count,
    COUNT(CASE WHEN q.status = 'in_progress' THEN 1 END) as in_progress_count,
    COUNT(CASE WHEN q.status = 'completed' THEN 1 END) as completed_count
FROM clinics c
LEFT JOIN queue_entries q ON c.id = q.clinic_id AND DATE(q.registered_at) = CURDATE()
WHERE c.is_active = 1
GROUP BY c.id, c.name, c.capacity_per_hour
ORDER BY name";

$detailed_stats = $db->query($detailed_query)->fetchAll(PDO::FETCH_ASSOC);

// Calculate system-wide summary metrics
$total_pending = 0;
$total_in_progress = 0;
$total_completed = 0;
$high_congestion_count = 0;

foreach ($clinic_stats as $stat) {
    $total_pending += isset($stat['waiting_count']) ? $stat['waiting_count'] : 0;
    $total_completed += isset($stat['completed_count']) ? $stat['completed_count'] : 0;
    
    // Fix: Use a safe isset check to prevent the undefined array key error
    if (isset($stat['congestion_level'])) {
        if ($stat['congestion_level'] === 'High' || $stat['congestion_level'] === 'Critical') {
            $high_congestion_count++;
        }
    }
}

foreach ($detailed_stats as $ds) {
    $total_in_progress += $ds['in_progress_count'];
}

$total_active_queue = $total_pending + $total_in_progress;
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clinic Congestion Monitor | 4ID Station Hospital | Camp Evangelista</title>
    
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

    <aside id="sidebar" class="fixed top-0 left-0 h-screen bg-white dark:bg-[#1f2937] border-r border-slate-300/90 dark:border-slate-700/80 shadow-xl md:shadow-none z-[1000] flex flex-col justify-between overflow-x-hidden transition-all duration-300 ease-in-out group/sidebar -translate-x-full md:translate-x-0 w-[260px] md:w-[80px] md:hover:w-[260px]">
        <div>
            <div class="p-4 border-b border-slate-300/90 dark:border-slate-700/60 mb-5 flex flex-col items-center justify-center min-h-[160px]">
                <div class="hidden md:flex md:group-hover/sidebar:hidden flex-col items-center justify-center font-extrabold text-2xl tracking-wider text-sky-600 dark:text-sky-400 leading-tight select-none animate-[fadeIn_0.15s_ease-in-out]">
                    <span>C</span><span>E</span><span>S</span><span>H</span>
                </div>
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
                        <a href="clinic-congestion.php" class="flex items-center rounded-xl font-semibold transition-all duration-150 bg-sky-50 dark:bg-sky-500/10 text-sky-600 dark:text-sky-400 border-l-4 border-sky-500 p-3 justify-center md:justify-start gap-0 md:group-hover/sidebar:gap-4">
                            <div class="w-6 h-6 flex items-center justify-center shrink-0">
                                <i class="fas fa-chart-pie text-base"></i>
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
                    <h1 class="text-slate-900 dark:text-white text-2xl md:text-3xl font-extrabold tracking-tight mb-0.5">Clinic Congestion Monitor</h1>
                    <p class="text-slate-500 dark:text-slate-400 text-xs md:text-sm font-medium">Real-time load balancing and operational bottleneck analytics</p>
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
                    <button id="profileMenuBtn" class="w-10 h-10 bg-white dark:bg-[#1f2937] rounded-full flex items-center justify-center text-sky-600 dark:text-sky-400 border border-slate-300 dark:border-slate-700 shadow-sm hover:border-sky-500 dark:hover:border-sky-400 focus:outline-none transition-all duration-150">
                        <i class="fas fa-user-md text-lg"></i>
                    </button>
                    
                    <div id="profileDropdown" class="hidden absolute right-0 mt-2 w-56 bg-white dark:bg-[#1f2937] border border-slate-300 dark:border-slate-700 rounded-xl shadow-xl z-[1100] animate-[modalFadeIn_0.15s_ease-out]">
                        <div class="p-3 border-b border-slate-200 dark:border-slate-700/60 bg-slate-50 dark:bg-slate-850/40 rounded-t-xl">
                            <p class="text-xs font-bold text-slate-900 dark:text-white truncate">System Administrator</p>
                            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider truncate mt-0.5">HOSP-HQ COM</p>
                        </div>
                        <div class="p-2">
                            <a href="../logout.php" onclick="return confirm('Confirm Dashboard Exit?')" class="flex items-center gap-2.5 w-full text-left px-3 py-2.5 text-xs font-bold text-rose-500 hover:bg-rose-50 dark:hover:bg-rose-500/10 rounded-lg transition-colors">
                                <i class="fas fa-power-off text-sm"></i>
                                <span>Logout Session</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div class="bg-white dark:bg-[#1f2937] border border-slate-300 dark:border-slate-700/70 rounded-xl p-4 shadow-sm">
                <div class="flex justify-between items-center mb-2">
                    <div class="w-9 h-9 bg-amber-50 dark:bg-amber-500/10 rounded-lg flex items-center justify-center text-amber-600 dark:text-amber-400 text-base">
                        <i class="fas fa-clock"></i>
                    </div>
                </div>
                <div class="text-2xl font-bold text-slate-900 dark:text-white mb-1 font-mono"><?= number_format($total_pending); ?></div>
                <div class="text-slate-400 dark:text-slate-400 text-[10px] font-bold uppercase tracking-wider">Total Waiting</div>
            </div>

            <div class="bg-white dark:bg-[#1f2937] border border-slate-300 dark:border-slate-700/70 rounded-xl p-4 shadow-sm">
                <div class="flex justify-between items-center mb-2">
                    <div class="w-9 h-9 bg-sky-50 dark:bg-sky-500/10 rounded-lg flex items-center justify-center text-sky-600 dark:text-sky-400 text-base">
                        <i class="fas fa-spinner"></i>
                    </div>
                </div>
                <div class="text-2xl font-bold text-slate-900 dark:text-white mb-1 font-mono"><?= number_format($total_in_progress); ?></div>
                <div class="text-slate-400 dark:text-slate-400 text-[10px] font-bold uppercase tracking-wider">In Consultation</div>
            </div>

            <div class="bg-white dark:bg-[#1f2937] border border-slate-300 dark:border-slate-700/70 rounded-xl p-4 shadow-sm">
                <div class="flex justify-between items-center mb-2">
                    <div class="w-9 h-9 bg-emerald-50 dark:bg-emerald-500/10 rounded-lg flex items-center justify-center text-emerald-600 dark:text-emerald-400 text-base">
                        <i class="fas fa-check-double"></i>
                    </div>
                </div>
                <div class="text-2xl font-bold text-slate-900 dark:text-white mb-1 font-mono"><?= number_format($total_completed); ?></div>
                <div class="text-slate-400 dark:text-slate-400 text-[10px] font-bold uppercase tracking-wider">Served Clearances</div>
            </div>

            <div class="bg-white dark:bg-[#1f2937] border border-slate-300 dark:border-slate-700/70 rounded-xl p-4 shadow-sm">
                <div class="flex justify-between items-center mb-2">
                    <div class="w-9 h-9 <?= $high_congestion_count > 0 ? 'bg-rose-50 dark:bg-rose-500/10 text-rose-600 dark:text-rose-400' : 'bg-slate-100 dark:bg-slate-800 text-slate-400' ?> rounded-lg flex items-center justify-center text-base">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                </div>
                <div class="text-2xl font-bold text-slate-900 dark:text-white mb-1 font-mono"><?= number_format($high_congestion_count); ?></div>
                <div class="text-slate-400 dark:text-slate-400 text-[10px] font-bold uppercase tracking-wider">Overloaded Units</div>
            </div>
        </div>

        <?php if ($least_congested): ?>
            <div class="p-4 mb-6 bg-sky-50 dark:bg-sky-500/10 border border-sky-300 dark:border-sky-500/20 text-sky-700 dark:text-sky-300 rounded-xl flex flex-col sm:flex-row items-start sm:items-center justify-between shadow-sm gap-3">
                <div class="flex items-center">
                    <div class="w-8 h-8 rounded-lg bg-sky-500 text-white flex items-center justify-center mr-3 shrink-0">
                        <i class="fas fa-route text-xs"></i>
                    </div>
                    <div>
                        <h4 class="text-xs font-extrabold uppercase tracking-wider text-sky-900 dark:text-sky-400">Intelligent Routing Suggestion</h4>
                        <p class="text-[11px] mt-0.5 opacity-90">Triage operations should balance layout distribution by routing new entries toward <strong class="font-bold underline"><?= htmlspecialchars($least_congested['name']); ?></strong> (<?= isset($least_congested['waiting_count']) ? $least_congested['waiting_count'] : 0; ?> waiting).</p>
                    </div>
                </div>
                <a href="queue-monitor.php" class="bg-sky-600 dark:bg-sky-500 text-white font-bold text-[10px] tracking-wide uppercase px-3 py-1.5 rounded-lg hover:bg-sky-700 dark:hover:bg-sky-600 transition-all shrink-0">Manage Entries</a>
            </div>
        <?php endif; ?>

        <section class="bg-white dark:bg-[#1f2937] border border-slate-300 dark:border-slate-700/70 rounded-xl shadow-sm overflow-hidden mb-8">
            <div class="p-4 border-b border-slate-200 dark:border-slate-700/60 bg-slate-50/50 dark:bg-slate-800/20 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div>
                    <h3 class="text-xs font-bold uppercase text-slate-900 dark:text-white tracking-wider">Functional Department Status Breakdown</h3>
                    <p class="text-[11px] text-slate-400 mt-0.5">Real-time parameters for internal diagnostic throughput and clinical workflow speed</p>
                </div>
                <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-[10px] font-mono text-slate-500 dark:text-slate-400">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                    <span>Live Tracking Sync</span>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full border-collapse text-left">
                    <thead>
                        <tr class="bg-slate-50 dark:bg-slate-800/50 border-b border-slate-300 dark:border-slate-700/80 text-slate-400 dark:text-slate-400 text-[10px] font-bold uppercase tracking-wider">
                            <th class="py-3 px-4">Clinic Department Name</th>
                            <th class="py-3 px-4 text-center">Pending Check-In</th>
                            <th class="py-3 px-4 text-center">Active Treatment</th>
                            <th class="py-3 px-4 text-center">Today Completed</th>
                            <th class="py-3 px-4 text-center">Hourly Target Capacity</th>
                            <th class="py-3 px-4">Congestion Index Threshold</th>
                            <th class="py-3 px-4 text-right">System Metrics Context</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-700/60 text-xs font-medium">
                        <?php if (empty($detailed_stats)): ?>
                            <tr>
                                <td colspan="7" class="py-8 text-center text-slate-400 font-bold uppercase tracking-wider">No active departments or tracking counters configuration discovered.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($detailed_stats as $row): 
                                // Find match from QueueManager analytical response map
                                $matched_analytics = [
                                    'waiting_count' => $row['pending_count'],
                                    'congestion_level' => 'Low'
                                ];
                                foreach ($clinic_stats as $cs) {
                                    if (isset($cs['clinic_id']) && $cs['clinic_id'] == $row['id']) {
                                        $matched_analytics = $cs;
                                        break;
                                    }
                                }
                                
                                // Setup color dynamic badges safely
                                $level = isset($matched_analytics['congestion_level']) ? $matched_analytics['congestion_level'] : 'Low';
                                if ($level === 'Critical' || $level === 'High') {
                                    $badge_styles = "bg-rose-50 dark:bg-rose-500/10 text-rose-600 dark:text-rose-400";
                                } elseif ($level === 'Moderate') {
                                    $badge_styles = "bg-amber-50 dark:bg-amber-500/10 text-amber-600 dark:text-amber-400";
                                } else {
                                    $badge_styles = "bg-emerald-50 dark:bg-emerald-500/10 text-emerald-600 dark:text-emerald-400";
                                }

                                // Define dot animation class parameters
                                if ($level === 'Critical') {
                                    $dot_color = 'bg-rose-500 animate-ping';
                                } elseif ($level === 'High') {
                                    $dot_color = 'bg-rose-500';
                                } elseif ($level === 'Moderate') {
                                    $dot_color = 'bg-amber-500';
                                } else {
                                    $dot_color = 'bg-emerald-500';
                                }
                            ?>
                                <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-800/30 transition-colors">
                                    <td class="py-3.5 px-4 font-semibold text-slate-900 dark:text-white flex items-center gap-2">
                                        <div class="w-2 h-2 rounded-full <?= $dot_color; ?>"></div>
                                        <span><?= htmlspecialchars($row['name']); ?></span>
                                    </td>
                                    <td class="py-3.5 px-4 text-center font-mono font-bold text-slate-700 dark:text-slate-300"><?= number_format($row['pending_count']); ?></td>
                                    <td class="py-3.5 px-4 text-center font-mono font-bold text-sky-600 dark:text-sky-400"><?= number_format($row['in_progress_count']); ?></td>
                                    <td class="py-3.5 px-4 text-center font-mono font-bold text-emerald-600 dark:text-emerald-400"><?= number_format($row['completed_count']); ?></td>
                                    <td class="py-3.5 px-4 text-center font-mono text-slate-500 dark:text-slate-400"><?= number_format($row['capacity_per_hour']); ?>/hr</td>
                                    <td class="py-3.5 px-4">
                                        <span class="px-2.5 py-0.5 rounded-full text-[9px] font-extrabold uppercase tracking-wide <?= $badge_styles ?>">
                                            <?= htmlspecialchars($level); ?> Load
                                        </span>
                                    </td>
                                    <td class="py-3.5 px-4 text-right whitespace-nowrap">
                                        <a href="queue-monitor.php" class="inline-flex items-center gap-1 text-[10px] font-bold text-sky-600 dark:text-sky-400 hover:underline uppercase tracking-wide">
                                            <span>Inspect Queue</span>
                                            <i class="fas fa-chevron-right text-[8px]"></i>
                                        </a>
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
        // Real-Time System Clock Script
        function updateDateTime() {
            const now = new Date();
            const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
            document.getElementById('currentDate').textContent = now.toLocaleDateString('en-US', options);
            document.getElementById('currentTime').textContent = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
        }
        updateDateTime();
        setInterval(updateDateTime, 1000);

        // Header Profile Context Dropdown
        const profileMenuBtn = document.getElementById('profileMenuBtn');
        const profileDropdown = document.getElementById('profileDropdown');
        if (profileMenuBtn && profileDropdown) {
            profileMenuBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                profileDropdown.classList.toggle('hidden');
            });
            document.addEventListener('click', () => profileDropdown.classList.add('hidden'));
        }

        // Mobile Responsive Navigation Control Hooks
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

        // Activity Inactivity Automatic Timeout Check System
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

        // Dark/Light System Visual Engine State Context Management
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

        // Automatic Page Reload Routine to pull the freshest queue parameters every 60 seconds
        setInterval(function() {
            window.location.reload();
        }, 60000);
    </script>
</body>
</html>