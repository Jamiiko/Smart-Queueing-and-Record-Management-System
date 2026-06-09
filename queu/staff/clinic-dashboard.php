<?php
// staff/clinic-dashboard.php - Clinic Staff Dashboard
// Camp Evangelista Station Hospital

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/QueueManager.php';

session_start();

// ============================================
// AUTHENTICATION & ROLE-BASED ACCESS CONTROL
// ============================================

// Check if user is logged in
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
// DATABASE CONNECTION (Must be before SessionManager)
// ============================================
$database = new Database();
$db = $database->getConnection();
$queueManager = new QueueManager($db);

// ============================================
// SESSION TIMEOUT CHECK (Now $db exists!)
// ============================================
require_once dirname(__DIR__) . '/includes/SessionManager.php';
$sessionManager = new SessionManager($db);
if (!$sessionManager->checkTimeout()) {
    exit(); // Already redirected to login
}
$sessionManager->logActivity('Viewed clinic dashboard');

// ============================================
// CLINIC SETUP
// ============================================

// Get clinic ID from URL (default to user's clinic if not specified)
$clinic_id = isset($_GET['clinic_id']) ? (int)$_GET['clinic_id'] : ($_SESSION['clinic_id'] ?? 1);

// Check if user has access to this clinic (for non-admin users)
if ($_SESSION['role'] != 'admin') {
    if (!isset($_SESSION['clinic_id']) || $_SESSION['clinic_id'] != $clinic_id) {
        $_SESSION['error'] = "You don't have permission to access this clinic.";
        header('Location: clinic-dashboard.php?clinic_id=' . $_SESSION['clinic_id']);
        exit();
    }
}

// Get clinic info
$query = "SELECT * FROM clinics WHERE id = :id";
$stmt = $db->prepare($query);
$stmt->bindParam(':id', $clinic_id);
$stmt->execute();
$clinic = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$clinic) {
    die("Clinic not found. Please check the clinic ID.");
}

// Handle calling next patient
if (isset($_POST['call_next'])) {
    $queueManager->callNextPatient($clinic_id);
    header('Location: clinic-dashboard.php?clinic_id=' . $clinic_id);
    exit();
}

// Handle patient status update
if (isset($_POST['update_status'])) {
    $query = "UPDATE queue_entries SET status = :status";
    
    if ($_POST['status'] == 'completed') {
        $query .= ", completed_at = NOW()";
    } elseif ($_POST['status'] == 'called') {
        $query .= ", called_at = NOW()";
    }
    
    $query .= " WHERE id = :id";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':status', $_POST['status']);
    $stmt->bindParam(':id', $_POST['queue_id']);
    
    if ($stmt->execute() && $_POST['status'] == 'completed') {
        $queue_query = "SELECT q.patient_id, p.patient_type, q.clinic_id 
                        FROM queue_entries q
                        JOIN patients p ON q.patient_id = p.id
                        WHERE q.id = :queue_id";
        $queue_stmt = $db->prepare($queue_query);
        $queue_stmt->bindParam(':queue_id', $_POST['queue_id']);
        $queue_stmt->execute();
        $queue_data = $queue_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($queue_data && $queue_data['patient_type'] == 'military') {
            $next_result = $queueManager->queueForNextClinic($queue_data['patient_id'], $queue_data['clinic_id']);
            
            if ($next_result['success']) {
                $_SESSION['next_queue'] = "Patient queued for next clinic: " . $next_result['clinic'];
            } elseif (isset($next_result['all_completed']) && $next_result['all_completed']) {
                $_SESSION['next_queue'] = "Patient has completed all clinics!";
            }
        }
    }
    
    header('Location: clinic-dashboard.php?clinic_id=' . $clinic_id);
    exit();
}

// Get current queue
$query = "SELECT q.*, p.first_name, p.last_name, p.date_of_birth,
                 p.patient_type, p.is_pwd, p.is_senior, p.is_pregnant,
                 TIMESTAMPDIFF(MINUTE, q.registered_at, NOW()) as waiting_minutes,
                 HOUR(q.batch_hour) as batch_number,
                 DATE_FORMAT(q.batch_hour, '%h:%i %p') as batch_time,
                 (SELECT COUNT(*) FROM queue_entries 
                  WHERE clinic_id = q.clinic_id 
                  AND status IN ('waiting', 'called') 
                  AND batch_hour = q.batch_hour
                  AND id < q.id) + 1 as position_in_batch
          FROM queue_entries q
          JOIN patients p ON q.patient_id = p.id
          WHERE q.clinic_id = :clinic_id 
          AND q.status IN ('waiting', 'called', 'in-progress')
          AND DATE(q.registered_at) = CURDATE()
          ORDER BY 
            q.batch_hour ASC,
            FIELD(q.priority_level, 'PR1', 'PR2', 'PR3'),
            FIELD(q.status, 'called', 'in-progress', 'waiting'),
            q.registered_at ASC";

$stmt = $db->prepare($query);
$stmt->bindParam(':clinic_id', $clinic_id);
$stmt->execute();
$queue = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get today's stats
$query = "SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
            SUM(CASE WHEN status = 'waiting' THEN 1 ELSE 0 END) as waiting,
            SUM(CASE WHEN status = 'called' THEN 1 ELSE 0 END) as called,
            SUM(CASE WHEN status = 'in-progress' THEN 1 ELSE 0 END) as in_progress,
            AVG(TIMESTAMPDIFF(MINUTE, registered_at, completed_at)) as avg_time
          FROM queue_entries 
          WHERE clinic_id = :clinic_id 
          AND DATE(registered_at) = CURDATE()";

$stmt = $db->prepare($query);
$stmt->bindParam(':clinic_id', $clinic_id);
$stmt->execute();
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// ============================================
// BATCH INFORMATION RETRIEVAL
// ============================================
$batch_info = $queueManager->getCurrentBatch();

