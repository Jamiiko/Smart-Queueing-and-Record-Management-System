<?php
// admin/login-monitor.php - Login Security Monitor
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

// ============================================
// LOGIN HISTORY
// ============================================
$query = "SELECT h.*, u.full_name
          FROM login_history h
          LEFT JOIN users u ON h.user_id = u.id
          ORDER BY h.attempt_time DESC
          LIMIT 100";

$history = $db->query($query)->fetchAll(PDO::FETCH_ASSOC);

// ============================================
// LOCKED ACCOUNTS
// ============================================
$locked = "SELECT username, full_name, login_attempts, locked_until
           FROM users
           WHERE locked_until > NOW()
           ORDER BY locked_until DESC";

$locked_accounts = $db->query($locked)->fetchAll(PDO::FETCH_ASSOC);

// ============================================
// STATISTICS
// ============================================
$total_attempts = count($history);
$successful_logins = 0;
$failed_logins = 0;
$unique_users = [];

foreach ($history as $log) {
    if ($log['success']) {
        $successful_logins++;
    } else {
        $failed_logins++;
    }

    if (!in_array($log['username'], $unique_users)) {
        $unique_users[] = $log['username'];
    }
}

$success_rate = $total_attempts > 0
    ? round(($successful_logins / $total_attempts) * 100)
    : 0;

// ============================================
// TODAY ATTEMPTS
// ============================================
$today = date('Y-m-d');
$today_attempts = 0;

