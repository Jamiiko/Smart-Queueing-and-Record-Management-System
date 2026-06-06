<?php
// admin/dashboard.php - Command Dashboard
// Camp Evangelista Station Hospital

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/QueueManager.php';
require_once dirname(__DIR__) . '/includes/SessionManager.php';

session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: ../index.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();
$queueManager = new QueueManager($db);

// Get statistics
$query = "SELECT COUNT(*) as count FROM queue_entries WHERE DATE(registered_at) = CURDATE()";
$today_intake = $db->query($query)->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

$batch_info = $queueManager->getCurrentBatch();
$batch_progress = ($batch_info['current_count'] / 20) * 100;

// Get clinic status
$query = "SELECT 
            c.*,
            COUNT(CASE WHEN q.status IN ('waiting', 'called') AND DATE(q.registered_at) = CURDATE() THEN 1 END) as waiting_count,
            COUNT(CASE WHEN q.status = 'in-progress' AND DATE(q.registered_at) = CURDATE() THEN 1 END) as in_progress,
            COUNT(CASE WHEN q.status = 'completed' AND DATE(q.registered_at) = CURDATE() THEN 1 END) as completed
          FROM clinics c
          LEFT JOIN queue_entries q ON c.id = q.clinic_id
          WHERE c.is_active = 1
          GROUP BY c.id
          ORDER BY c.id";
$clinics = $db->query($query)->fetchAll(PDO::FETCH_ASSOC);

// Get recent activity
$query = "SELECT 
            q.queue_number,
            q.status,
            q.registered_at,
            p.first_name,
            p.last_name,
            p.patient_type,
            c.name as clinic_name
          FROM queue_entries q
          JOIN patients p ON q.patient_id = p.id
          JOIN clinics c ON q.clinic_id = c.id
          WHERE DATE(q.registered_at) = CURDATE()
          ORDER BY q.registered_at DESC
          LIMIT 10";
$recent = $db->query($query)->fetchAll(PDO::FETCH_ASSOC);

// Calculate totals
$total_waiting = array_sum(array_column($clinics, 'waiting_count'));
$total_in_progress = array_sum(array_column($clinics, 'in_progress'));
$total_completed = array_sum(array_column($clinics, 'completed'));
?>

