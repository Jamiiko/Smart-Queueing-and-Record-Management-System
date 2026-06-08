<?php
// admin/patient-view.php - View Patient Information (Read Only)
// Camp Evangelista Station Hospital

require_once dirname(__DIR__) . '/config/database.php';

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

// ============================================
// DEFENSIVE WORKAROUND: CREATE MISSING TABLE
// ============================================
try {
    $db->exec("CREATE TABLE IF NOT EXISTS `patient_documents` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `patient_id` INT NOT NULL,
        `file_name` VARCHAR(255) NOT NULL,
        `document_type` VARCHAR(100) DEFAULT NULL,
        `file_path` VARCHAR(255) NOT NULL,
        `uploaded_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
} catch (PDOException $e) {
    // If table already exists or minor error occurs, fail silently and proceed
}

// ============================================
// SESSION TIMEOUT
// ============================================
require_once dirname(__DIR__) . '/includes/SessionManager.php';
$sessionManager = new SessionManager($db);
if (!$sessionManager->checkTimeout()) {
    exit(); // Already redirected
}
$sessionManager->logActivity('Viewed patient master record');

$patient_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($patient_id <= 0) {
    header('Location: patients.php');
    exit();
}

// Get patient data
$query = "SELECT * FROM patients WHERE id = :id";
$stmt = $db->prepare($query);
$stmt->bindParam(':id', $patient_id);
$stmt->execute();
$patient = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$patient) {
    header('Location: patients.php');
    exit();
}

// Get queue history
$queue_query = "SELECT q.*, c.name as clinic_name
                FROM queue_entries q
                JOIN clinics c ON q.clinic_id = c.id
                WHERE q.patient_id = :patient_id
                ORDER BY q.registered_at DESC
                LIMIT 20";
$queue_stmt = $db->prepare($queue_query);
$queue_stmt->bindParam(':patient_id', $patient_id);
$queue_stmt->execute();
$queue_history = $queue_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get medical documents
$doc_query = "SELECT * FROM patient_documents WHERE patient_id = :patient_id ORDER BY uploaded_at DESC";
$doc_stmt = $db->prepare($doc_query);
$doc_stmt->bindParam(':patient_id', $patient_id);
$doc_stmt->execute();
$documents = $doc_stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Master Record | 4ID Station Hospital | Camp Evangelista</title>
    
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
                    <h1 class="text-slate-900 dark:text-white text-2xl md:text-3xl font-extrabold tracking-tight mb-0.5">Patient Record Ledger</h1>
                    <p class="text-slate-500 dark:text-slate-400 text-xs md:text-sm font-medium">Read-only profile archive registry and diagnostic ledger history</p>
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
                    
                    <div id="profileDropdown" class="hidden absolute right-0 mt-2 w-56 bg-white dark:bg-[#1f2937] border border-slate-300 dark:border-slate-700 rounded-xl shadow-xl z-[1100]">
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

        <div class="mb-5">
            <a href="patients.php" class="inline-flex items-center gap-2 bg-white dark:bg-[#1f2937] border border-slate-300 dark:border-slate-700 hover:border-slate-400 dark:hover:border-slate-600 text-slate-600 dark:text-slate-300 text-xs font-bold uppercase tracking-wider px-4 py-2.5 rounded-xl shadow-sm transition-all">
                <i class="fas fa-arrow-left text-sky-500"></i> Return to Registry
            </a>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start mb-8">
            
            <section class="bg-white dark:bg-[#1f2937] border border-slate-300 dark:border-slate-700/70 rounded-xl shadow-sm overflow-hidden lg:col-span-1">
                <div class="p-4 border-b border-slate-200 dark:border-slate-700/60 bg-slate-50/50 dark:bg-slate-800/20">
                    <h3 class="text-xs font-bold uppercase text-slate-900 dark:text-white tracking-wider flex items-center gap-2"><i class="fas fa-id-card text-sky-500 text-sm"></i> Demographic Summary</h3>
                </div>
                <div class="p-5 flex flex-col items-center border-b border-slate-200 dark:border-slate-700/50 text-center bg-slate-50/40 dark:bg-slate-800/10">
                    <div class="w-16 h-16 bg-sky-50 dark:bg-sky-500/10 border border-sky-100 dark:border-sky-500/20 text-sky-600 dark:text-sky-400 rounded-full flex items-center justify-center text-2xl font-black shadow-inner mb-3">
                        <?= strtoupper(substr($patient['first_name'] ?? '', 0, 1) . substr($patient['last_name'] ?? '', 0, 1)); ?>
                    </div>
                    <h2 class="text-slate-900 dark:text-white text-base font-extrabold tracking-tight leading-snug"><?= htmlspecialchars(($patient['first_name'] ?? '') . ' ' . ($patient['last_name'] ?? '')); ?></h2>
                    <span class="mt-1.5 px-2.5 py-0.5 rounded-md text-[9px] font-mono font-extrabold uppercase tracking-widest bg-slate-200/60 dark:bg-slate-700 text-slate-600 dark:text-slate-300 border border-slate-300/40 dark:border-slate-600/30">ID: #<?= str_pad($patient['id'], 5, '0', STR_PAD_LEFT); ?></span>
                </div>
                
                <div class="p-5 space-y-4">
                    <div class="flex flex-col gap-1">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Military Rank Classification</span>
                        <p class="text-xs font-bold text-slate-800 dark:text-slate-200">
                            <?= htmlspecialchars(($patient['rank_classification'] ?? $patient['rank'] ?? '') ?: 'N/A (Civilian)'); ?>
                        </p>
                    </div>
                    <div class="flex flex-col gap-1">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Military Serial Number</span>
                        <p class="text-xs font-mono font-bold text-slate-800 dark:text-slate-200">
                            <?= htmlspecialchars(($patient['serial_number'] ?? $patient['serial_no'] ?? '') ?: 'Non-Military Personnel'); ?>
                        </p>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="flex flex-col gap-1">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Gender Index</span>
                            <p class="text-xs font-bold text-slate-800 dark:text-slate-200"><?= htmlspecialchars($patient['gender'] ?? 'N/A'); ?></p>
                        </div>
                        <div class="flex flex-col gap-1">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Date of Birth</span>
                            <p class="text-xs font-bold text-slate-800 dark:text-slate-200">
                                <?= isset($patient['date_of_birth']) ? date('M d, Y', strtotime($patient['date_of_birth'])) : 'N/A'; ?>
                            </p>
                        </div>
                    </div>
                    <div class="flex flex-col gap-1">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Mobile Communications Line</span>
                        <p class="text-xs font-mono font-bold text-slate-800 dark:text-slate-200">
                            <?= htmlspecialchars(($patient['phone_number'] ?? $patient['contact_no'] ?? $patient['phone'] ?? '') ?: 'None Reported'); ?>
                        </p>
                    </div>
                    <div class="flex flex-col gap-1">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Permanent Residential Address</span>
                        <p class="text-xs font-medium text-slate-600 dark:text-slate-300 leading-relaxed"><?= htmlspecialchars($patient['address'] ?? 'No address specified'); ?></p>
                    </div>
                </div>
            </section>

            <div class="lg:col-span-2 space-y-6">
                
                <section class="bg-white dark:bg-[#1f2937] border border-slate-300 dark:border-slate-700/70 rounded-xl shadow-sm overflow-hidden">
                    <div class="p-4 border-b border-slate-200 dark:border-slate-700/60 bg-slate-50/50 dark:bg-slate-800/20">
                        <h3 class="text-xs font-bold uppercase text-slate-900 dark:text-white tracking-wider flex items-center gap-2"><i class="fas fa-history text-sky-500 text-sm"></i> Chronological Queue Log History</h3>
                    </div>
                    
                    <div class="overflow-x-auto">
                        <table class="w-full border-collapse text-left">
                            <thead>
                                <tr class="bg-slate-50 dark:bg-slate-800/50 border-b border-slate-300 dark:border-slate-700/80 text-slate-400 text-[10px] font-bold uppercase tracking-wider">
                                    <th class="py-3 px-4">Registry Stamp</th>
                                    <th class="py-3 px-4">Clinic Operational Block</th>
                                    <th class="py-3 px-4 text-center">Token Code</th>
                                    <th class="py-3 px-4 text-center">Priority</th>
                                    <th class="py-3 px-4 text-right">Fulfillment Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200 dark:divide-slate-700/60 text-xs font-medium text-slate-700 dark:text-slate-300">
                                <?php if (empty($queue_history)): ?>
                                    <tr>
                                        <td colspan="5" class="py-8 text-center text-slate-400 font-bold uppercase tracking-wider">No history tracking metrics discovered for this profile map.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($queue_history as $entry): ?>
                                        <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-800/30 transition-colors">
                                            <td class="py-3 px-4 text-slate-500 dark:text-slate-400">
                                                <?= date('M d, Y h:i A', strtotime($entry['registered_at'])); ?>
                                            </td>
                                            <td class="py-3 px-4 font-bold text-slate-900 dark:text-white">
                                                <?= htmlspecialchars($entry['clinic_name']); ?>
                                            </td>
                                            <td class="py-3 px-4 text-center font-mono font-bold text-sky-600 dark:text-sky-400">
                                                <?= htmlspecialchars($entry['queue_number']); ?>
                                            </td>
                                            <td class="py-3 px-4 text-center">
                                                <?php if (($entry['priority_level'] ?? '') === 'PR1'): ?>
                                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-rose-50 dark:bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-200/40">PR1</span>
                                                <?php elseif (($entry['priority_level'] ?? '') === 'PR2'): ?>
                                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-50 dark:bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-200/40">PR2</span>
                                                <?php else: ?>
                                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-50 dark:bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-200/40">PR3</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="py-3 px-4 text-right">
                                                <?php $status = $entry['status'] ?? 'pending'; ?>
                                                <?php if ($status === 'completed'): ?>
                                                    <span class="text-[10px] font-bold uppercase tracking-wide text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-500/10 px-2 py-0.5 rounded-md"><i class="fas fa-check-circle mr-1"></i> Completed</span>
                                                <?php elseif ($status === 'in_progress' || $status === 'serving'): ?>
                                                    <span class="text-[10px] font-bold uppercase tracking-wide text-sky-600 dark:text-sky-400 bg-sky-50 dark:bg-sky-500/10 px-2 py-0.5 rounded-md"><i class="fas fa-spinner animate-spin mr-1"></i> Serving</span>
                                                <?php elseif ($status === 'pending'): ?>
                                                    <span class="text-[10px] font-bold uppercase tracking-wide text-amber-600 dark:text-amber-500 bg-amber-50 dark:bg-amber-500/10 px-2 py-0.5 rounded-md"><i class="fas fa-clock mr-1"></i> Pending</span>
                                                <?php else: ?>
                                                    <span class="text-[10px] font-bold uppercase tracking-wide text-slate-400 bg-slate-100 dark:bg-slate-800 px-2 py-0.5 rounded-md">Skipped</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>

                <section class="bg-white dark:bg-[#1f2937] border border-slate-300 dark:border-slate-700/70 rounded-xl shadow-sm overflow-hidden">
                    <div class="p-4 border-b border-slate-200 dark:border-slate-700/60 bg-slate-50/50 dark:bg-slate-800/20">
                        <h3 class="text-xs font-bold uppercase text-slate-900 dark:text-white tracking-wider flex items-center gap-2"><i class="fas fa-folder-open text-sky-500 text-sm"></i> Secure Vault Digital Records Archive</h3>
                    </div>
                    
                    <div class="overflow-x-auto">
                        <table class="w-full border-collapse text-left">
                            <thead>
                                <tr class="bg-slate-50 dark:bg-slate-800/50 border-b border-slate-300 dark:border-slate-700/80 text-slate-400 text-[10px] font-bold uppercase tracking-wider">
                                    <th class="py-3 px-4">Upload Stamp</th>
                                    <th class="py-3 px-4">Cryptographic File Identifier</th>
                                    <th class="py-3 px-4">Document Clearance Type</th>
                                    <th class="py-3 px-4 text-right">Data Endpoint Path Link</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200 dark:divide-slate-700/60 text-xs font-medium text-slate-700 dark:text-slate-300">
                                <?php if (empty($documents)): ?>
                                    <tr>
                                        <td colspan="4" class="py-8 text-center text-slate-400 font-bold uppercase tracking-wider">No attached clinical clearance paperwork uploaded to this ledger.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($documents as $doc): ?>
                                        <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-800/30 transition-colors">
                                            <td class="py-3 px-4 text-slate-500 dark:text-slate-400">
                                                <?= date('M d, Y', strtotime($doc['uploaded_at'])); ?>
                                            </td>
                                            <td class="py-3 px-4 font-bold text-slate-900 dark:text-white truncate max-w-[180px]">
                                                <?= htmlspecialchars($doc['file_name']); ?>
                                            </td>
                                            <td class="py-3 px-4">
                                                <span class="inline-block text-[10px] font-bold px-2 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 border border-slate-300/30 dark:border-slate-700/50">
                                                    <?= htmlspecialchars($doc['document_type'] ?: 'Generic Asset'); ?>
                                                </span>
                                            </td>
                                            <td class="py-3 px-4 text-right">
                                                <a href="<?= htmlspecialchars($doc['file_path']); ?>" target="_blank" class="inline-flex items-center gap-1.5 font-bold text-[10px] text-sky-600 dark:text-sky-400 border border-slate-300 dark:border-slate-700 hover:border-sky-500 dark:hover:border-sky-400 bg-white dark:bg-[#111827] rounded-lg px-2.5 py-1.5 transition-all shadow-sm">
                                                    <i class="fas fa-external-link-alt text-[9px]"></i> View Document
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </div>
    </main>

    <script>
        // System Native Clock Integration
        function updateDateTime() {
            const now = new Date();
            const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
            document.getElementById('currentDate').textContent = now.toLocaleDateString('en-US', options);
            document.getElementById('currentTime').textContent = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
        }
        updateDateTime();
        setInterval(updateDateTime, 1000);

        // Responsive Mobile Left Drawer Toggles
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

        // Administrator Action Panel Profile Context Dropdowns
        const profileMenuBtn = document.getElementById('profileMenuBtn');
        const profileDropdown = document.getElementById('profileDropdown');
        if (profileMenuBtn && profileDropdown) {
            profileMenuBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                profileDropdown.classList.toggle('hidden');
            });
            document.addEventListener('click', () => profileDropdown.classList.add('hidden'));
        }

        // Dark/Light Visual Theme Core Manager Rules
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
    </script>
</body>
</html>