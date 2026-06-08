<?php
// admin/patient-results.php - View Patient Results
// Camp Evangelista Station Hospital

require_once dirname(__DIR__) . '/config/database.php';

session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: ../index.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

// ============================================
// SESSION TIMEOUT
// ============================================
require_once dirname(__DIR__) . '/includes/SessionManager.php';
$sessionManager = new SessionManager($db);
if (!$sessionManager->checkTimeout()) {
    exit(); // Already redirected
}
$sessionManager->logActivity('Viewed patient clinical results archive');

$patient_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$message = '';

// Get patient info
$patient_query = "SELECT * FROM patients WHERE id = :id";
$patient_stmt = $db->prepare($patient_query);
$patient_stmt->bindParam(':id', $patient_id);
$patient_stmt->execute();
$patient = $patient_stmt->fetch(PDO::FETCH_ASSOC);

if (!$patient) {
    header('Location: patients.php');
    exit();
}

// Get all results for this patient
$results_query = "SELECT cr.*, c.name as clinic_name, u.full_name as doctor_name,
                         q.queue_number, q.registered_at
                  FROM clinic_results cr
                  JOIN clinics c ON cr.clinic_id = c.id
                  LEFT JOIN users u ON cr.submitted_by = u.id
                  JOIN queue_entries q ON cr.queue_entry_id = q.id
                  WHERE cr.patient_id = :patient_id
                  ORDER BY cr.submitted_at DESC";