// Extract or compute elements for the 4-column single-cell tracker framework
$curr_batch_val = $batch_info['current_batch'] ?? $batch_info['start_time'] ?? date('h:00 A');
$patients_batch_val = $batch_info['patients_count'] ?? $stats['waiting'] ?? 0;
$capacity_hour = $clinic['capacity_per_hour'] ?? 20;
$remaining_slots_val = $batch_info['remaining_slots'] ?? max(0, $capacity_hour - ($stats['total'] ?? 0));
$next_batch_val = $batch_info['next_batch'] ?? $batch_info['end_time'] ?? date('h:00 A', strtotime('+1 hour'));

// Get completed patients today
$query = "SELECT q.*, p.first_name, p.last_name, p.patient_type,
                 DATE_FORMAT(q.completed_at, '%h:%i %p') as completed_time
          FROM queue_entries q
          JOIN patients p ON q.patient_id = p.id
          WHERE q.clinic_id = :clinic_id 
          AND q.status = 'completed'
          AND DATE(q.registered_at) = CURDATE()
          ORDER BY q.completed_at DESC
          LIMIT 10";

$stmt = $db->prepare($query);
$stmt->bindParam(':clinic_id', $clinic_id);
$stmt->execute();
$completed_patients = $stmt->fetchAll(PDO::FETCH_ASSOC);

$completion_rate = 0;
if ($stats['total'] > 0) {
    $completion_rate = round(($stats['completed'] / $stats['total']) * 100, 1);
}
?>

<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($clinic['name']); ?> | Staff Dashboard | Camp Evangelista</title>
    
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
        /* Support style rules for arbitrary modal animation config injections */
        @keyframes modalFadeIn {
            from { opacity: 0; transform: scale(0.96) translateY(-4px); }
            to { opacity: 1; transform: scale(1) translateY(0); }
        }
    </style>
    <meta http-equiv="refresh" content="30">
</head>
<body class="bg-slate-50 dark:bg-[#111827] text-slate-800 dark:text-slate-100 font-sans antialiased min-h-full transition-colors duration-200">

    <aside id="sidebar" class="fixed top-0 left-0 h-screen bg-white dark:bg-[#1f2937] border-r border-slate-300/60 dark:border-slate-700/80 shadow-xl md:shadow-none z-[1000] flex flex-col justify-between overflow-x-hidden transition-all duration-300 ease-in-out group/sidebar -translate-x-full md:translate-x-0 w-[260px] md:w-[80px] md:hover:w-[260px]">
        <div class="flex flex-col justify-between h-full w-full">
            <div>
                <div class="p-4 border-b border-slate-200 dark:border-slate-700/60 mb-5 flex flex-col items-center justify-center min-h-[120px]">
                    <div class="hidden md:flex md:group-hover/sidebar:hidden flex-col items-center justify-center font-extrabold text-xl tracking-wider text-sky-600 dark:text-sky-400 leading-tight select-none">
                        <span>C</span><span>E</span><span>S</span><span>H</span>
                    </div>
                    <div class="flex md:hidden md:group-hover/sidebar:flex flex-col items-center">
                        <img src="../assets/images/logo.png" alt="CESH Logo" class="w-60 h-60 object-contain rounded-xl mb-2" onerror="this.style.display='none'">
                        <h2 class="text-slate-800 dark:text-slate-100 text-sm font-extrabold tracking-tight text-center whitespace-nowrap">4ID Station Hospital</h2>
                        <p class="text-slate-400 dark:text-slate-500 text-[10px] font-bold uppercase tracking-widest text-center whitespace-nowrap mt-1">Camp Evangelista</p>
                    </div>
                </div>
                
                <nav class="px-3 md:group-hover/sidebar:px-4 transition-all duration-200">
                    <ul class="space-y-1.5 list-none p-0">
                        <li>
                            <a href="clinic-dashboard.php" class="flex items-center rounded-xl font-semibold transition-all duration-150 bg-sky-50 dark:bg-sky-500/10 text-sky-600 dark:text-sky-400 border-l-4 border-sky-500 p-3 justify-center md:justify-start gap-0 md:group-hover/sidebar:gap-4 group/link">
                                <div class="w-6 h-6 flex items-center justify-center shrink-0">
                                    <i class="fas fa-desktop text-base"></i>
                                </div>
                                <span class="opacity-100 md:opacity-0 md:group-hover/sidebar:opacity-100 text-xs tracking-wide whitespace-nowrap transition-opacity duration-200 origin-left">Dashboard</span>
                            </a>
                        </li>
                        <li>
                            <a href="registration.php" class="flex items-center rounded-xl font-medium transition-all duration-150 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700/50 p-3 justify-center md:justify-start gap-0 md:group-hover/sidebar:gap-4 group/link">
                                <div class="w-6 h-6 flex items-center justify-center shrink-0">
                                    <i class="fas fa-user-plus text-base text-slate-400 group-hover/link:text-sky-500 transition-colors"></i>
                                </div>
                                <span class="opacity-100 md:opacity-0 md:group-hover/sidebar:opacity-100 text-xs tracking-wide whitespace-nowrap transition-opacity duration-200 origin-left">Registration</span>
                            </a>
                        </li>
                        <li>
                            <a href="patient-queue.php" class="flex items-center rounded-xl font-medium transition-all duration-150 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700/50 p-3 justify-center md:justify-start gap-0 md:group-hover/sidebar:gap-4 group/link">
                                <div class="w-6 h-6 flex items-center justify-center shrink-0">
                                    <i class="fas fa-list text-base text-slate-400 group-hover/link:text-sky-500 transition-colors"></i>
                                </div>
                                <span class="opacity-100 md:opacity-0 md:group-hover/sidebar:opacity-100 text-xs tracking-wide whitespace-nowrap transition-opacity duration-200 origin-left">All Clinics Queue</span>
                            </a>
                        </li>
                        <li>
                            <a href="search-patient.php" class="flex items-center rounded-xl font-medium transition-all duration-150 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700/50 p-3 justify-center md:justify-start gap-0 md:group-hover/sidebar:gap-4 group/link">
                                <div class="w-6 h-6 flex items-center justify-center shrink-0">
                                    <i class="fas fa-search text-base text-slate-400 group-hover/link:text-sky-500 transition-colors"></i>
                                </div>
                                <span class="opacity-100 md:opacity-0 md:group-hover/sidebar:opacity-100 text-xs tracking-wide whitespace-nowrap transition-opacity duration-200 origin-left">Search Patient</span>
                            </a>
                        </li>
                    </ul>
                </nav>
            </div>

            <div class="p-4 border-t border-slate-200 dark:border-slate-700/60 bg-slate-50/50 dark:bg-slate-800/30 flex items-center gap-3 w-full shrink-0">
               <div class="w-10 h-10 rounded-full bg-white dark:bg-slate-700 flex items-center justify-center text-sky-600 dark:text-sky-400 border border-slate-200 dark:border-slate-600 shrink-0 shadow-sm md:shadow-none md:group-hover/sidebar:shadow-sm">
    <i class="fas fa-user-md"></i>
