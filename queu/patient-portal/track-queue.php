<?php
// patient-portal/track-queue.php - Track Queue Status with Medical Journey Roadmap
// Camp Evangelista Station Hospital

require_once dirname(__DIR__) . '/config/database.php';

$database = new Database();
$db = $database->getConnection();

$queue_info = null;
$error = '';
$searched = false;
$search_term = '';
$all_clinics = [];
$completed_clinics = [];
$pending_clinics = [];
$current_clinic = null;

// ============================================
// HELPER FUNCTIONS
// ============================================

/**
 * Get all active clinics
 */
function getAllClinics($db) {
    $query = "SELECT id, name, description, capacity_per_hour, clinic_order 
              FROM clinics WHERE is_active = 1 
              ORDER BY clinic_order ASC, id ASC";
    $stmt = $db->prepare($query);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get completed clinics for a patient today
 */
function getCompletedClinics($db, $patient_id) {
    $query = "SELECT DISTINCT q.clinic_id, c.name, q.status, q.completed_at
              FROM queue_entries q
              JOIN clinics c ON q.clinic_id = c.id
              WHERE q.patient_id = :patient_id 
              AND q.status = 'completed'
              AND DATE(q.registered_at) = CURDATE()
              ORDER BY q.completed_at ASC";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':patient_id', $patient_id);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get current/in-progress clinic
 */
function getCurrentClinic($db, $patient_id) {
    $query = "SELECT q.clinic_id, c.name, q.status, q.called_at, q.registered_at
              FROM queue_entries q
              JOIN clinics c ON q.clinic_id = c.id
              WHERE q.patient_id = :patient_id 
              AND q.status IN ('called', 'in-progress')
              AND DATE(q.registered_at) = CURDATE()
              ORDER BY q.registered_at DESC
              LIMIT 1";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':patient_id', $patient_id);
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Get pending clinics (patient is registered but not yet completed)
 */
function getPendingClinics($db, $patient_id, $all_clinic_ids, $completed_ids, $current_clinic_id = null) {
    $pending = [];
    foreach ($all_clinic_ids as $clinic_id) {
        if (in_array($clinic_id, $completed_ids)) {
            continue;
        }
        if ($current_clinic_id && $clinic_id == $current_clinic_id) {
            continue;
        }
        
        // Check if patient is registered for this clinic
        $query = "SELECT q.status, q.queue_number, q.priority_level,
                         (SELECT COUNT(*) + 1 FROM queue_entries 
                          WHERE clinic_id = q.clinic_id 
                          AND status IN ('waiting', 'called')
                          AND DATE(registered_at) = CURDATE()
                          AND registered_at < q.registered_at) as position
                  FROM queue_entries q
                  WHERE q.patient_id = :patient_id 
                  AND q.clinic_id = :clinic_id
                  AND DATE(q.registered_at) = CURDATE()";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':patient_id', $patient_id);
        $stmt->bindParam(':clinic_id', $clinic_id);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            $pending[] = [
                'clinic_id' => $clinic_id,
                'status' => $result['status'],
                'queue_number' => $result['queue_number'],
                'position' => $result['position'] ?? '?'
            ];
        } else {
            // Not yet registered - will be auto-queued after completing current
            $pending[] = [
                'clinic_id' => $clinic_id,
                'status' => 'pending',
                'queue_number' => null,
                'position' => null
            ];
        }
    }
    return $pending;
}

// ============================================
// SEARCH LOGIC
// ============================================

// Check if token is provided in URL (from QR code or direct link)
if (isset($_GET['token']) && !empty($_GET['token'])) {
    $searched = true;
    $search_term = trim($_GET['token']);
    
    $query = "SELECT q.*, p.id as patient_id, p.first_name, p.last_name, p.patient_type, p.is_pwd, p.is_senior, p.is_pregnant, c.name as clinic_name,
                     TIMESTAMPDIFF(MINUTE, q.registered_at, NOW()) as waiting_minutes,
                     (SELECT COUNT(*) + 1 FROM queue_entries 
                      WHERE clinic_id = q.clinic_id 
                      AND status IN ('waiting', 'called')
                      AND DATE(registered_at) = CURDATE()
                      AND registered_at < q.registered_at) as position_in_queue
              FROM queue_entries q
              JOIN patients p ON q.patient_id = p.id
              JOIN clinics c ON q.clinic_id = c.id
              WHERE q.transaction_token = :token";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':token', $search_term);
    $stmt->execute();
    
    if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $queue_info = $row;
        
        // Build medical journey roadmap for military patients
        if ($queue_info['patient_type'] == 'military') {
            $all_clinics = getAllClinics($db);
            $completed_clinics = getCompletedClinics($db, $queue_info['patient_id']);
            $current_clinic = getCurrentClinic($db, $queue_info['patient_id']);
            
            $completed_ids = array_column($completed_clinics, 'clinic_id');
            $all_clinic_ids = array_column($all_clinics, 'id');
            $current_clinic_id = $current_clinic ? $current_clinic['clinic_id'] : null;
            
            $pending_clinics = getPendingClinics($db, $queue_info['patient_id'], $all_clinic_ids, $completed_ids, $current_clinic_id);
        }
    } else {
        $error = 'Invalid tracking token. Please check your token and try again.';
    }
}
// Check if queue number is provided in URL
elseif (isset($_GET['q']) && !empty($_GET['q'])) {
    $searched = true;
    $search_term = trim($_GET['q']);
    
    $query = "SELECT q.*, p.id as patient_id, p.first_name, p.last_name, p.patient_type, p.is_pwd, p.is_senior, p.is_pregnant, c.name as clinic_name,
                     TIMESTAMPDIFF(MINUTE, q.registered_at, NOW()) as waiting_minutes,
                     (SELECT COUNT(*) + 1 FROM queue_entries 
                      WHERE clinic_id = q.clinic_id 
                      AND status IN ('waiting', 'called')
                      AND DATE(registered_at) = CURDATE()
                      AND registered_at < q.registered_at) as position_in_queue
              FROM queue_entries q
              JOIN patients p ON q.patient_id = p.id
              JOIN clinics c ON q.clinic_id = c.id
              WHERE q.queue_number = :queue_number";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':queue_number', $search_term);
    $stmt->execute();
    
    if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $queue_info = $row;
        
        // Build medical journey roadmap for military patients
        if ($queue_info['patient_type'] == 'military') {
            $all_clinics = getAllClinics($db);
            $completed_clinics = getCompletedClinics($db, $queue_info['patient_id']);
            $current_clinic = getCurrentClinic($db, $queue_info['patient_id']);
            
            $completed_ids = array_column($completed_clinics, 'clinic_id');
            $all_clinic_ids = array_column($all_clinics, 'id');
            $current_clinic_id = $current_clinic ? $current_clinic['clinic_id'] : null;
            
            $pending_clinics = getPendingClinics($db, $queue_info['patient_id'], $all_clinic_ids, $completed_ids, $current_clinic_id);
        }
    } else {
        $error = 'Queue number not found. Please check and try again.';
    }
}
// Handle POST form submission
elseif (isset($_POST['track'])) {
    $searched = true;
    $search_term = trim($_POST['search_term']);
    
    // Search by both queue number AND transaction token
    $query = "SELECT q.*, p.id as patient_id, p.first_name, p.last_name, p.patient_type, p.is_pwd, p.is_senior, p.is_pregnant, c.name as clinic_name,
                     TIMESTAMPDIFF(MINUTE, q.registered_at, NOW()) as waiting_minutes,
                     (SELECT COUNT(*) + 1 FROM queue_entries 
                      WHERE clinic_id = q.clinic_id 
                      AND status IN ('waiting', 'called')
                      AND DATE(registered_at) = CURDATE()
                      AND registered_at < q.registered_at) as position_in_queue
              FROM queue_entries q
              JOIN patients p ON q.patient_id = p.id
              JOIN clinics c ON q.clinic_id = c.id
              WHERE q.queue_number = :search_term OR q.transaction_token = :search_term";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':search_term', $search_term);
    $stmt->execute();
    
    if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $queue_info = $row;
        
        // Build medical journey roadmap for military patients
        if ($queue_info['patient_type'] == 'military') {
            $all_clinics = getAllClinics($db);
            $completed_clinics = getCompletedClinics($db, $queue_info['patient_id']);
            $current_clinic = getCurrentClinic($db, $queue_info['patient_id']);
            
            $completed_ids = array_column($completed_clinics, 'clinic_id');
            $all_clinic_ids = array_column($all_clinics, 'id');
            $current_clinic_id = $current_clinic ? $current_clinic['clinic_id'] : null;
            
            $pending_clinics = getPendingClinics($db, $queue_info['patient_id'], $all_clinic_ids, $completed_ids, $current_clinic_id);
        }
    } else {
        $error = 'No record found. Please check your queue number or token and try again.';
    }
}