$results_stmt = $db->prepare($results_query);
$results_stmt->bindParam(':patient_id', $patient_id);
$results_stmt->execute();
$results = $results_stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Clinical Results | <?php echo htmlspecialchars(($patient['first_name'] ?? '') . ' ' . ($patient['last_name'] ?? '')); ?> | Camp Evangelista</title>
    
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
                    <h1 class="text-slate-900 dark:text-white text-2xl md:text-3xl font-extrabold tracking-tight mb-0.5"><i class="fas fa-file-alt text-sky-500 mr-1.5"></i>Patient Clinical Results</h1>
                    <p class="text-slate-500 dark:text-slate-400 text-xs md:text-sm font-medium">Read-only profile archive registry of laboratory reports and examination outcomes</p>
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
                <i class="fas fa-arrow-left text-teal-500"></i> Return to Registry
            </a>
        </div>

        <div class="bg-white dark:bg-[#1f2937] border border-slate-300 dark:border-slate-700/70 rounded-xl shadow-sm p-5 mb-6 flex flex-col sm:flex-row justify-between sm:items-center gap-4">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 bg-sky-50 dark:bg-sky-500/10 border border-sky-100 dark:border-sky-500/20 text-sky-600 dark:text-sky-400 rounded-full flex items-center justify-center text-lg font-black shadow-inner">
                    <?php echo strtoupper(substr($patient['first_name'] ?? '', 0, 1) . substr($patient['last_name'] ?? '', 0, 1)); ?>
                </div>
                <div>
                    <h2 class="text-slate-900 dark:text-white text-base font-extrabold tracking-tight leading-snug"><?php echo htmlspecialchars(($patient['first_name'] ?? '') . ' ' . ($patient['last_name'] ?? '')); ?></h2>
                    <p class="text-slate-500 dark:text-slate-400 text-xs font-medium mt-0.5">
                        <i class="fas fa-calendar-alt mr-1"></i>DOB: <?php echo isset($patient['date_of_birth']) ? date('M d, Y', strtotime($patient['date_of_birth'])) : 'N/A'; ?> | 
                        <i class="fas fa-venus-mars ml-2 mr-1"></i><?php echo htmlspecialchars($patient['gender'] ?? 'N/A'); ?>
                    </p>
                </div>
            </div>
            <div class="sm:text-right">
                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-mono font-extrabold uppercase tracking-widest bg-sky-50 dark:bg-sky-500/10 text-sky-600 dark:text-sky-400 border border-sky-200/50 dark:border-sky-500/20">
                    <i class="fas fa-id-card mr-1.5 text-[10px]"></i>MRN: <?php echo htmlspecialchars($patient['mrn'] ?? $patient['id'] ?? 'N/A'); ?>
                </span>
            </div>
        </div>

        <?php if (empty($results)): ?>
            <div class="bg-white dark:bg-[#1f2937] border border-slate-300 dark:border-slate-700/70 text-center rounded-xl p-12 shadow-sm">
                <i class="fas fa-file-invoice text-slate-300 dark:text-slate-600 text-5xl mb-4"></i>
                <h3 class="text-slate-400 dark:text-slate-500 font-bold uppercase tracking-wider text-sm">No clinical results found for this profile map.</h3>
                <p class="text-slate-400 dark:text-slate-500 text-xs mt-1">Results will appear here once examinations are finalized by matching clinics.</p>
            </div>
        <?php else: ?>
            <div class="space-y-6">
                <?php foreach ($results as $result): 
                    $result_data = json_decode($result['result_data'], true);
                ?>
                    <div class="bg-white dark:bg-[#1f2937] border border-slate-300 dark:border-slate-700/70 rounded-xl shadow-sm overflow-hidden">
                        
                        <div class="p-4 bg-slate-50/50 dark:bg-slate-800/40 border-b border-slate-200 dark:border-slate-700/60 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                            <div class="flex items-center flex-wrap gap-2.5">
                                <div class="w-8 h-8 rounded-lg bg-sky-500/10 text-sky-500 flex items-center justify-center text-sm">
                                    <i class="fas fa-clinic-medical"></i>
                                </div>
                                <span class="font-extrabold text-slate-900 dark:text-white text-sm tracking-tight"><?php echo htmlspecialchars($result['clinic_name']); ?></span>
                                <span class="px-2 py-0.5 rounded text-[10px] font-mono font-bold bg-sky-50 dark:bg-sky-500/10 text-sky-600 dark:text-sky-400 border border-sky-200/40 dark:border-sky-500/20">
                                    Queue Code: <?php echo htmlspecialchars($result['queue_number']); ?>
                                </span>
                            </div>
                            <div class="text-slate-500 dark:text-slate-400 font-medium text-xs flex items-center flex-wrap gap-x-4 gap-y-1">
                                <span><i class="fas fa-calendar-alt text-[11px] mr-1"></i><?php echo date('F d, Y h:i A', strtotime($result['submitted_at'])); ?></span>
                                <?php if ($result['doctor_name']): ?>
                                    <span class="font-bold text-slate-700 dark:text-slate-300"><i class="fas fa-user-md text-[11px] text-sky-500 mr-1"></i><?php echo htmlspecialchars($result['doctor_name']); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="p-5 space-y-5">
                            <?php if ($result_data && is_array($result_data)): ?>
                                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                                    <?php foreach ($result_data as $key => $value): 
                                        if (empty($value)) continue;
                                        $label = ucwords(str_replace('_', ' ', $key));
                                    ?>
                                        <div class="bg-slate-50 dark:bg-slate-800/30 border border-slate-200 dark:border-slate-700/40 rounded-xl p-3">
                                            <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500"><?php echo htmlspecialchars($label); ?></div>
                                            <div class="text-xs font-bold text-slate-900 dark:text-white mt-1 leading-relaxed"><?php echo nl2br(htmlspecialchars($value)); ?></div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($result['findings'])): ?>
                                <div class="space-y-1.5">
                                    <div class="text-[10px] font-bold uppercase text-slate-400 dark:text-slate-500 tracking-wider flex items-center gap-1.5">
                                        <i class="fas fa-stethoscope text-sky-500"></i> Clinical Findings
                                    </div>
                                    <div class="bg-slate-50 dark:bg-slate-800/30 border border-slate-200 dark:border-slate-700/40 rounded-xl p-4 text-xs font-medium text-slate-700 dark:text-slate-300 leading-relaxed">
                                        <?php echo nl2br(htmlspecialchars($result['findings'])); ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($result['recommendations'])): ?>
                                <div class="space-y-1.5">
                                    <div class="text-[10px] font-bold uppercase text-slate-400 dark:text-slate-500 tracking-wider flex items-center gap-1.5">
                                        <i class="fas fa-comment-medical text-teal-500"></i> Recommendations & Directives
                                    </div>
                                    <div class="bg-slate-50 dark:bg-slate-800/30 border border-slate-200 dark:border-slate-700/40 rounded-xl p-4 text-xs font-medium text-slate-700 dark:text-slate-300 leading-relaxed">
                                        <?php echo nl2br(htmlspecialchars($result['recommendations'])); ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
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

        // ============================================
        // AUTO-LOGOUT AFTER INACTIVITY
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
                        <button onclick="keepSessionAlive()" style="background: #009688; color: white; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer; font-weight: 600; font-size:0.875rem;">
                            <i class="fas fa-mouse-pointer mr-1"></i> Stay Logged In
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
                console.log('Heartbeat failed:', err);
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