</div>
                <div class="opacity-100 md:opacity-0 md:group-hover/sidebar:opacity-100 transition-opacity duration-200 overflow-hidden select-none">
                    <p class="text-[9px] uppercase tracking-widest text-slate-400 dark:text-slate-500 font-extrabold leading-none">Logged in as</p>
                    <p class="text-xs font-bold text-slate-800 dark:text-slate-200 truncate max-w-[150px] mt-1.5 leading-tight"><?php echo htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username']); ?></p>
                    <p class="text-[10px] font-bold text-sky-600 dark:text-sky-400 uppercase tracking-wider mt-0.5"><?php echo htmlspecialchars($_SESSION['role']); ?></p>
                </div>
            </div>
        </div>
    </aside>

    <main class="min-h-screen ml-0 md:ml-[80px] hover:translate-x-0 transition-all duration-300 px-4 sm:px-8 py-8 lg:pl-12 max-w-[1600px] mx-auto">
        
        <header class="flex flex-col sm:flex-row justify-between sm:items-center mb-8 pb-5 border-b border-slate-200 dark:border-slate-700/80 gap-4">
            <div class="flex items-center gap-4">
                <button id="mobileMenuBtn" class="md:hidden p-2 text-slate-600 dark:text-slate-300 bg-white dark:bg-[#1f2937] border border-slate-300 dark:border-slate-700 rounded-xl shadow-sm">
                    <i class="fas fa-bars text-lg"></i>
                </button>
                <div>
                    <h1 class="text-slate-900 dark:text-white text-2xl font-extrabold tracking-tight flex items-center gap-2">
                        <?php echo htmlspecialchars($clinic['name']); ?>
                    </h1>
                    <p class="text-slate-500 dark:text-slate-400 text-xs font-medium">Staff Dashboard • Manage patient queue and consultations</p>
                </div>
            </div>
            
            <div class="flex items-center justify-between sm:justify-end gap-4 relative">
                <div class="text-right text-xs hidden sm:block">
                    <div class="text-slate-700 dark:text-slate-300 font-bold" id="currentDate"></div>
                    <div class="text-sky-600 dark:text-sky-400 font-bold font-mono text-xs mt-0.5" id="currentTime"></div>
                </div>

                <button id="themeToggleBtn" class="w-11 h-11 flex items-center justify-center bg-white dark:bg-[#1f2937] border border-slate-300 dark:border-slate-700 rounded-xl transition-all shadow-sm text-slate-500 dark:text-amber-400" title="Toggle Adaptive Theme Light/Dark Mode">
                    <i id="themeToggleIcon" class="fas fa-moon text-sm"></i>
                </button>

                <div class="relative">
    <button id="profileMenuBtn" class="w-11 h-11 bg-white dark:bg-[#1f2937] rounded-full flex items-center justify-center text-sky-600 dark:text-sky-400 border border-slate-300 dark:border-slate-700 shadow-sm hover:border-sky-500 dark:hover:border-sky-400 focus:outline-none transition-all duration-150">
        <i class="fas fa-user-md text-lg"></i>
    </button>
    
    <div id="profileDropdown" class="hidden absolute right-0 mt-2.5 w-60 bg-white dark:bg-[#1f2937] border border-slate-300 dark:border-slate-700 rounded-xl shadow-xl z-[1100] animate-[modalFadeIn_0.15s_ease-out]">
        <div class="p-4 border-b border-slate-200 dark:border-slate-700/60 bg-slate-50 dark:bg-slate-800/40 rounded-t-xl">
            <p class="text-xs font-bold text-slate-900 dark:text-white truncate">
                <?php echo htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username']); ?>
            </p>
            <p class="text-[0.65rem] font-bold text-slate-400 uppercase tracking-wider truncate mt-0.5">
                <?php echo htmlspecialchars(ucfirst($_SESSION['role'])); ?>
            </p>
        </div>
        
        <div class="p-1.5 flex flex-col gap-1">
            <a href="profile.php" class="flex items-center gap-2.5 w-full text-left px-3 py-2.5 text-xs font-bold text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800/60 rounded-lg transition-colors">
                <i class="fas fa-user-circle text-sm text-slate-400 dark:text-slate-500"></i>
                <span>My Profile</span>
            </a>

            <a href="../logout.php" onclick="return confirm('Confirm Dashboard Exit?')" class="flex items-center gap-2.5 w-full text-left px-3 py-2.5 text-xs font-bold text-rose-500 hover:bg-rose-50 dark:hover:bg-rose-500/10 rounded-lg transition-colors">
                <i class="fas fa-power-off text-sm"></i>
                <span>Logout Session</span>
            </a>
        </div>
    </div>