// Get estimated wait times for all clinics
$query = "SELECT c.id, c.name, c.capacity_per_hour,
                 COUNT(CASE WHEN q.status = 'waiting' THEN 1 END) as waiting_count,
                 COUNT(CASE WHEN q.status = 'in-progress' THEN 1 END) as in_progress_count,
                 COUNT(CASE WHEN q.status = 'completed' AND DATE(q.registered_at) = CURDATE() THEN 1 END) as completed_today
          FROM clinics c
          LEFT JOIN queue_entries q ON c.id = q.clinic_id AND DATE(q.registered_at) = CURDATE()
          WHERE c.is_active = 1
          GROUP BY c.id
          ORDER BY c.name";
$clinics = $db->query($query)->fetchAll(PDO::FETCH_ASSOC);

// Calculate journey progress percentage
$journey_progress = 0;
if ($queue_info && $queue_info['patient_type'] == 'military' && !empty($all_clinics)) {
    $total_clinics = count($all_clinics);
    $completed_count = count($completed_clinics);
    if ($total_clinics > 0) {
        $journey_progress = round(($completed_count / $total_clinics) * 100);
    }
}
?>

<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Track Queue | Patient Portal | Camp Evangelista Hospital</title>
    
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
                    },
                    animation: {
                        'spin-slow': 'spin 3s linear infinite',
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-slate-50 dark:bg-[#111827] text-slate-800 dark:text-slate-100 font-sans antialiased min-h-full flex flex-col transition-colors duration-200">

    <header class="bg-white dark:bg-[#1f2937] border-b border-slate-300/80 dark:border-slate-700/80 sticky top-0 z-50 shadow-sm transition-colors duration-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <img src="../assets/images/logo.png" alt="CESH Logo" class="h-10 w-10 object-contain hidden sm:block" onerror="this.style.display='none'">
                <div>
                    <h1 class="text-sky-600 dark:text-sky-400 font-extrabold text-base md:text-lg tracking-tight leading-none">4ID Station Hospital</h1>
                    <p class="text-slate-500 dark:text-slate-400 text-[10px] font-bold uppercase tracking-widest mt-0.5">Camp Evangelista</p>
                </div>
            </div>
            
            <div class="flex items-center gap-4">
                <span class="bg-sky-50 dark:bg-sky-500/10 text-sky-600 dark:text-sky-400 px-3 py-1.5 rounded-full text-[10px] font-bold uppercase tracking-wider border border-sky-100 dark:border-sky-500/20 hidden sm:flex items-center gap-1.5">
                    <i class="fas fa-broadcast-tower"></i> Live Monitor
                </span>
                
                <button id="themeToggleBtn" class="w-9 h-9 flex items-center justify-center bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl transition-all shadow-sm text-slate-500 dark:text-amber-400 focus:outline-none hover:bg-slate-200 dark:hover:bg-slate-700" title="Toggle Visual Mode">
                    <i id="themeToggleIcon" class="fas fa-moon text-sm"></i>
                </button>
            </div>
        </div>
    </header>

    <main class="flex-grow max-w-5xl mx-auto w-full px-4 sm:px-6 py-8 sm:py-12">
        
        <section class="bg-white dark:bg-[#1f2937] border border-slate-300 dark:border-slate-700/70 rounded-2xl shadow-sm p-6 sm:p-8 mb-8 text-center transition-colors">
            <div class="w-16 h-16 bg-sky-50 dark:bg-sky-500/10 text-sky-500 rounded-full flex items-center justify-center text-2xl mx-auto mb-4">
                <i class="fas fa-search-location"></i>
            </div>
            <h2 class="text-xl sm:text-2xl font-extrabold text-slate-900 dark:text-white mb-2 tracking-tight">Locate Your Queue</h2>
            <p class="text-slate-500 dark:text-slate-400 text-xs sm:text-sm font-medium max-w-xl mx-auto mb-6">Enter your designated queue number or transaction tracking token to monitor your real-time position and estimated consultation wait time.</p>
            
            <form method="POST" action="" class="max-w-2xl mx-auto">
                <div class="flex flex-col sm:flex-row items-center gap-3">
                    <div class="relative flex-grow w-full">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                            <i class="fas fa-ticket-alt text-slate-400 dark:text-slate-500"></i>
                        </div>
                        <input type="text" name="search_term" 
                            class="w-full pl-11 pr-4 py-3.5 bg-slate-50 dark:bg-[#111827] border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white rounded-xl focus:outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500 text-sm font-bold tracking-wide transition-all shadow-inner" 
                            placeholder="e.g., M-09-001 or TXN-1234..." 
                            value="<?php echo isset($_POST['search_term']) ? htmlspecialchars($_POST['search_term']) : (isset($_GET['q']) ? htmlspecialchars($_GET['q']) : (isset($_GET['token']) ? htmlspecialchars($_GET['token']) : '')); ?>" 
                            required>
                    </div>
                    <button type="submit" name="track" class="w-full sm:w-auto px-6 py-3.5 bg-sky-600 hover:bg-sky-500 text-white rounded-xl font-bold uppercase tracking-wider text-xs flex items-center justify-center gap-2 shadow-sm transition-colors shrink-0">
                        <i class="fas fa-satellite-dish"></i> Track
                    </button>
                </div>
            </form>
        </section>

        <?php if ($searched && !$queue_info && !$error): ?>
            <div class="bg-sky-50 dark:bg-sky-500/10 border border-sky-300 dark:border-sky-500/30 text-sky-800 dark:text-sky-400 rounded-xl p-4 text-xs font-bold tracking-wide mb-8 flex items-center gap-3 shadow-sm animate-pulse">
                <i class="fas fa-info-circle text-base text-sky-500"></i> Insert your tracking parameters above to scan the registry.
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="bg-rose-50 dark:bg-rose-500/10 border border-rose-300 dark:border-rose-500/30 text-rose-800 dark:text-rose-400 rounded-xl p-4 text-xs font-bold tracking-wide mb-8 flex items-center gap-3 shadow-sm">
                <i class="fas fa-exclamation-triangle text-base text-rose-500"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <?php if ($queue_info): ?>
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-10">
                
                <div class="lg:col-span-1 bg-white dark:bg-[#1f2937] border border-slate-300 dark:border-slate-700/70 rounded-2xl shadow-sm overflow-hidden flex flex-col transition-colors">
                    <div class="p-6 flex-grow flex flex-col items-center justify-center text-center border-b border-slate-200 dark:border-slate-700/60 bg-slate-50/50 dark:bg-[#1f2937]">
                        <span class="text-[10px] font-bold uppercase tracking-widest text-slate-400 mb-2">Token Identifier</span>
                        <h2 class="text-4xl sm:text-5xl font-extrabold font-mono text-sky-600 dark:text-sky-400 tracking-tight leading-none mb-4"><?php echo htmlspecialchars($queue_info['queue_number']); ?></h2>
                        
                        <?php 
                            $status = $queue_info['status'];
                            $badge_class = '';
                            $icon_class = '';
                            
                            if ($status == 'waiting') {
                                $badge_class = 'bg-amber-100 dark:bg-amber-500/20 text-amber-700 dark:text-amber-400 border-amber-200 dark:border-amber-500/30';
                                $icon_class = 'fa-hourglass-half';
                            } elseif ($status == 'called') {
                                $badge_class = 'bg-sky-100 dark:bg-sky-500/20 text-sky-700 dark:text-sky-400 border-sky-200 dark:border-sky-500/30 animate-pulse';
                                $icon_class = 'fa-bullhorn';
                            } elseif ($status == 'in-progress' || $status == 'in_progress') {
                                $badge_class = 'bg-indigo-100 dark:bg-indigo-500/20 text-indigo-700 dark:text-indigo-400 border-indigo-200 dark:border-indigo-500/30';
                                $icon_class = 'fa-spinner fa-spin';
                            } elseif ($status == 'completed') {
                                $badge_class = 'bg-emerald-100 dark:bg-emerald-500/20 text-emerald-700 dark:text-emerald-400 border-emerald-200 dark:border-emerald-500/30';
                                $icon_class = 'fa-check-circle';
                            } else {
                                $badge_class = 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 border-slate-200 dark:border-slate-700';
                                $icon_class = 'fa-info-circle';
                            }
                        ?>
                        <span class="inline-flex items-center gap-1.5 px-4 py-1.5 rounded-full text-xs font-bold uppercase tracking-wider border <?php echo $badge_class; ?>">
                            <i class="fas <?php echo $icon_class; ?>"></i> <?php echo str_replace('-', ' ', $status); ?>
                        </span>
                    </div>

                    <div class="p-5 bg-white dark:bg-[#111827] space-y-4">
                        <div class="flex justify-between items-center pb-3 border-b border-slate-100 dark:border-slate-800/80">
                            <span class="text-[10px] font-bold uppercase text-slate-400 flex items-center gap-1.5"><i class="fas fa-user text-sky-500"></i> Identity</span>
                            <span class="text-xs font-bold text-slate-800 dark:text-slate-200 truncate max-w-[140px] text-right"><?php echo htmlspecialchars($queue_info['first_name'] . ' ' . $queue_info['last_name']); ?></span>
                        </div>
                        <div class="flex justify-between items-center pb-3 border-b border-slate-100 dark:border-slate-800/80">
                            <span class="text-[10px] font-bold uppercase text-slate-400 flex items-center gap-1.5"><i class="fas fa-clinic-medical text-sky-500"></i> Target Unit</span>
                            <span class="text-xs font-bold text-slate-800 dark:text-slate-200 text-right"><?php echo htmlspecialchars($queue_info['clinic_name']); ?></span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-[10px] font-bold uppercase text-slate-400 flex items-center gap-1.5"><i class="fas fa-shield-alt text-sky-500"></i> Clearance</span>
                            <span class="text-xs font-bold text-slate-800 dark:text-slate-200 text-right uppercase tracking-wider"><?php echo htmlspecialchars($queue_info['patient_type']); ?></span>
                        </div>
                    </div>
                </div>

                <div class="lg:col-span-2 space-y-6">
                    
                    <div class="bg-white dark:bg-[#1f2937] border border-slate-300 dark:border-slate-700/70 rounded-2xl shadow-sm p-6 sm:p-8 flex flex-col justify-center min-h-[160px] transition-colors">
                        <?php if ($queue_info['status'] == 'waiting'): ?>
                            <div class="flex items-start justify-between mb-4">
                                <div>
                                    <h3 class="text-sm font-extrabold text-slate-900 dark:text-white tracking-tight uppercase flex items-center gap-2"><i class="fas fa-stopwatch text-amber-500"></i> Line Position Estimate</h3>
                                    <p class="text-xs text-slate-500 mt-1">Based on current structural throughput limits</p>
                                </div>
                                <div class="text-right">
                                    <span class="text-2xl font-black font-mono text-slate-800 dark:text-white"><?php echo $queue_info['position_in_queue'] ?? 1; ?></span>
                                    <span class="text-[10px] uppercase font-bold text-slate-400 block mt-0.5">In Line</span>
                                </div>
                            </div>
                            
                            <div class="relative w-full h-3 bg-slate-100 dark:bg-slate-800 rounded-full overflow-hidden mb-3 border border-slate-200 dark:border-slate-700/50">
                                <?php 
                                    $pos = $queue_info['position_in_queue'] ?? 1;
                                    $percentage = max(5, 100 - (($pos - 1) * 5)); 
                                ?>
                                <div class="absolute top-0 left-0 h-full bg-gradient-to-r from-amber-400 to-sky-500 transition-all duration-1000" style="width: <?php echo $percentage; ?>%"></div>
                            </div>
                            
                            <div class="flex justify-between items-center text-xs font-bold">
                                <span class="text-slate-500">Est. Wait: <span class="text-slate-800 dark:text-slate-200">~<?php echo ($pos) * 8; ?> minutes</span></span>
                                <span class="text-amber-600 dark:text-amber-500 animate-pulse"><i class="fas fa-spinner fa-spin mr-1"></i> Tracking</span>
                            </div>

                        <?php elseif ($queue_info['status'] == 'called'): ?>
                            <div class="text-center">
                                <div class="w-16 h-16 bg-amber-50 dark:bg-amber-500/10 text-amber-500 rounded-full flex items-center justify-center text-3xl mx-auto mb-4 animate-bounce shadow-inner border border-amber-200 dark:border-amber-500/30">
                                    <i class="fas fa-bullhorn"></i>
                                </div>
                                <h3 class="text-lg font-extrabold text-slate-900 dark:text-white tracking-tight mb-2">Your Number Was Called!</h3>
                                <p class="text-sm font-medium text-slate-600 dark:text-slate-400">Please proceed directly to <strong class="text-amber-600 dark:text-amber-400 font-bold"><?php echo htmlspecialchars($queue_info['clinic_name']); ?></strong> immediately to maintain your slot.</p>
                            </div>
                            
                        <?php elseif ($queue_info['status'] == 'in-progress' || $queue_info['status'] == 'in_progress'): ?>
                            <div class="text-center">
                                <div class="w-16 h-16 bg-sky-50 dark:bg-sky-500/10 text-sky-500 rounded-full flex items-center justify-center text-3xl mx-auto mb-4 shadow-inner border border-sky-200 dark:border-sky-500/30">
                                    <i class="fas fa-user-md animate-pulse"></i>
                                </div>
                                <h3 class="text-lg font-extrabold text-slate-900 dark:text-white tracking-tight mb-2">Active Consultation</h3>
                                <p class="text-sm font-medium text-slate-600 dark:text-slate-400">You are currently inside <strong class="text-sky-600 dark:text-sky-400 font-bold"><?php echo htmlspecialchars($queue_info['clinic_name']); ?></strong> undergoing assessment.</p>
                            </div>
                            
                        <?php elseif ($queue_info['status'] == 'completed'): ?>
                            <div class="text-center">
                                <div class="w-16 h-16 bg-emerald-50 dark:bg-emerald-500/10 text-emerald-500 rounded-full flex items-center justify-center text-3xl mx-auto mb-4 shadow-inner border border-emerald-200 dark:border-emerald-500/30">
                                    <i class="fas fa-check"></i>
                                </div>
                                <h3 class="text-lg font-extrabold text-slate-900 dark:text-white tracking-tight mb-2">Clearance Completed</h3>
                                <p class="text-sm font-medium text-slate-600 dark:text-slate-400">Your session at <strong class="text-emerald-600 dark:text-emerald-400 font-bold"><?php echo htmlspecialchars($queue_info['clinic_name']); ?></strong> has been successfully finalized.</p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php if ($queue_info['patient_type'] == 'military' && !empty($all_clinics)): ?>
                        <div class="bg-white dark:bg-[#1f2937] border border-slate-300 dark:border-slate-700/70 rounded-2xl shadow-sm overflow-hidden transition-colors">
                            <div class="p-5 border-b border-slate-200 dark:border-slate-700/60 bg-slate-50/50 dark:bg-slate-800/40">
                                <div class="flex justify-between items-center mb-3">
                                    <h3 class="text-xs font-extrabold uppercase text-slate-900 dark:text-white tracking-wider flex items-center gap-2">
                                        <i class="fas fa-route text-sky-500 text-sm"></i> Medical Journey Roadmap
                                    </h3>
                                    <span class="text-[10px] font-bold text-sky-600 dark:text-sky-400 bg-sky-50 dark:bg-sky-500/10 px-2 py-0.5 rounded border border-sky-100 dark:border-sky-500/20"><?php echo $journey_progress; ?>% Clear</span>
                                </div>
                                <div class="w-full h-1.5 bg-slate-200 dark:bg-slate-700/50 rounded-full overflow-hidden">
                                    <div class="h-full bg-sky-500 transition-all duration-1000" style="width: <?php echo $journey_progress; ?>%"></div>
                                </div>
                            </div>
                            
                            <div class="p-6">
                                <div class="relative border-l-2 border-slate-200 dark:border-slate-700 ml-3 space-y-8">
                                    
                                    <?php foreach ($completed_clinics as $clinic): ?>
                                        <div class="relative pl-6">
                                            <div class="absolute -left-[9px] top-1 w-4 h-4 bg-emerald-500 rounded-full border-4 border-white dark:border-[#1f2937] shadow-sm"></div>
                                            <div class="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-1">
                                                <h4 class="text-sm font-bold text-slate-800 dark:text-slate-200 line-through opacity-80"><?php echo htmlspecialchars($clinic['name']); ?></h4>
                                                <span class="text-[10px] font-bold uppercase text-emerald-500 bg-emerald-50 dark:bg-emerald-500/10 px-2 py-0.5 rounded border border-emerald-100 dark:border-emerald-500/20 inline-flex items-center gap-1 w-max"><i class="fas fa-check"></i> Cleared</span>
                                            </div>
                                            <p class="text-[10px] text-slate-400 font-medium mt-1 font-mono">Timestamp: <?php echo date('h:i A', strtotime($clinic['completed_at'])); ?></p>
                                        </div>
                                    <?php endforeach; ?>

                                    <?php 
                                        $current_rendered = false;
                                        if ($current_clinic): 
                                            $current_rendered = true;
                                    ?>
                                        <div class="relative pl-6">
                                            <div class="absolute -left-[11px] top-1 w-5 h-5 bg-sky-500 rounded-full border-4 border-white dark:border-[#1f2937] shadow-md ring-2 ring-sky-200 dark:ring-sky-500/30 animate-pulse"></div>
                                            <div class="bg-sky-50 dark:bg-sky-500/10 border border-sky-200 dark:border-sky-500/30 rounded-xl p-4 shadow-sm -mt-2">
                                                <div class="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-2 mb-2">
                                                    <h4 class="text-sm font-extrabold text-sky-800 dark:text-sky-300"><?php echo htmlspecialchars($current_clinic['name']); ?></h4>
                                                    <span class="text-[10px] font-bold uppercase text-sky-600 dark:text-sky-400 bg-white dark:bg-slate-800 px-2 py-0.5 rounded border border-sky-100 dark:border-sky-700 inline-flex items-center gap-1 w-max"><i class="fas fa-spinner fa-spin"></i> Active Assessment</span>
                                                </div>
                                                <p class="text-[10px] text-sky-600/80 dark:text-sky-400/80 font-medium mt-1 font-mono">Registry Block: <?php echo date('h:i A', strtotime($current_clinic['registered_at'])); ?></p>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <?php foreach ($pending_clinics as $pending): 
                                        $clinic_name = '';
                                        foreach ($all_clinics as $c) {
                                            if ($c['id'] == $pending['clinic_id']) {
                                                $clinic_name = $c['name'];
                                                break;
                                            }
                                        }
                                        
                                        $is_waiting = ($pending['status'] == 'waiting');
                                        $is_first_pending = !$current_rendered; 
                                    ?>
                                        <div class="relative pl-6">
                                            <div class="absolute -left-[9px] top-1 w-4 h-4 bg-slate-300 dark:bg-slate-600 rounded-full border-4 border-white dark:border-[#1f2937] shadow-sm"></div>
                                            <div class="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-1">
                                                <h4 class="text-sm font-bold text-slate-500 dark:text-slate-400"><?php echo htmlspecialchars($clinic_name); ?></h4>
                                                
                                                <?php if ($is_waiting): ?>
                                                    <span class="text-[10px] font-bold uppercase text-amber-500 bg-amber-50 dark:bg-amber-500/10 px-2 py-0.5 rounded border border-amber-100 dark:border-amber-500/20 inline-flex items-center gap-1 w-max"><i class="fas fa-hourglass-half"></i> In Queue (#<?php echo $pending['position']; ?>)</span>
                                                <?php else: ?>
                                                    <span class="text-[10px] font-bold uppercase text-slate-400 bg-slate-100 dark:bg-slate-800 px-2 py-0.5 rounded border border-slate-200 dark:border-slate-700 inline-flex items-center gap-1 w-max"><i class="fas fa-lock"></i> Locked</span>
                                                <?php endif; ?>
                                            </div>
                                            <?php if ($is_waiting): ?>
                                                <p class="text-[10px] text-slate-400 font-medium mt-1 font-mono">Token: <?php echo htmlspecialchars($pending['queue_number']); ?></p>
                                            <?php else: ?>
                                                <p class="text-[10px] text-slate-400 font-medium mt-1">Unlocks automatically upon clearance</p>
                                            <?php endif; ?>
                                        </div>
                                    <?php $current_rendered = true; endforeach; ?>

                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <section class="mt-4">
            <div class="flex items-center gap-2 mb-6 px-2">
                <i class="fas fa-satellite text-slate-400"></i>
                <h3 class="text-sm font-extrabold uppercase text-slate-800 dark:text-slate-200 tracking-wider">Hospital Load Distribution Matrix</h3>
            </div>
            
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                <?php foreach ($clinics as $clinic): 
                    $waiting = $clinic['waiting_count'] ?? 0;
                    $load_color = $waiting > 10 ? 'text-rose-500 bg-rose-50 dark:bg-rose-500/10 border-rose-200 dark:border-rose-500/20' : ($waiting > 5 ? 'text-amber-500 bg-amber-50 dark:bg-amber-500/10 border-amber-200 dark:border-amber-500/20' : 'text-emerald-500 bg-emerald-50 dark:bg-emerald-500/10 border-emerald-200 dark:border-emerald-500/20');
                ?>
                    <div class="bg-white dark:bg-[#1f2937] border border-slate-200 dark:border-slate-700/70 rounded-xl p-5 shadow-sm hover:shadow-md transition-all">
                        <div class="flex justify-between items-start mb-4 border-b border-slate-100 dark:border-slate-800 pb-3">
                            <h4 class="text-xs font-bold text-slate-800 dark:text-slate-200 pr-2"><?php echo htmlspecialchars($clinic['name']); ?></h4>
                            <span class="shrink-0 px-2 py-0.5 rounded text-[10px] font-bold border <?php echo $load_color; ?>">
                                <?php echo $waiting; ?> Waiting
                            </span>
                        </div>
                        
                        <div class="flex justify-between items-center text-[10px] font-mono font-medium text-slate-500 dark:text-slate-400">
                            <span class="flex items-center gap-1" title="Currently Serving"><i class="fas fa-user-md text-sky-500"></i> <?php echo $clinic['in_progress_count'] ?? 0; ?> Active</span>
                            <span class="text-slate-300 dark:text-slate-600">|</span>
                            <span class="flex items-center gap-1" title="Cleared Today"><i class="fas fa-check text-emerald-500"></i> <?php echo $clinic['completed_today'] ?? 0; ?> Cleared</span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

    </main>

    <footer class="bg-white dark:bg-[#111827] border-t border-slate-200 dark:border-slate-800 py-6 mt-auto transition-colors z-10 relative">
        <div class="max-w-5xl mx-auto px-6 flex flex-col sm:flex-row justify-between items-center gap-4">
            <div class="flex flex-wrap justify-center gap-x-6 gap-y-2 text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                <a href="index.php" class="hover:text-sky-500 transition-colors"><i class="fas fa-home mr-1"></i> Home</a>
                <a href="self-register.php" class="hover:text-sky-500 transition-colors"><i class="fas fa-user-plus mr-1"></i> Self Registration</a>
                <a href="../index.php" class="hover:text-sky-500 transition-colors"><i class="fas fa-lock mr-1"></i> Staff Login</a>
            </div>
            <div class="text-[10px] font-bold uppercase tracking-widest text-slate-400 dark:text-slate-500 text-center sm:text-right">
                <i class="fas fa-shield-alt mr-1"></i> <?php echo date('Y'); ?> 4ID Station Hospital
            </div>
        </div>
    </footer>

    <script>
        // System Theme Matrix Switch Engine
        const themeToggleBtn = document.getElementById('themeToggleBtn');
        const themeToggleIcon = document.getElementById('themeToggleIcon');
        
        if (localStorage.getItem('theme') === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark'); 
            if(themeToggleIcon) themeToggleIcon.className = 'fas fa-sun text-sm';
        }
        
        if(themeToggleBtn) {
            themeToggleBtn.addEventListener('click', () => {
                if (document.documentElement.classList.contains('dark')) {
                    document.documentElement.classList.remove('dark'); 
                    localStorage.setItem('theme', 'light'); 
                    if(themeToggleIcon) themeToggleIcon.className = 'fas fa-moon text-sm';
                } else {
                    document.documentElement.classList.add('dark'); 
                    localStorage.setItem('theme', 'dark'); 
                    if(themeToggleIcon) themeToggleIcon.className = 'fas fa-sun text-sm text-amber-400';
                }
            });
        }

        // Automated Queue Monitor Refresh Interval Sequence
        <?php if ($queue_info): ?>
        setTimeout(function() {
            location.reload();
        }, 30000);
        <?php endif; ?>
    </script>
</body>
</html>