foreach ($history as $log) {
    if (date('Y-m-d', strtotime($log['attempt_time'])) == $today) {
        $today_attempts++;
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Monitor | 4ID Station Hospital | Camp Evangelista</title>
    
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
                        <a href="login-monitor.php" class="flex items-center rounded-xl font-semibold transition-all duration-150 bg-sky-50 dark:bg-sky-500/10 text-sky-600 dark:text-sky-400 border-l-4 border-sky-500 p-3 justify-center md:justify-start gap-0 md:group-hover/sidebar:gap-4">
                            <div class="w-6 h-6 flex items-center justify-center shrink-0">
                                <i class="fas fa-history text-base"></i>
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
                    <h1 class="text-slate-900 dark:text-white text-2xl md:text-3xl font-extrabold tracking-tight mb-0.5">Login Security Monitor</h1>
                    <p class="text-slate-500 dark:text-slate-400 text-xs md:text-sm font-medium">Monitor real-time login attempts and internal system security infrastructure events</p>
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
                        <i class="fas fa-user-shield text-lg"></i>
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

        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4 mb-6">
            <div class="bg-white dark:bg-[#1f2937] border border-slate-300 dark:border-slate-700/70 rounded-xl p-4 shadow-sm flex flex-col items-center justify-center text-center">
                <div class="w-9 h-9 bg-slate-100 dark:bg-slate-700/50 rounded-xl flex items-center justify-center text-slate-500 dark:text-slate-400 text-sm mb-2"><i class="fas fa-chart-line"></i></div>
                <div class="text-xl font-extrabold text-slate-900 dark:text-white font-mono leading-none"><?php echo number_format($total_attempts); ?></div>
                <div class="text-slate-400 dark:text-slate-500 text-[9px] font-bold uppercase tracking-wider mt-1">Total Attempts</div>
            </div>
            <div class="bg-white dark:bg-[#1f2937] border border-slate-300 dark:border-slate-700/70 rounded-xl p-4 shadow-sm flex flex-col items-center justify-center text-center">
                <div class="w-9 h-9 bg-emerald-50 dark:bg-emerald-500/10 rounded-xl flex items-center justify-center text-emerald-500 text-sm mb-2"><i class="fas fa-check-circle"></i></div>
                <div class="text-xl font-extrabold text-emerald-600 dark:text-emerald-400 font-mono leading-none"><?php echo number_format($successful_logins); ?></div>
                <div class="text-slate-400 dark:text-slate-500 text-[9px] font-bold uppercase tracking-wider mt-1">Successful</div>
            </div>
            <div class="bg-white dark:bg-[#1f2937] border border-slate-300 dark:border-slate-700/70 rounded-xl p-4 shadow-sm flex flex-col items-center justify-center text-center">
                <div class="w-9 h-9 bg-rose-50 dark:bg-rose-500/10 rounded-xl flex items-center justify-center text-rose-500 text-sm mb-2"><i class="fas fa-times-circle"></i></div>
                <div class="text-xl font-extrabold text-rose-600 dark:text-rose-400 font-mono leading-none"><?php echo number_format($failed_logins); ?></div>
                <div class="text-slate-400 dark:text-slate-500 text-[9px] font-bold uppercase tracking-wider mt-1">Failed Attempts</div>
            </div>
            <div class="bg-white dark:bg-[#1f2937] border border-slate-300 dark:border-slate-700/70 rounded-xl p-4 shadow-sm flex flex-col items-center justify-center text-center">
                <div class="w-9 h-9 bg-sky-50 dark:bg-sky-500/10 rounded-xl flex items-center justify-center text-sky-500 text-sm mb-2"><i class="fas fa-percent"></i></div>
                <div class="text-xl font-extrabold text-sky-600 dark:text-sky-400 font-mono leading-none"><?php echo $success_rate; ?>%</div>
                <div class="text-slate-400 dark:text-slate-500 text-[9px] font-bold uppercase tracking-wider mt-1">Success Rate</div>
            </div>
            <div class="bg-white dark:bg-[#1f2937] border border-slate-300 dark:border-slate-700/70 rounded-xl p-4 shadow-sm flex flex-col items-center justify-center text-center col-span-2 md:col-span-1">
                <div class="w-9 h-9 bg-amber-50 dark:bg-amber-500/10 rounded-xl flex items-center justify-center text-amber-500 text-sm mb-2"><i class="fas fa-calendar-day"></i></div>
                <div class="text-xl font-extrabold text-amber-600 dark:text-amber-500 font-mono leading-none"><?php echo number_format($today_attempts); ?></div>
                <div class="text-slate-400 dark:text-slate-500 text-[9px] font-bold uppercase tracking-wider mt-1">Today's Attempts</div>
            </div>
        </div>

        <?php if (!empty($locked_accounts)): ?>
            <div class="bg-rose-50 dark:bg-rose-500/10 border border-rose-200 dark:border-rose-500/30 text-rose-700 dark:text-rose-400 px-4 py-3.5 rounded-xl text-xs font-bold uppercase tracking-wide mb-6 flex items-center gap-3 shadow-sm animate-pulse">
                <i class="fas fa-lock text-base"></i>
                <div>
                    <div>Locked System Accounts Detected</div>
                    <span class="text-[10px] opacity-80 normal-case font-semibold block mt-0.5"><?php echo count($locked_accounts); ?> user profile terminal authentication routes are currently frozen due to safety policy threshold breaks.</span>
                </div>
            </div>
        <?php endif; ?>

        <section class="bg-white dark:bg-[#1f2937] border border-slate-300 dark:border-slate-700/70 rounded-xl shadow-sm overflow-hidden mb-8">
            <div class="p-4 border-b border-slate-200 dark:border-slate-700/60 bg-slate-50/50 dark:bg-slate-800/20 flex justify-between items-center">
                <h3 class="text-xs font-bold uppercase text-slate-900 dark:text-white tracking-wider flex items-center gap-2"><i class="fas fa-history text-sky-500 text-sm"></i> Recent Authorization Activity Logs</h3>
                <span class="text-[10px] text-slate-400 dark:text-slate-500 font-bold uppercase tracking-wider bg-slate-100 dark:bg-slate-800 px-2.5 py-1 rounded-md">Last 100 Attempts</span>
            </div>
            
            <div class="overflow-x-auto">
                <?php if (empty($history)): ?>
                    <div class="p-12 text-center text-slate-400 dark:text-slate-500 flex flex-col items-center justify-center">
                        <i class="fas fa-shield-alt text-4xl mb-3 text-slate-300 dark:text-slate-700"></i>
                        <p class="font-bold uppercase tracking-wider text-xs">No Security Event History Located</p>
                    </div>
                <?php else: ?>
                    <table class="w-full border-collapse text-left">
                        <thead>
                            <tr class="bg-slate-50 dark:bg-slate-800/50 border-b border-slate-300 dark:border-slate-700/80 text-slate-400 text-[10px] font-bold uppercase tracking-wider">
                                <th class="py-3.5 px-4">Attempt Timestamp</th>
                                <th class="py-3.5 px-4">Username</th>
                                <th class="py-3.5 px-4">Full Identity</th>
                                <th class="py-3.5 px-4">Network IP Address</th>
                                <th class="py-3.5 px-4">Security Status</th>
                                <th class="py-3.5 px-4">System Disruption Reason</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 dark:divide-slate-700/60 text-xs font-medium text-slate-700 dark:text-slate-300">
                            <?php foreach ($history as $log): ?>
                            <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-800/30 transition-colors">
                                <td class="py-3 px-4 font-mono font-bold text-slate-500 dark:text-slate-400">
                                    <?php echo date('M d, Y h:i:s A', strtotime($log['attempt_time'])); ?>
                                </td>
                                <td class="py-3 px-4 font-mono font-bold text-slate-900 dark:text-white">
                                    <?php echo htmlspecialchars($log['username']); ?>
                                </td>
                                <td class="py-3 px-4 font-semibold text-slate-800 dark:text-slate-200">
                                    <?php echo htmlspecialchars($log['full_name'] ?? '—'); ?>
                                </td>
                                <td class="py-3 px-4">
                                    <span class="px-2 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 font-mono text-[11px] border border-slate-200 dark:border-slate-700">
                                        <?php echo htmlspecialchars($log['ip_address']); ?>
                                    </span>
                                </td>
                                <td class="py-3 px-4">
                                    <?php if ($log['success']): ?>
                                        <span class="inline-flex items-center gap-1 text-emerald-600 dark:text-emerald-400 font-bold uppercase tracking-wide text-[10px] bg-emerald-50 dark:bg-emerald-500/10 px-2.5 py-0.5 rounded-full">Success</span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center gap-1 text-rose-600 dark:text-rose-400 font-bold uppercase tracking-wide text-[10px] bg-rose-50 dark:bg-rose-500/10 px-2.5 py-0.5 rounded-full">Failed</span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3 px-4 font-medium">
                                    <?php
                                    $reason = $log['failure_reason'] ?? '';
                                    if ($reason == 'Invalid password') {
                                        echo '<span class="text-amber-500 flex items-center gap-1.5"><i class="fas fa-key text-[10px]"></i> Invalid Password Matrix</span>';
                                    } elseif ($reason == 'User not found') {
                                        echo '<span class="text-slate-400 dark:text-slate-500 flex items-center gap-1.5"><i class="fas fa-user-slash text-[10px]"></i> Identity Record Blank</span>';
                                    } elseif ($reason == 'Invalid 2FA code') {
                                        echo '<span class="text-rose-500 flex items-center gap-1.5"><i class="fas fa-qrcode text-[10px]"></i> Secondary Factor Error</span>';
                                    } elseif ($reason == 'Account locked') {
                                        echo '<span class="text-red-500 font-bold uppercase text-[10px] tracking-wide bg-red-50 dark:bg-red-500/10 px-2 py-0.5 rounded border border-red-200 dark:border-red-500/20 flex items-center w-max gap-1"><i class="fas fa-lock text-[9px]"></i> Account Intercept</span>';
                                    } elseif ($reason == 'CAPTCHA failed') {
                                        echo '<span class="text-amber-500 flex items-center gap-1.5"><i class="fas fa-robot text-[10px]"></i> Human Token Failure</span>';
                                    } else {
                                        echo '<span class="text-slate-400 font-mono">—</span>';
                                    }
                                    ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </section>
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
        // PRESERVED SECURITY & INACTIVITY TIMEOUT NATIVE LOGIC
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