</div>
            </div>
        </header>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert bg-rose-50 dark:bg-rose-500/10 border border-rose-300 dark:border-rose-500/30 text-rose-800 dark:text-rose-400 rounded-xl p-4 text-xs font-bold uppercase tracking-wide mb-6 flex items-center gap-2.5 shadow-sm">
                <i class="fas fa-exclamation-circle text-base text-rose-500"></i> <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>
        <?php if (isset($_SESSION['next_queue'])): ?>
            <div class="alert bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-300 dark:border-emerald-500/30 text-emerald-800 dark:text-emerald-400 rounded-xl p-4 text-xs font-bold uppercase tracking-wide mb-6 flex items-center gap-2.5 shadow-sm">
                <i class="fas fa-check-circle text-base text-emerald-500"></i> <?php echo $_SESSION['next_queue']; unset($_SESSION['next_queue']); ?>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4 mb-6">
            <div class="bg-white dark:bg-[#1f2937] border border-slate-300 dark:border-slate-700/70 rounded-xl p-4 shadow-sm flex flex-col items-center justify-center text-center transition-all hover:-translate-y-0.5 hover:shadow-md">
                <div class="w-10 h-10 bg-slate-100 dark:bg-slate-700/50 rounded-xl flex items-center justify-center text-slate-500 dark:text-slate-400 text-lg mb-2"><i class="fas fa-users"></i></div>
                <div class="text-xl font-extrabold text-slate-900 dark:text-white font-mono leading-none"><?php echo $stats['total'] ?? 0; ?></div>
                <div class="text-slate-400 dark:text-slate-500 text-[9px] font-bold uppercase tracking-wider mt-1">Total Today</div>
            </div>
            <div class="bg-white dark:bg-[#1f2937] border border-slate-300 dark:border-slate-700/70 rounded-xl p-4 shadow-sm flex flex-col items-center justify-center text-center transition-all hover:-translate-y-0.5 hover:shadow-md">
                <div class="w-10 h-10 bg-amber-50 dark:bg-amber-500/10 rounded-xl flex items-center justify-center text-amber-500 text-lg mb-2"><i class="fas fa-clock"></i></div>
                <div class="text-xl font-extrabold text-amber-600 dark:text-amber-400 font-mono leading-none"><?php echo $stats['waiting'] ?? 0; ?></div>
                <div class="text-slate-400 dark:text-slate-500 text-[9px] font-bold uppercase tracking-wider mt-1">Waiting</div>
            </div>
            <div class="bg-white dark:bg-[#1f2937] border border-slate-300 dark:border-slate-700/70 rounded-xl p-4 shadow-sm flex flex-col items-center justify-center text-center transition-all hover:-translate-y-0.5 hover:shadow-md">
                <div class="w-10 h-10 bg-sky-50 dark:bg-sky-500/10 rounded-xl flex items-center justify-center text-sky-500 text-lg mb-2"><i class="fas fa-bullhorn"></i></div>
                <div class="text-xl font-extrabold text-sky-600 dark:text-sky-400 font-mono leading-none"><?php echo $stats['called'] ?? 0; ?></div>
                <div class="text-slate-400 dark:text-slate-500 text-[9px] font-bold uppercase tracking-wider mt-1">Called</div>
            </div>
            <div class="bg-white dark:bg-[#1f2937] border border-slate-300 dark:border-slate-700/70 rounded-xl p-4 shadow-sm flex flex-col items-center justify-center text-center transition-all hover:-translate-y-0.5 hover:shadow-md">
                <div class="w-10 h-10 bg-indigo-50 dark:bg-indigo-500/10 rounded-xl flex items-center justify-center text-indigo-500 text-lg mb-2"><i class="fas fa-user-md"></i></div>
                <div class="text-xl font-extrabold text-indigo-600 dark:text-indigo-400 font-mono leading-none"><?php echo $stats['in_progress'] ?? 0; ?></div>
                <div class="text-slate-400 dark:text-slate-500 text-[9px] font-bold uppercase tracking-wider mt-1">In Consult</div>
            </div>
            <div class="bg-white dark:bg-[#1f2937] border border-slate-300 dark:border-slate-700/70 rounded-xl p-4 shadow-sm flex flex-col items-center justify-center text-center transition-all hover:-translate-y-0.5 hover:shadow-md">
                <div class="w-10 h-10 bg-emerald-50 dark:bg-emerald-500/10 rounded-xl flex items-center justify-center text-emerald-500 text-lg mb-2"><i class="fas fa-check-circle"></i></div>
                <div class="text-xl font-extrabold text-emerald-600 dark:text-emerald-400 font-mono leading-none"><?php echo $stats['completed'] ?? 0; ?></div>
                <div class="text-slate-400 dark:text-slate-500 text-[9px] font-bold uppercase tracking-wider mt-1">Completed</div>
            </div>
            <div class="bg-white dark:bg-[#1f2937] border border-slate-300 dark:border-slate-700/70 rounded-xl p-4 shadow-sm flex flex-col items-center justify-center text-center transition-all hover:-translate-y-0.5 hover:shadow-md">
                <div class="w-10 h-10 bg-rose-50 dark:bg-rose-500/10 rounded-xl flex items-center justify-center text-rose-500 text-lg mb-2"><i class="fas fa-stopwatch"></i></div>
                <div class="text-xl font-extrabold text-rose-600 dark:text-rose-400 font-mono leading-none"><?php echo round($stats['avg_time'] ?? 0); ?><span class="text-xs ml-1">m</span></div>
                <div class="text-slate-400 dark:text-slate-500 text-[9px] font-bold uppercase tracking-wider mt-1">Avg Wait</div>
            </div>
        </div>

        <div class="bg-white dark:bg-[#1f2937] border border-slate-300 dark:border-slate-700/70 rounded-2xl shadow-sm p-5 mb-6 transition-colors">
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-6 text-center sm:text-left divide-y lg:divide-y-0 lg:divide-x divide-slate-200 dark:divide-slate-700/60">
                
                <div class="flex items-center gap-3.5 justify-center sm:justify-start pb-4 lg:pb-0">
                    <div class="w-9 h-9 rounded-xl bg-sky-50 dark:bg-sky-500/10 flex items-center justify-center text-sky-600 dark:text-sky-400 shrink-0"><i class="fas fa-hourglass-start"></i></div>
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">Current Batch</p>
                        <p class="font-mono font-extrabold text-sm text-slate-900 dark:text-white mt-0.5"><?php echo htmlspecialchars($curr_batch_val); ?></p>
                    </div>
                </div>

                <div class="flex items-center gap-3.5 justify-center sm:justify-start pt-4 lg:pt-0 lg:pl-6">
                    <div class="w-9 h-9 rounded-xl bg-indigo-50 dark:bg-indigo-500/10 flex items-center justify-center text-indigo-600 dark:text-indigo-400 shrink-0"><i class="fas fa-user-clock"></i></div>
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">Patients Batch</p>
                        <p class="font-mono font-extrabold text-sm text-slate-900 dark:text-white mt-0.5"><?php echo htmlspecialchars($patients_batch_val); ?> Active</p>
                    </div>
                </div>

                <div class="flex items-center gap-3.5 justify-center sm:justify-start pt-4 lg:pt-0 lg:pl-6">
                    <div class="w-9 h-9 rounded-xl bg-amber-50 dark:bg-amber-500/10 flex items-center justify-center text-amber-500 shrink-0"><i class="fas fa-chart-pie"></i></div>
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">Remaining Slots</p>
                        <p class="font-mono font-extrabold text-sm text-amber-600 dark:text-amber-400 mt-0.5"><?php echo htmlspecialchars($remaining_slots_val); ?> Slots Open</p>
                    </div>
                </div>

                <div class="flex items-center gap-3.5 justify-center sm:justify-start pt-4 lg:pt-0 lg:pl-6">
                    <div class="w-9 h-9 rounded-xl bg-slate-50 dark:bg-slate-700/50 flex items-center justify-center text-slate-500 dark:text-slate-400 shrink-0"><i class="fas fa-step-forward"></i></div>
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">Next Batch</p>
                        <p class="font-mono font-extrabold text-sm text-slate-500 dark:text-slate-400 mt-0.5"><?php echo htmlspecialchars($next_batch_val); ?></p>
                    </div>
                </div>

            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
            
            <div class="lg:col-span-2">
                <div class="bg-white dark:bg-[#1f2937] border border-slate-300 dark:border-slate-700/70 rounded-2xl shadow-sm overflow-hidden transition-colors">
                    <div class="p-4 border-b border-slate-200 dark:border-slate-700/60 bg-slate-50/50 dark:bg-slate-800/20 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                        <h3 class="text-xs font-extrabold uppercase text-slate-900 dark:text-white tracking-wider flex items-center gap-2">
                            <i class="fas fa-list-ol text-sky-500 text-sm"></i> Current Active Queue
                        </h3>
                        <div class="flex items-center gap-2">
                            <form method="POST" class="inline">
                                <button type="submit" name="call_next" class="bg-sky-600 hover:bg-sky-500 text-white border border-transparent text-[10px] font-bold uppercase tracking-wider px-3 py-1.5 rounded-lg shadow-sm transition-all flex items-center gap-1.5 cursor-pointer">
                                    <i class="fas fa-bell"></i> Call Next Patient
                                </button>
                            </form>
                            <a href="registration.php" class="bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 border border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-300 text-[10px] font-bold uppercase tracking-wider px-3 py-1.5 rounded-lg transition-all flex items-center gap-1.5">
                                <i class="fas fa-user-plus"></i> Register
                            </a>
                        </div>
                    </div>
                    
                    <div class="p-4 bg-slate-50/30 dark:bg-[#1f2937]">
                        <?php if (empty($queue)): ?>
                            <div class="text-center py-12 text-slate-400 dark:text-slate-500">
                                <i class="fas fa-check-circle text-4xl mb-3 text-slate-300 dark:text-slate-600"></i>
                                <p class="font-bold uppercase tracking-wider text-xs">No patients in queue</p>
                                <small class="text-[10px] opacity-80 mt-1 block">Queue is empty. New patients will appear here when registered.</small>
                            </div>
                        <?php else: ?>
                            <?php foreach ($queue as $patient): 
                                $prColor = $patient['priority_level'] == 'PR1' ? 'border-rose-500 bg-rose-50 dark:bg-rose-500/5' : 
                                          ($patient['priority_level'] == 'PR2' ? 'border-amber-500 bg-amber-50 dark:bg-amber-500/5' : 
                                           'border-emerald-500 bg-white dark:bg-slate-800/40');
                                           
                                $prBadge = $patient['priority_level'] == 'PR1' ? 'bg-rose-500 text-white' : 
                                          ($patient['priority_level'] == 'PR2' ? 'bg-amber-500 text-white' : 
                                           'bg-emerald-500 text-white');
                            ?>
                                <div class="p-4 border border-slate-200 dark:border-slate-700/60 border-l-4 rounded-xl shadow-sm mb-3 transition-all hover:shadow-md $prColor; <?php echo $patient['status'] == 'called' ? 'ring-2 ring-sky-500/50 animate-pulse' : ''; ?>">
                                    <div class="flex flex-col sm:flex-row justify-between sm:items-start lg:items-center gap-3">
                                        
                                        <div class="flex items-start gap-4">
                                            <div class="flex flex-col items-center justify-center shrink-0 mt-1">
                                                <span class="px-2.5 py-1 rounded text-[10px] font-black uppercase tracking-wider shadow-sm <?php echo $prBadge; ?>">
                                                    <?php echo $patient['priority_level']; ?>
                                                </span>
                                            </div>
                                            
                                            <div class="flex flex-col gap-0.5">
                                                <div class="flex items-center gap-2">
                                                    <span class="font-mono font-extrabold text-base text-slate-900 dark:text-white"><?php echo $patient['queue_number']; ?></span>
                                                    <span class="text-[9px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 bg-slate-200 dark:bg-slate-700 px-1.5 py-0.5 rounded">
                                                        Batch <?php echo $patient['batch_number']; ?>:00
                                                    </span>
                                                </div>
                                                
                                                <h4 class="font-bold text-slate-800 dark:text-slate-200 text-sm">
                                                    <?php echo htmlspecialchars($patient['last_name'] . ', ' . $patient['first_name']); ?>
                                                    <?php if ($patient['patient_type'] == 'military'): ?>
                                                        <i class="fas fa-shield-alt text-sky-500 text-[10px] ml-1" title="Military Personnel"></i>
                                                    <?php endif; ?>
                                                </h4>
                                                
                                                <div class="text-[10px] font-medium text-slate-500 dark:text-slate-400 mt-1">
                                                    Age: <?php $dob = new DateTime($patient['date_of_birth']); echo (new DateTime())->diff($dob)->y; ?> yrs | 
                                                    Registered: <?php echo date('h:i A', strtotime($patient['registered_at'])); ?>
                                                </div>
                                                
                                                <div class="flex flex-wrap gap-1 mt-1">
                                                    <?php if ($patient['is_pwd']): ?><span class="text-[9px] bg-slate-200 dark:bg-slate-700 text-slate-600 dark:text-slate-300 px-1.5 py-0.5 rounded font-bold">PWD</span><?php endif; ?>
                                                    <?php if ($patient['is_senior']): ?><span class="text-[9px] bg-slate-200 dark:bg-slate-700 text-slate-600 dark:text-slate-300 px-1.5 py-0.5 rounded font-bold">Senior</span><?php endif; ?>
                                                    <?php if ($patient['is_pregnant']): ?><span class="text-[9px] bg-slate-200 dark:bg-slate-700 text-slate-600 dark:text-slate-300 px-1.5 py-0.5 rounded font-bold">Pregnant</span><?php endif; ?>
                                                </div>
                                                
                                                <div class="text-[10px] text-slate-500 dark:text-slate-400 font-medium mt-1">
                                                    <i class="fas fa-clock mr-1 text-sky-500"></i> Waiting: <?php echo $patient['waiting_minutes']; ?> mins | <span class="font-bold">Pos: <?php echo $patient['position_in_batch']; ?></span>
                                                </div>
                                                
                                                <div class="flex items-center gap-2 mt-2">
                                                    <?php 
                                                        $statusDisplay = ucfirst($patient['status']);
                                                        $statusBg = $patient['status'] == 'completed' ? 'bg-emerald-100 dark:bg-emerald-500/20 text-emerald-700 dark:text-emerald-400' : 
                                                                  ($patient['status'] == 'in-progress' ? 'bg-indigo-100 dark:bg-indigo-500/20 text-indigo-700 dark:text-indigo-400' : 
                                                                   'bg-amber-100 dark:bg-amber-500/20 text-amber-700 dark:text-amber-400');
                                                    ?>
                                                    <span class="px-2 py-0.5 rounded text-[9px] font-bold uppercase tracking-wider border border-transparent <?php echo $statusBg; ?>">
                                                        <?php echo $statusDisplay; ?>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="flex items-center sm:justify-end gap-1.5 shrink-0 mt-3 sm:mt-0 bg-slate-100 dark:bg-slate-800/80 p-1.5 rounded-lg border border-slate-200 dark:border-slate-700 h-max">
                                            
                                            <form method="POST" class="inline m-0">
                                                <input type="hidden" name="queue_id" value="<?php echo $patient['id']; ?>">
                                                <input type="hidden" name="status" value="called">
                                                <button type="submit" name="update_status" 
                                                    class="w-8 h-8 rounded-md flex items-center justify-center transition-all <?php echo $patient['status'] == 'called' ? 'bg-slate-200 dark:bg-slate-700 text-slate-400 cursor-not-allowed' : 'bg-sky-100 hover:bg-sky-500 dark:bg-sky-500/20 dark:hover:bg-sky-500 text-sky-600 dark:text-sky-400 hover:text-white cursor-pointer'; ?>" 
                                                    <?php echo $patient['status'] == 'called' ? 'disabled' : ''; ?> title="Call Patient">
                                                    <i class="fas fa-bullhorn text-xs"></i>
                                                </button>
                                            </form>
                                            
                                            <form method="POST" class="inline m-0">
                                                <input type="hidden" name="queue_id" value="<?php echo $patient['id']; ?>">
                                                <input type="hidden" name="status" value="in-progress">
                                                <button type="submit" name="update_status" 
                                                    class="w-8 h-8 rounded-md flex items-center justify-center transition-all <?php echo $patient['status'] == 'in-progress' ? 'bg-slate-200 dark:bg-slate-700 text-slate-400 cursor-not-allowed' : 'bg-indigo-100 hover:bg-indigo-500 dark:bg-indigo-500/20 dark:hover:bg-indigo-500 text-indigo-600 dark:text-indigo-400 hover:text-white cursor-pointer'; ?>" 
                                                    <?php echo $patient['status'] == 'in-progress' ? 'disabled' : ''; ?> title="Start Consult">
                                                    <i class="fas fa-play text-xs"></i>
                                                </button>
                                            </form>
                                            
                                            <form method="POST" class="inline m-0">
                                                <input type="hidden" name="queue_id" value="<?php echo $patient['id']; ?>">
                                                <input type="hidden" name="status" value="completed">
                                                <button type="submit" name="update_status" 
                                                    class="w-8 h-8 rounded-md flex items-center justify-center transition-all <?php echo $patient['status'] == 'completed' ? 'bg-slate-200 dark:bg-slate-700 text-slate-400 cursor-not-allowed' : 'bg-emerald-100 hover:bg-emerald-500 dark:bg-emerald-500/20 dark:hover:bg-emerald-500 text-emerald-600 dark:text-emerald-400 hover:text-white cursor-pointer'; ?>" 
                                                    <?php echo $patient['status'] == 'completed' ? 'disabled' : ''; ?> title="Complete">
                                                    <i class="fas fa-check text-xs"></i>
                                                </button>
                                            </form>
                                            
                                        </div>
                                        
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="lg:col-span-1 space-y-6">
                
                <div class="bg-white dark:bg-[#1f2937] border border-slate-300 dark:border-slate-700/70 rounded-2xl shadow-sm overflow-hidden transition-colors">
                    <div class="p-4 border-b border-slate-200 dark:border-slate-700/60 bg-slate-50/50 dark:bg-slate-800/20">
                        <h3 class="text-xs font-extrabold uppercase text-slate-900 dark:text-white tracking-wider flex items-center gap-2">
                            <i class="fas fa-bolt text-amber-500 text-sm"></i> Quick Actions
                        </h3>
                    </div>
                    <div class="p-4 flex flex-col gap-2.5">
                        <a href="search-patient.php" class="w-full bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 border border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-300 text-[10px] font-bold uppercase tracking-wider px-4 py-2.5 rounded-xl transition-all flex items-center justify-center gap-2">
                            <i class="fas fa-search"></i> Search Patient Directory
                        </a>
                        <a href="patient-queue.php" class="w-full bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 border border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-300 text-[10px] font-bold uppercase tracking-wider px-4 py-2.5 rounded-xl transition-all flex items-center justify-center gap-2">
                            <i class="fas fa-list"></i> View All Clinics Tracker
                        </a>
                        <button onclick="location.reload()" class="w-full bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 border border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-300 text-[10px] font-bold uppercase tracking-wider px-4 py-2.5 rounded-xl transition-all flex items-center justify-center gap-2">
                            <i class="fas fa-sync-alt"></i> Manual Refresh Interface
                        </button>
                    </div>
                </div>

                <div class="bg-white dark:bg-[#1f2937] border border-slate-300 dark:border-slate-700/70 rounded-2xl shadow-sm overflow-hidden transition-colors">
                    <div class="p-4 border-b border-slate-200 dark:border-slate-700/60 bg-slate-50/50 dark:bg-slate-800/20">
                        <h3 class="text-xs font-extrabold uppercase text-slate-900 dark:text-white tracking-wider flex items-center gap-2">
                            <i class="fas fa-info-circle text-sky-500 text-sm"></i> Clinic Information
                        </h3>
                    </div>
                    <div class="p-4 space-y-3 text-xs">
                        <div class="flex justify-between items-center pb-2 border-b border-slate-100 dark:border-slate-700/50">
                            <span class="text-slate-500 dark:text-slate-400 font-bold">Capacity</span>
                            <span class="font-mono font-bold text-slate-800 dark:text-slate-200"><?php echo $clinic['capacity_per_hour']; ?> patients/hour</span>
                        </div>
                        <div class="flex justify-between items-center pb-2 border-b border-slate-100 dark:border-slate-700/50">
                            <span class="text-slate-500 dark:text-slate-400 font-bold">Current Load</span>
                            <span class="font-mono font-bold text-slate-800 dark:text-slate-200"><?php echo $stats['waiting']; ?> waiting</span>
                        </div>
                        <div class="flex justify-between items-center pb-2 border-b border-slate-100 dark:border-slate-700/50">
                            <span class="text-slate-500 dark:text-slate-400 font-bold">Completion Rate</span>
                            <span class="font-mono font-bold text-emerald-600 dark:text-emerald-400"><?php echo $completion_rate; ?>%</span>
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-[#1f2937] border border-slate-300 dark:border-slate-700/70 rounded-2xl shadow-sm overflow-hidden transition-colors">
                    <div class="p-4 border-b border-slate-200 dark:border-slate-700/60 bg-slate-50/50 dark:bg-slate-800/20">
                        <h3 class="text-xs font-extrabold uppercase text-slate-900 dark:text-white tracking-wider flex items-center gap-2">
                            <i class="fas fa-check-double text-emerald-500 text-sm"></i> Recently Cleared (<?php echo count($completed_patients); ?>)
                        </h3>
                    </div>
                    
                    <div class="p-0">
                        <?php if (empty($completed_patients)): ?>
                            <div class="p-6 text-center text-slate-400 text-xs font-bold uppercase tracking-wider">
                                No completions recorded yet.
                            </div>
                        <?php else: ?>
                            <ul class="divide-y divide-slate-100 dark:divide-slate-700/50 list-none m-0 p-0">
                                <?php foreach ($completed_patients as $completed): ?>
                                    <li class="p-3 flex justify-between items-center hover:bg-slate-50 dark:hover:bg-slate-800/30 transition-colors">
                                        <div class="flex items-center gap-2.5">
                                            <span class="font-mono font-bold text-[10px] text-sky-600 dark:text-sky-400 bg-sky-50 dark:bg-sky-500/10 px-1.5 py-0.5 rounded border border-sky-100 dark:border-sky-500/20">
                                                <?php echo $completed['queue_number']; ?>
                                            </span>
                                            <span class="text-xs font-bold text-slate-800 dark:text-slate-200">
                                                <?php echo htmlspecialchars($completed['last_name'] . ', ' . substr($completed['first_name'], 0, 1) . '.'); ?>
                                            </span>
                                        </div>
                                        <div class="text-[10px] font-mono text-slate-400 font-medium">
                                            <?php echo $completed['completed_time']; ?>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
        </div>

    </main>

    <script>
        // System Hardware Integration Clock Realtime Module
        function updateDateTime() {
            const now = new Date();
            const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
            document.getElementById('currentDate').textContent = now.toLocaleDateString('en-US', options);
            document.getElementById('currentTime').textContent = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
        }
        updateDateTime();
        setInterval(updateDateTime, 1000);

        // Auto-dismiss alerts safely
        setTimeout(() => {
            document.querySelectorAll('.alert').forEach(alert => {
                alert.style.transition = 'all 0.4s ease';
                alert.style.opacity = '0';
                alert.style.transform = 'translateY(-10px)';
                setTimeout(() => alert.remove(), 400);
            });
        }, 5000);

        // Responsive Mobile Left Drawer Toggles
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

        // Dynamic Profile Menu Panel Dropdowns
        const profileMenuBtn = document.getElementById('profileMenuBtn');
        const profileDropdown = document.getElementById('profileDropdown');
        if (profileMenuBtn && profileDropdown) {
            profileMenuBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                profileDropdown.classList.toggle('hidden');
            });
            document.addEventListener('click', () => profileDropdown.classList.add('hidden'));
        }

        // Light/Dark System Theme Matrix Rules
        const themeToggleBtn = document.getElementById('themeToggleBtn');
        const themeToggleIcon = document.getElementById('themeToggleIcon');
        
        if (localStorage.getItem('theme') === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark'); 
            if(themeToggleIcon) themeToggleIcon.className = 'fas fa-sun text-sm text-amber-400';
        } else {
            if(themeToggleIcon) themeToggleIcon.className = 'fas fa-moon text-sm text-slate-500';
        }
        
        if(themeToggleBtn) {
            themeToggleBtn.addEventListener('click', () => {
                if (document.documentElement.classList.contains('dark')) {
                    document.documentElement.classList.remove('dark'); 
                    localStorage.setItem('theme', 'light'); 
                    if(themeToggleIcon) themeToggleIcon.className = 'fas fa-moon text-sm text-slate-500';
                } else {
                    document.documentElement.classList.add('dark'); 
                    localStorage.setItem('theme', 'dark'); 
                    if(themeToggleIcon) themeToggleIcon.className = 'fas fa-sun text-sm text-amber-400';
                }
            });
        }

        // Auto page refresh (30 seconds)
        setTimeout(function() {
            window.location.reload();
        }, 30000);
        
        // ============================================
        // AUTO-LOGOUT FRAMEWORK FOR INACTIVITY
        // ============================================
        const INACTIVITY_TIMEOUT = 30 * 60 * 1000; // 30 mins
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
            }).catch(err => console.log('Heartbeat dropped:', err));
        }

        function logoutUser() {
            const logoutMsg = document.createElement('div');
            logoutMsg.innerHTML = `
                <div style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; 
                            background: rgba(0,0,0,0.8); z-index: 9999; display: flex; 
                            align-items: center; justify-content: center;">
                    <div style="background: white; padding: 30px; border-radius: 16px; text-align: center; max-width: 400px; color: #1e293b;">
                        <i class="fas fa-clock" style="font-size: 48px; color: #FF6F61; margin-bottom: 20px;"></i>
                        <h3 style="font-weight:800; font-size:1.25rem;">Session Expired</h3>
                        <p style="font-size:0.875rem; margin-top:4px;">You have been logged out due to inactivity.</p>
                        <div style="margin-top: 20px;">
                            <p style="font-size:0.75rem; color:#64748b;">Redirecting to login page...</p>
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
                            background: rgba(0,0,0,0.5); z-index: 10000; display: flex; 
                            align-items: center; justify-content: center;">
                    <div style="background: white; padding: 30px; border-radius: 16px; text-align: center; max-width: 400px; box-shadow: 0 4px 20px rgba(0,0,0,0.2); color: #1e293b;">
                        <i class="fas fa-hourglass-half" style="font-size: 48px; color: #FFB84D; margin-bottom: 20px;"></i>
                        <h3 style="font-weight:800; font-size:1.25rem;">Session About to Expire</h3>
                        <p style="font-size:0.875rem; margin-top:4px;">You will be logged out due to inactivity.</p>
                        <p id="countdownText" style="font-size: 24px; font-weight: bold; margin: 15px 0;">2:00</p>
                        <button onclick="keepSessionAlive()" style="background: #0284c7; color: white; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer; font-weight: 600; font-size:0.875rem;">
                            Keep Session Alive
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
            fetch('heartbeat.php', {
                method: 'POST',
                credentials: 'same-origin'
            }).then(function() {
                resetInactivityTimer();
            }).catch(function(err) {
                console.log('Heartbeat link dropped:', err);
                resetInactivityTimer();
            });
        }

        function hideWarningModal() {
            const modal = document.getElementById('sessionWarningModal');
            if (modal) modal.remove();
        }

        const events = ['mousedown', 'mousemove', 'keypress', 'scroll', 'touchstart', 'click', 'keydown'];
        events.forEach(function(event) {
            document.addEventListener(event, resetInactivityTimer, false);
        });

        resetInactivityTimer();
        setInterval(function() {
            if (!warningShown) sendHeartbeat();
        }, 5 * 60 * 1000);
    </script>
</body>
</html>