<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Command Dashboard | 4ID Station Hospital | Camp Evangelista</title>
    
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
                    <span>C</span>
                    <span>E</span>
                    <span>S</span>
                    <span>H</span>
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
                        <a href="dashboard.php" class="flex items-center rounded-xl font-semibold transition-all duration-150 bg-sky-50 dark:bg-sky-500/10 text-sky-600 dark:text-sky-400 border-l-4 border-sky-500 p-3 justify-center md:justify-start gap-0 md:group-hover/sidebar:gap-3">
                            <div class="w-6 h-6 flex items-center justify-center shrink-0">
                                <i class="fas fa-tachometer-alt text-base"></i>
                            </div>
                            <span class="opacity-100 md:opacity-0 md:group-hover/sidebar:opacity-100 text-[0.85rem] tracking-wide whitespace-nowrap transition-opacity duration-200 origin-left">Dashboard</span>
                        </a>
                    </li>
                    <li>
                        <a href="patients.php" class="flex items-center rounded-xl font-medium transition-all duration-150 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700/50 p-3 justify-center md:justify-start gap-0 md:group-hover/sidebar:gap-3 border-l-4 border-transparent group/link">
                            <div class="w-6 h-6 flex items-center justify-center shrink-0">
                                <i class="fas fa-users text-base text-slate-400 group-hover/link:text-sky-500 transition-colors"></i>
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
        
        <header class="flex justify-between items-center mb-8 pb-5 border-b border-slate-300/90 dark:border-slate-700/80">
            <div class="flex items-center gap-4">
                <button id="mobileMenuBtn" class="md:hidden p-2 text-slate-600 dark:text-slate-300 bg-white dark:bg-[#1f2937] border border-slate-300 rounded-xl">
                    <i class="fas fa-bars text-xl"></i>
                </button>
                <div>
                    <h1 class="text-slate-900 dark:text-white text-2xl md:text-3xl font-extrabold tracking-tight mb-0.5">Command Dashboard</h1>
                    <p class="text-slate-500 dark:text-slate-400 text-xs md:text-sm font-medium">Outpatient Record Management & Smart Queueing System</p>
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

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="bg-white dark:bg-[#1f2937] border border-slate-300 dark:border-slate-700/70 rounded-2xl p-5 shadow-sm hover:shadow-md transition-all">
                <div class="flex justify-between items-center mb-4">
                    <div class="w-11 h-11 bg-sky-50 dark:bg-sky-500/10 rounded-xl flex items-center justify-center text-sky-600 dark:text-sky-400 text-lg">
                        <i class="fas fa-calendar-day"></i>
                    </div>
                </div>
                <div class="text-3xl font-bold text-slate-900 dark:text-white mb-1 font-mono"><?php echo number_format($today_intake); ?></div>
                <div class="text-slate-400 dark:text-slate-400 text-[0.7rem] font-bold uppercase tracking-wider">Today's Intake</div>
            </div>
            <div class="bg-white dark:bg-[#1f2937] border border-slate-300 dark:border-slate-700/70 rounded-2xl p-5 shadow-sm hover:shadow-md transition-all">
                <div class="flex justify-between items-center mb-4">
                    <div class="w-11 h-11 bg-amber-50 dark:bg-amber-500/10 rounded-xl flex items-center justify-center text-amber-600 dark:text-amber-400 text-lg">
                        <i class="fas fa-clock"></i>
                    </div>
                </div>
                <div class="text-3xl font-bold text-slate-900 dark:text-white mb-1 font-mono"><?php echo number_format($total_waiting); ?></div>
                <div class="text-slate-400 dark:text-slate-400 text-[0.7rem] font-bold uppercase tracking-wider">Waiting</div>
            </div>
            <div class="bg-white dark:bg-[#1f2937] border border-slate-300 dark:border-slate-700/70 rounded-2xl p-5 shadow-sm hover:shadow-md transition-all">
                <div class="flex justify-between items-center mb-4">
                    <div class="w-11 h-11 bg-emerald-50 dark:bg-emerald-500/10 rounded-xl flex items-center justify-center text-emerald-600 dark:text-emerald-400 text-lg">
                        <i class="fas fa-play-circle"></i>
                    </div>
                </div>
                <div class="text-3xl font-bold text-slate-900 dark:text-white mb-1 font-mono"><?php echo number_format($total_in_progress); ?></div>
                <div class="text-slate-400 dark:text-slate-400 text-[0.7rem] font-bold uppercase tracking-wider">In Progress</div>
            </div>
            <div class="bg-white dark:bg-[#1f2937] border border-slate-300 dark:border-slate-700/70 rounded-2xl p-5 shadow-sm hover:shadow-md transition-all">
                <div class="flex justify-between items-center mb-4">
                    <div class="w-11 h-11 bg-indigo-50 dark:bg-indigo-500/10 rounded-xl flex items-center justify-center text-indigo-600 dark:text-indigo-400 text-lg">
                        <i class="fas fa-check-circle"></i>
                    </div>
                </div>
                <div class="text-3xl font-bold text-slate-900 dark:text-white mb-1 font-mono"><?php echo number_format($total_completed); ?></div>
                <div class="text-slate-400 dark:text-slate-400 text-[0.7rem] font-bold uppercase tracking-wider">Completed</div>
            </div>
        </div>

        <section class="bg-white dark:bg-[#1f2937] border border-slate-300 dark:border-slate-700/70 rounded-2xl p-6 mb-8 shadow-sm">
            <div class="flex flex-col sm:flex-row justify-between sm:items-center gap-4 mb-4">
                <div class="flex items-center gap-2 text-slate-800 dark:text-slate-200 font-bold text-sm">
                    <i class="fas fa-layer-group text-sky-500 mr-1"></i>
                    <span>Current Batch Progress</span>
                </div>
                <div class="flex gap-8">
                    <div class="text-right">
                        <div class="text-slate-400 dark:text-slate-400 text-[0.65rem] font-bold uppercase tracking-wider">Current</div>
                        <div class="text-sky-600 dark:text-sky-400 font-extrabold font-mono text-lg">B<?php echo $batch_info['batch_number']; ?></div>
                    </div>
                    <div class="text-right">
                        <div class="text-slate-400 dark:text-slate-400 text-[0.65rem] font-bold uppercase tracking-wider">Next</div>
                        <div class="text-slate-700 dark:text-slate-300 font-bold font-mono">B<?php echo date('H', strtotime('+1 hour')); ?>-<?php echo date('hA', strtotime($batch_info['next_hour'])); ?></div>
                    </div>
                </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-[1fr_auto] gap-5 items-center">
                <div class="bg-slate-100 dark:bg-slate-800 h-2.5 rounded-full overflow-hidden border border-slate-200/60 dark:border-transparent">
                    <div class="bg-emerald-500 h-full rounded-full transition-all duration-300" style="width: <?php echo $batch_progress; ?>%;"></div>
                </div>
                <div class="flex gap-5 text-xs font-medium text-slate-500 dark:text-slate-400">
                    <span><strong class="text-slate-800 dark:text-slate-200 font-mono font-bold"><?php echo $batch_info['current_count']; ?></strong> / 20 Patients</span>
                    <span>Est. <span class="font-mono"><?php echo $batch_info['current_count'] * 5; ?></span> mins</span>
                </div>
            </div>
        </section>

        <div class="flex justify-between items-center mt-8 mb-5">
            <h2 class="text-slate-900 dark:text-white text-lg font-bold flex items-center gap-2">
                <i class="fas fa-clinic-medical text-sky-500"></i> Daily Clinic Status
            </h2>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-4 mb-8">
            <?php foreach ($clinics as $clinic): ?>
            <div class="relative bg-white dark:bg-[#1f2937] border border-slate-300 dark:border-slate-700/70 rounded-tl-2xl rounded-tr-lg rounded-bl-lg rounded-br-lg overflow-hidden shadow-sm hover:shadow-md hover:border-sky-500/80 dark:hover:border-sky-400/80 transition-all duration-200 cursor-pointer h-[105px] group flex flex-col justify-between" 
                 onclick="viewClinicQueue(<?php echo $clinic['id']; ?>, '<?php echo htmlspecialchars($clinic['name']); ?>')">
                
                <div class="p-3.5 flex flex-col justify-between h-full transition-all duration-300 group-hover:opacity-0 group-hover:scale-95">
                    <div class="flex items-start justify-between gap-1.5">
                        <span class="font-extrabold text-slate-800 dark:text-slate-200 text-[0.75rem] uppercase tracking-wide truncate block max-w-[85%]">
                            <?php echo htmlspecialchars($clinic['name']); ?>
                        </span>
                        <?php if ($clinic['waiting_count'] > 0): ?>
                            <span class="w-2 h-2 rounded-full bg-sky-500 animate-pulse shrink-0 mt-0.5"></span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="flex items-baseline justify-between mt-auto">
                        <span class="text-slate-400 text-[0.625rem] font-bold uppercase tracking-wider">In Queue</span>
                        <span class="text-2xl font-black text-sky-500 dark:text-sky-400 font-mono leading-none">
                            <?php echo $clinic['waiting_count']; ?>
                        </span>
                    </div>
                </div>

                <div class="absolute inset-0 bg-slate-900/95 dark:bg-[#111827]/95 p-3 opacity-0 pointer-events-none group-hover:opacity-100 group-hover:pointer-events-auto transition-all duration-200 flex flex-col justify-between z-10 scale-105 group-hover:scale-100 rounded-tl-2xl rounded-tr-lg rounded-bl-lg rounded-br-lg">
                    <div class="text-[0.7rem] font-black text-sky-400 dark:text-sky-400 uppercase tracking-wider truncate border-b border-slate-700/50 pb-1.5">
                        <?php echo htmlspecialchars($clinic['name']); ?>
                    </div>
                    
                    <div class="space-y-1 my-1">
                        <div class="flex justify-between items-center text-[0.68rem]">
                            <span class="text-slate-400 font-medium">In Progress</span>
                            <span class="font-mono font-bold text-white"><?php echo $clinic['in_progress']; ?></span>
                        </div>
                        <div class="flex justify-between items-center text-[0.68rem]">
                            <span class="text-slate-400 font-medium">Completed</span>
                            <span class="font-mono font-bold text-slate-300"><?php echo $clinic['completed']; ?></span>
                        </div>
                        <div class="flex justify-between items-center text-[0.68rem] pt-0.5 border-t border-slate-800">
                            <span class="text-slate-400 font-medium">Est. Wait</span>
                            <span class="font-mono font-bold text-amber-400"><?php echo $clinic['waiting_count'] * 10; ?>m</span>
                        </div>
                    </div>

                    <div class="text-[0.625rem] font-bold text-sky-400 flex items-center justify-center gap-1 bg-sky-500/10 rounded py-1 mt-auto">
                        <span>Open Monitor</span>
                        <i class="fas fa-arrow-right text-[8px]"></i>
                    </div>
                </div>

            </div>
            <?php endforeach; ?>
        </div>

        <div class="flex justify-between items-center mt-8 mb-5">
            <h2 class="text-slate-900 dark:text-white text-lg font-bold flex items-center gap-2">
                <i class="fas fa-history text-sky-500"></i> Recent Activity
            </h2>
            <span class="inline-flex items-center gap-1.5 text-slate-400 dark:text-slate-400 text-xs font-medium bg-slate-100 dark:bg-slate-800 px-2.5 py-1 rounded-full border border-slate-200 dark:border-transparent"><span class="w-1.5 h-1.5 bg-emerald-500 rounded-full animate-pulse"></span>Live Status Active</span>
        </div>

        <div class="bg-white dark:bg-[#1f2937] border border-slate-300 dark:border-slate-700/70 rounded-2xl overflow-hidden shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full border-collapse">
                    <thead>
                        <tr class="bg-slate-50 dark:bg-slate-800/60 text-left border-b border-slate-300 dark:border-slate-700/80 text-slate-400 dark:text-slate-400 text-[0.65rem] uppercase tracking-wider font-bold">
                            <th class="px-5 py-4">Ticket</th>
                            <th class="px-5 py-4">Patient Identity</th>
                            <th class="px-5 py-4">Status</th>
                            <th class="px-5 py-4">Location</th>
                            <th class="px-5 py-4">Time</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-300/80 dark:divide-slate-700/50 text-xs md:text-sm text-slate-700 dark:text-slate-200">
                        <?php if (empty($recent)): ?>
                        <tr>
                            <td colspan="5" class="text-center py-12 text-slate-400 dark:text-slate-400">
                                <i class="fas fa-inbox text-3xl mb-2.5 block text-slate-300 dark:text-slate-600"></i> No recent activity found
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($recent as $activity): ?>
                        <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition-colors duration-150">
                            <td class="px-5 py-3.5"><span class="font-bold text-sky-600 dark:text-sky-400 font-mono text-sm"><?php echo htmlspecialchars($activity['queue_number']); ?></span></td>
                            <td class="px-5 py-3.5 font-semibold text-slate-800 dark:text-slate-100">
                                <div class="flex items-center gap-2">
                                    <?php echo htmlspecialchars($activity['last_name'] . ', ' . $activity['first_name']); ?>
                                    <?php if ($activity['patient_type'] == 'military'): ?>
                                        <i class="fas fa-shield-alt text-rose-500" title="Military Personnel"></i>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="px-5 py-3.5">
                                <?php 
                                    if ($activity['status'] == 'waiting') {
                                        $badgeStyles = 'bg-amber-50 dark:bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-300 dark:border-amber-400/20';
                                    } elseif ($activity['status'] == 'called') {
                                        $badgeStyles = 'bg-sky-50 dark:bg-sky-500/10 text-sky-600 dark:text-sky-400 border border-sky-300 dark:border-sky-400/20';
                                    } elseif ($activity['status'] == 'in-progress') {
                                        $badgeStyles = 'bg-emerald-50 dark:bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-300 dark:border-emerald-400/20';
                                    } else {
                                        $badgeStyles = 'bg-slate-50 dark:bg-slate-800 text-slate-500 dark:text-slate-400 border border-slate-300 dark:border-slate-700';
                                    }
                                ?>
                                <span class="inline-block px-3 py-1 rounded-full text-[0.65rem] font-bold tracking-wide uppercase <?php echo $badgeStyles; ?>">
                                    <?php echo ucfirst(str_replace('-', ' ', $activity['status'])); ?>
                                </span>
                            </td>
                            <td class="px-5 py-3.5 text-slate-500 dark:text-slate-400 font-medium"><?php echo htmlspecialchars($activity['clinic_name']); ?></td>
                            <td class="px-5 py-3.5 text-slate-400 dark:text-slate-400 font-bold font-mono text-xs"><?php echo date('h:i A', strtotime($activity['registered_at'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <div id="clinicQueueModal" class="hidden fixed inset-0 bg-slate-900/60 dark:bg-black/70 z-[2000] items-center justify-center p-4 backdrop-blur-sm">
        <div class="bg-white dark:bg-[#1f2937] border border-slate-300 dark:border-slate-700 rounded-2xl w-full max-w-[800px] max-h-[85vh] overflow-hidden flex flex-col shadow-2xl animate-[modalFadeIn_0.2s_ease]">
            <div class="px-6 py-4 bg-slate-50 dark:bg-slate-800 text-slate-800 dark:text-white flex justify-between items-center border-b border-slate-300 dark:border-slate-700">
                <h3 class="text-base font-bold flex items-center gap-2.5 text-sky-600 dark:text-sky-400">
                    <i class="fas fa-clinic-medical text-lg"></i>
                    <span id="modalClinicName">Loading...</span>
                </h3>
                <button class="w-8 h-8 bg-white dark:bg-slate-700 hover:bg-slate-100 dark:hover:bg-slate-600 rounded-full flex items-center justify-center border border-slate-300 dark:border-slate-600 text-slate-500 dark:text-slate-300 text-sm cursor-pointer transition-colors" onclick="closeModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="overflow-y-auto flex-1" id="modalBody">
                <div id="modalContent" class="text-center py-10"></div>
            </div>
        </div>
    </div>

    <style>
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        @keyframes modalFadeIn { from { opacity: 0; transform: scale(0.97); } to { opacity: 1; transform: scale(1); } }
    </style>

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

        // Top Right Account Profile Management Dropdown Logic Layer
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

        function updateDateTime() {
            const now = new Date();
            const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
            document.getElementById('currentDate').textContent = now.toLocaleDateString('en-US', options);
            document.getElementById('currentTime').textContent = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
        }
        updateDateTime();
        setInterval(updateDateTime, 1000);

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

        function viewClinicQueue(clinicId, clinicName) {
            const modal = document.getElementById('clinicQueueModal');
            const modalClinicName = document.getElementById('modalClinicName');
            const modalContent = document.getElementById('modalBody');
            
            modalClinicName.textContent = clinicName;
            modal.style.display = 'flex';
            
            modalContent.innerHTML = `
                <div class="text-center py-14">
                    <i class="fas fa-spinner fa-pulse text-2xl text-sky-500 mb-3"></i>
                    <p class="text-xs text-slate-400 font-medium">Pulling secure matrix registers...</p>
                </div>
            `;
            
            fetch(`get-clinic-queue.php?clinic_id=${clinicId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayQueueModal(data);
                    } else {
                        modalContent.innerHTML = `
                            <div class="text-center py-14 text-slate-400">
                                <i class="fas fa-exclamation-triangle text-2xl text-rose-500 mb-4 block"></i>
                                <p class="text-xs font-bold">${data.error || 'Failed to load configuration.'}</p>
                            </div>
                        `;
                    }
                })
                .catch(error => {
                    modalContent.innerHTML = `
                        <div class="text-center py-14 text-slate-400">
                            <i class="fas fa-exclamation-triangle text-2xl text-rose-500 mb-4 block"></i>
                            <p class="text-xs font-bold">Network connectivity interruption detected.</p>
                        </div>
                    `;
                });
        }
        
        function displayQueueModal(data) {
            const modalContent = document.getElementById('modalBody');
            const queue = data.queue;
            const stats = data.stats;
            
            if (!queue || queue.length === 0) {
                modalContent.innerHTML = `
                    <div class="text-center py-14 text-slate-400">
                        <i class="fas fa-check-circle text-4xl text-emerald-500 mb-4 block"></i>
                        <p class="text-sm font-bold text-slate-700 dark:text-slate-200 mb-1">Queue Clear</p>
                        <small class="text-xs">No active patients registered.</small>
                    </div>
                `;
                return;
            }
            
            let tableRows = '';
            queue.forEach(patient => {
                let priorityClass = '';
                if (patient.priority_level === 'PR1') priorityClass = 'bg-rose-50 dark:bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-300 dark:border-rose-400/20';
                else if (patient.priority_level === 'PR2') priorityClass = 'bg-amber-50 dark:bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-300 dark:border-amber-400/20';
                else priorityClass = 'bg-emerald-50 dark:bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-300 dark:border-emerald-400/20';
                
                let statusClass = '';
                if (patient.status === 'waiting') statusClass = 'bg-amber-50 dark:bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-300';
                else if (patient.status === 'called') statusClass = 'bg-sky-50 dark:bg-sky-500/10 text-sky-600 dark:text-sky-400 border border-sky-300';
                else if (patient.status === 'in-progress') statusClass = 'bg-emerald-50 dark:bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-300';
                else statusClass = 'bg-slate-50 dark:bg-slate-800 text-slate-400 border border-slate-300';
                
                tableRows += `
                    <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/40 border-b border-slate-300 dark:border-slate-700/50 transition-colors text-xs md:text-sm text-slate-700 dark:text-slate-200">
                        <td class="px-4 py-3"><span class="font-bold text-sky-600 dark:text-sky-400 font-mono">${escapeHtml(patient.queue_number)}</span></td>
                        <td class="px-4 py-3 font-semibold">${escapeHtml(patient.last_name)}, ${escapeHtml(patient.first_name)}</td>
                        <td class="px-4 py-3"><span class="inline-block px-2.5 py-0.5 rounded-full text-[0.65rem] font-bold ${priorityClass}">${escapeHtml(patient.priority_level)}</span></td>
                        <td class="px-4 py-3"><span class="inline-block px-2.5 py-0.5 rounded-full text-[0.65rem] font-bold uppercase tracking-wide ${statusClass}">${escapeHtml(patient.status)}</span></td>
                        <td class="px-4 py-3 font-medium text-slate-500 dark:text-slate-400 font-mono">${patient.waiting_minutes || 0} min</td>
                        <td class="px-4 py-3 font-mono text-xs text-slate-400">${patient.registered_time || '-'}</td>
                    </tr>
                `;
            });
            
            modalContent.innerHTML = `
                <div class="grid grid-cols-4 gap-4 p-4 bg-slate-50 dark:bg-slate-800/50 border-b border-slate-300 dark:border-slate-700 text-center">
                    <div>
                        <div class="text-lg font-bold text-amber-500 font-mono">${stats.waiting}</div>
                        <div class="text-[0.6rem] font-bold uppercase text-slate-400 tracking-wider">Waiting</div>
                    </div>
                    <div>
                        <div class="text-lg font-bold text-sky-500 font-mono">${stats.called}</div>
                        <div class="text-[0.6rem] font-bold uppercase text-slate-400 tracking-wider">Called</div>
                    </div>
                    <div>
                        <div class="text-lg font-bold text-emerald-500 font-mono">${stats.in_progress}</div>
                        <div class="text-[0.6rem] font-bold uppercase text-slate-400 tracking-wider">In Progress</div>
                    </div>
                    <div>
                        <div class="text-lg font-bold text-slate-700 dark:text-slate-300 font-mono">${stats.total_today}</div>
                        <div class="text-[0.6rem] font-bold uppercase text-slate-400 tracking-wider">Total Today</div>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50/70 dark:bg-[#1f2937] text-slate-400 dark:text-slate-400 text-[0.65rem] font-bold uppercase tracking-wider border-b border-slate-300 dark:border-slate-700 sticky top-0">
                                <th class="px-4 py-3.5">Queue #</th>
                                <th class="px-4 py-3.5">Patient</th>
                                <th class="px-4 py-3.5">Priority</th>
                                <th class="px-4 py-3.5">Status</th>
                                <th class="px-4 py-3.5">Wait Time</th>
                                <th class="px-4 py-3.5">Registered</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-300/80 dark:divide-slate-700/50">
                            ${tableRows}
                        </tbody>
                    </table>
                </div>
            `;
        }
        
        function escapeHtml(str) {
            if (!str) return '';
            return str.replace(/[&<>]/g, function(m) {
                if (m === '&') return '&amp;';
                if (m === '<') return '&lt;';
                if (m === '>') return '&gt;';
                return m;
            });
        }
        
        function closeModal() {
            document.getElementById('clinicQueueModal').style.display = 'none';
        }
        
        window.onclick = function(event) {
            const modal = document.getElementById('clinicQueueModal');
            if (event.target === modal) closeModal();
        }
        
        // Anti-Inactivity timeout fallback controls
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
    </script>
</body>
</html>