<?php
// staff/registration.php - Patient Registration Form Engine
// Camp Evangelista Station Hospital

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/QueueManager.php';

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
$queueManager = new QueueManager($db);

// ============================================
// SESSION TIMEOUT CHECK
// ============================================
require_once dirname(__DIR__) . '/includes/SessionManager.php';
$sessionManager = new SessionManager($db);
if (!$sessionManager->checkTimeout()) {
    exit();
}
$sessionManager->logActivity('Viewed registration page');

// ============================================
// CLINICS DATA INGESTION
// ============================================
$query = "SELECT * FROM clinics WHERE is_active = 1 ORDER BY name";
$clinics = $db->query($query)->fetchAll(PDO::FETCH_ASSOC);

// Handle form submission
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['register_patient'])) {
    
    $mrn = trim($_POST['mrn']);
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $dob = $_POST['dob'];
    $gender = $_POST['gender'];
    $contact = trim($_POST['contact_number'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $patient_type = $_POST['patient_type'];
    
    $is_pwd = isset($_POST['is_pwd']) ? 1 : 0;
    $is_senior = isset($_POST['is_senior']) ? 1 : 0;
    $is_pregnant = isset($_POST['is_pregnant']) ? 1 : 0;
    
    $query = "INSERT INTO patients 
              (mrn, first_name, last_name, date_of_birth, gender, 
               contact_number, address, patient_type, is_pwd, is_senior, is_pregnant) 
              VALUES 
              (:mrn, :first_name, :last_name, :dob, :gender, 
               :contact, :address, :patient_type, :is_pwd, :is_senior, :is_pregnant)";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':mrn', $mrn);
    $stmt->bindParam(':first_name', $first_name);
    $stmt->bindParam(':last_name', $last_name);
    $stmt->bindParam(':dob', $dob);
    $stmt->bindParam(':gender', $gender);
    $stmt->bindParam(':contact', $contact);
    $stmt->bindParam(':address', $address);
    $stmt->bindParam(':patient_type', $patient_type);
    $stmt->bindParam(':is_pwd', $is_pwd);
    $stmt->bindParam(':is_senior', $is_senior);
    $stmt->bindParam(':is_pregnant', $is_pregnant);
    
    if ($stmt->execute()) {
        $patient_id = $db->lastInsertId();
        
        if (isset($_POST['clinic_id']) && !empty($_POST['clinic_id'])) {
            $clinic_id = $_POST['clinic_id'];
            $appointment_time = !empty($_POST['appointment_time']) ? $_POST['appointment_time'] : null;
            $queueManager->addToQueue($patient_id, $clinic_id, $appointment_time);
        }
        
        if ($_SESSION['role'] === 'admin') {
            header('Location: ../admin/patients.php?msg=' . urlencode("Successfully registered patient. MRN: " . $mrn));
            exit();
        }

        $success_message = "Patient registered successfully! MRN: " . $mrn;
    } else {
        $error_message = "Error registering patient. Please verify input data values.";
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Registration | 4ID Station Hospital</title>
    
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
                    <span>C</span><span>E</span><span>S</span><span>H</span>
                </div>
                <div class="flex md:hidden md:group-hover/sidebar:flex flex-col items-center animate-[fadeIn_0.2s_ease-in-out]">
                    <h2 class="text-slate-800 dark:text-slate-100 text-sm font-extrabold tracking-tight text-center whitespace-nowrap">4ID Station Hospital</h2>
                    <p class="text-slate-400 dark:text-slate-400 text-[0.65rem] font-bold uppercase tracking-widest text-center whitespace-nowrap mt-0.5">Camp Evangelista</p>
                </div>
            </div>
            
            <nav class="px-3 md:group-hover/sidebar:px-4 transition-all duration-200">
                <ul class="list-none p-0 space-y-1">
                    <?php if ($_SESSION['role'] === 'admin'): ?>
                    <li>
                        <a href="../admin/patients.php" class="flex items-center rounded-xl font-medium transition-all duration-150 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700/50 p-3 justify-center md:justify-start gap-0 md:group-hover/sidebar:gap-3 border-l-4 border-transparent group/link">
                            <div class="w-6 h-6 flex items-center justify-center shrink-0">
                                <i class="fas fa-arrow-left text-base text-slate-400 group-hover/link:text-sky-500 transition-colors"></i>
                            </div>
                            <span class="opacity-100 md:opacity-0 md:group-hover/sidebar:opacity-100 text-[0.85rem] tracking-wide whitespace-nowrap transition-opacity duration-200 origin-left">Patient Directory</span>
                        </a>
                    </li>
                    <?php endif; ?>
                    <li>
                        <a href="registration.php" class="flex items-center rounded-xl font-semibold transition-all duration-150 bg-sky-50 dark:bg-sky-500/10 text-sky-600 dark:text-sky-400 border-l-4 border-sky-500 p-3 justify-center md:justify-start gap-0 md:group-hover/sidebar:gap-3">
                            <div class="w-6 h-6 flex items-center justify-center shrink-0">
                                <i class="fas fa-user-plus text-base"></i>
                            </div>
                            <span class="opacity-100 md:opacity-0 md:group-hover/sidebar:opacity-100 text-[0.85rem] tracking-wide whitespace-nowrap transition-opacity duration-200 origin-left">Register Patient</span>
                        </a>
                    </li>
                </ul>
            </nav>
        </div>
    </aside>

    <main class="min-h-screen ml-0 md:ml-[80px] p-5 md:p-8 transition-all duration-300">
        
        <header class="flex flex-col sm:flex-row justify-between sm:items-center mb-8 pb-5 border-b border-slate-300/90 dark:border-slate-700/80 gap-4">
            <div class="flex items-center gap-4">
                <button id="mobileMenuBtn" class="md:hidden p-2 text-slate-600 dark:text-slate-300 bg-white dark:bg-[#1f2937] border border-slate-300 rounded-xl">
                    <i class="fas fa-bars text-xl"></i>
                </button>
                <div>
                    <h1 class="text-slate-900 dark:text-white text-2xl md:text-3xl font-extrabold tracking-tight mb-0.5">New Patient Setup</h1>
                    <p class="text-slate-500 dark:text-slate-400 text-xs md:text-sm font-medium">Configure internal medical profiles and record tracking tags</p>
                </div>
            </div>
            
            <div class="flex items-center gap-3 md:gap-5">
                <button id="themeToggleBtn" class="w-10 h-10 flex items-center justify-center bg-white dark:bg-[#1f2937] text-slate-500 dark:text-slate-400 border border-slate-300 dark:border-slate-700 rounded-xl hover:text-sky-500 transition-all shadow-sm" title="Toggle Visual Mode">
                    <i id="themeToggleIcon" class="fas fa-moon text-base"></i>
                </button>
                <?php if ($_SESSION['role'] === 'admin'): ?>
                    <a href="../admin/patients.php" class="inline-flex items-center justify-center px-4 py-2.5 bg-white dark:bg-[#1f2937] border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-300 font-bold text-xs uppercase tracking-wider rounded-xl hover:bg-slate-50 dark:hover:bg-slate-800 transition-all shadow-sm"><i class="fas fa-arrow-left mr-2"></i> Back</a>
                <?php endif; ?>
            </div>
        </header>

        <?php if (!empty($success_message)): ?>
            <div class="alert p-4 mb-6 bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-300 dark:border-emerald-500/20 text-emerald-600 dark:text-emerald-400 rounded-2xl flex items-center justify-between shadow-sm">
                <div class="flex items-center"><i class="fas fa-check-circle mr-3 text-base"></i> <span class="text-xs font-bold uppercase tracking-wide"><?= htmlspecialchars($success_message) ?></span></div>
                <button onclick="this.parentElement.remove()" class="text-slate-400 hover:text-emerald-500"><i class="fas fa-times"></i></button>
            </div>
        <?php endif; ?>
        <?php if (!empty($error_message)): ?>
            <div class="alert p-4 mb-6 bg-rose-50 dark:bg-rose-500/10 border border-rose-300 dark:border-rose-500/20 text-rose-600 dark:text-rose-400 rounded-2xl flex items-center justify-between shadow-sm">
                <div class="flex items-center"><i class="fas fa-exclamation-triangle mr-3 text-base"></i> <span class="text-xs font-bold uppercase tracking-wide"><?= htmlspecialchars($error_message) ?></span></div>
                <button onclick="this.parentElement.remove()" class="text-slate-400 hover:text-rose-500"><i class="fas fa-times"></i></button>
            </div>
        <?php endif; ?>

        <section class="bg-white dark:bg-[#1f2937] border border-slate-300 dark:border-slate-700/70 rounded-2xl shadow-sm overflow-hidden">
            <div class="p-5 border-b border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/30 flex items-center gap-2 text-slate-900 dark:text-white font-bold text-sm tracking-wide uppercase">
                <i class="fas fa-user-edit text-sky-500 text-base"></i> Profile Demographic Configuration
            </div>
            
            <div class="p-6 md:p-8">
                <form method="POST" action="">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                        <div class="flex flex-col gap-2">
                            <label class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Medical Record Number (MRN) *</label>
                            <input type="text" name="mrn" class="w-full px-4 py-3 bg-slate-100 dark:bg-[#111827] border border-slate-300 dark:border-slate-700 text-sky-600 dark:text-sky-400 font-mono font-bold rounded-xl focus:outline-none cursor-not-allowed select-all" value="MRN<?= date('Ymd') . rand(100, 999); ?>" readonly required>
                        </div>
                        <div class="flex flex-col gap-2">
                            <label class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Patient Scope Type Classification *</label>
                            <select name="patient_type" id="patientType" class="w-full px-4 py-3 bg-slate-50 dark:bg-[#111827] border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white rounded-xl focus:outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500 text-sm transition-all" required>
                                <option value="">-- Select Designation --</option>
                                <option value="Military Personnel">Military Personnel</option>
                                <option value="Dependent">Dependent</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                        <div class="flex flex-col gap-2">
                            <label class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">First Name *</label>
                            <input type="text" name="first_name" class="w-full px-4 py-3 bg-slate-50 dark:bg-[#111827] border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white rounded-xl focus:outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500 text-sm transition-all" required>
                        </div>
                        <div class="flex flex-col gap-2">
                            <label class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Last Name *</label>
                            <input type="text" name="last_name" class="w-full px-4 py-3 bg-slate-50 dark:bg-[#111827] border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white rounded-xl focus:outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500 text-sm transition-all" required>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                        <div class="flex flex-col gap-2">
                            <label class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Gender Scope *</label>
                            <select name="gender" class="w-full px-4 py-3 bg-slate-50 dark:bg-[#111827] border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white rounded-xl focus:outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500 text-sm transition-all" required>
                                <option value="">-- Select Gender --</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                            </select>
                        </div>
                        <div class="flex flex-col gap-2">
                            <label class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Date of Birth *</label>
                            <input type="date" name="dob" id="dob" class="w-full px-4 py-3 bg-slate-50 dark:bg-[#111827] border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white rounded-xl focus:outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500 text-sm transition-all" required>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                        <div class="flex flex-col gap-2">
                            <label class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Contact Phone Number</label>
                            <input type="tel" name="contact_number" class="w-full px-4 py-3 bg-slate-50 dark:bg-[#111827] border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white rounded-xl focus:outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500 text-sm transition-all">
                        </div>
                        <div class="flex flex-col gap-2">
                            <label class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Home Address</label>
                            <input type="text" name="address" class="w-full px-4 py-3 bg-slate-50 dark:bg-[#111827] border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white rounded-xl focus:outline-none focus:border-sky-500 focus:ring-1 focus:ring-sky-500 text-sm transition-all">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                        <div class="flex flex-col gap-2">
                            <label class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Triage Qualifiers</label>
                            <div class="flex items-center h-full gap-6 px-4 py-3 bg-slate-50 dark:bg-[#111827] border border-slate-300 dark:border-slate-700 rounded-xl">
                                <label class="inline-flex items-center gap-2 text-sm font-semibold cursor-pointer select-none"><input type="checkbox" name="is_pwd" id="isPwd" value="1" class="w-4 h-4 rounded text-sky-500 focus:ring-sky-500 accent-sky-500"> PWD</label>
                                <label class="inline-flex items-center gap-2 text-sm font-semibold cursor-pointer select-none"><input type="checkbox" name="is_senior" id="isSenior" value="1" class="w-4 h-4 rounded text-sky-500 focus:ring-sky-500 accent-sky-500"> Senior</label>
                                <label class="inline-flex items-center gap-2 text-sm font-semibold cursor-pointer select-none"><input type="checkbox" name="is_pregnant" id="isPregnant" value="1" class="w-4 h-4 rounded text-sky-500 focus:ring-sky-500 accent-sky-500"> Pregnant</label>
                            </div>
                        </div>
                    </div>

                    <div id="priorityPreview" class="hidden items-center justify-between p-4 mb-6 bg-sky-50 dark:bg-sky-500/10 border border-sky-300 dark:border-sky-500/20 rounded-xl transition-all">
                        <span class="text-xs font-bold uppercase tracking-wider text-slate-600 dark:text-slate-300">Evaluated Priority Classification Level:</span>
                        <div class="flex items-center gap-3">
                            <span id="priorityBadge" class="px-2.5 py-1 rounded-full text-xs font-extrabold uppercase tracking-wider text-white">PR3</span>
                            <span id="priorityDescription" class="text-xs font-bold uppercase tracking-wide text-slate-700 dark:text-slate-300"></span>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                        <div class="flex flex-col gap-2">
                            <label class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Primary Clinic Allocation Unit</label>
                            <select name="clinic_id" class="w-full px-4 py-3 bg-slate-50 dark:bg-[#111827] border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white rounded-xl focus:outline-none focus:border-sky-500 text-sm">
                                <option value="" disabled selected hidden>Clinics</option>
                                <option value="">Registration</option>
                                <?php foreach ($clinics as $clinic): ?>
                                    <option value="<?= $clinic['id']; ?>">
                                        <?= htmlspecialchars($clinic['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="flex flex-col gap-2">
                            <label class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Target Queue Slot/Time</label>
                            <input type="datetime-local" name="appointment_time" class="w-full px-4 py-3 bg-slate-50 dark:bg-[#111827] border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white rounded-xl focus:outline-none focus:border-sky-500 text-sm">
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-6 border-t border-slate-200 dark:border-slate-700">
                        <button type="reset" class="px-5 py-2.5 bg-slate-100 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-300 font-bold text-xs uppercase tracking-wider rounded-xl hover:bg-slate-200 dark:hover:bg-slate-700 transition-all shadow-sm">Clear Inputs</button>
                        <button type="submit" name="register_patient" class="inline-flex items-center justify-center px-6 py-2.5 bg-sky-600 dark:bg-sky-500 text-white font-bold text-xs uppercase tracking-wider rounded-xl hover:bg-sky-700 dark:hover:bg-sky-600 transition-all shadow-sm"><i class="fas fa-save mr-2 text-sm"></i> Commit Registration</button>
                    </div>
                </form>
            </div>
        </section>
    </main>

    <script>
        // Live Priority Triage Classifier Update Engine
        function updatePriorityPreview() {
            const type = document.getElementById('patientType').value;
            const isPwd = document.getElementById('isPwd').checked;
            const isSenior = document.getElementById('isSenior').checked;
            const isPregnant = document.getElementById('isPregnant').checked;
            
            let priority = 'PR3'; 
            let desc = 'Standard Processing Queue'; 
            let badgeClass = ' bg-sky-500 dark:bg-sky-500/20 text-sky-600 dark:text-sky-400';
            
            if (type === 'Military Personnel') { 
                priority = 'PR1'; 
                desc = 'Active Troop Service Priority'; 
                badgeClass = ' bg-emerald-500 dark:bg-emerald-500/20 text-emerald-600 dark:text-emerald-400'; 
            } else if (isPwd || isSenior || isPregnant) { 
                priority = 'PR2'; 
                desc = 'Special Privilege Allocation'; 
                badgeClass = ' bg-amber-500 dark:bg-amber-500/20 text-amber-600 dark:text-amber-400'; 
            }
            
            const preview = document.getElementById('priorityPreview');
            if (type || isPwd || isSenior || isPregnant) {
                preview.classList.remove('hidden');
                preview.classList.add('flex');
                const badge = document.getElementById('priorityBadge');
                badge.textContent = priority;
                badge.className = 'px-2.5 py-1 rounded-full text-xs font-extrabold uppercase tracking-wider' + badgeClass;
                document.getElementById('priorityDescription').textContent = desc;
            } else {
                preview.classList.remove('flex');
                preview.classList.add('hidden');
            }
        }

        document.getElementById('patientType').addEventListener('change', updatePriorityPreview);
        document.getElementById('isPwd').addEventListener('change', updatePriorityPreview);
        document.getElementById('isSenior').addEventListener('change', updatePriorityPreview);
        document.getElementById('isPregnant').addEventListener('change', updatePriorityPreview);

        // Auto Senior Flag Trigger by DOB Configuration
        document.getElementById('dob')?.addEventListener('change', function() {
            const dob = new Date(this.value);
            const today = new Date();
            let age = today.getFullYear() - dob.getFullYear();
            if (today.getMonth() < dob.getMonth() || (today.getMonth() === dob.getMonth() && today.getDate() < dob.getDate())) age--;
            if (age >= 60) {
                document.getElementById('isSenior').checked = true;
                updatePriorityPreview();
            }
        });

        // Theme Persistent Logic Core
        const themeToggleBtn = document.getElementById('themeToggleBtn');
        const themeToggleIcon = document.getElementById('themeToggleIcon');
        if (localStorage.getItem('theme') === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark'); 
            if(themeToggleIcon) themeToggleIcon.className = 'fas fa-sun text-base text-amber-400';
        }
        if(themeToggleBtn) {
            themeToggleBtn.addEventListener('click', () => {
                if (document.documentElement.classList.contains('dark')) {
                    document.documentElement.classList.remove('dark'); 
                    localStorage.setItem('theme', 'light'); 
                    if(themeToggleIcon) themeToggleIcon.className = 'fas fa-moon text-base';
                } else {
                    document.documentElement.classList.add('dark'); 
                    localStorage.setItem('theme', 'dark'); 
                    if(themeToggleIcon) themeToggleIcon.className = 'fas fa-sun text-base text-amber-400';
                }
            });
        }

        // Sidebar Responsive Toggle Utility
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

        // Dismiss Alerts automatically
        setTimeout(() => {
            document.querySelectorAll('.alert').forEach(alert => {
                alert.style.transition = 'all 0.4s ease-in-out';
                alert.style.opacity = '0';
                alert.style.transform = 'translateY(-10px)';
                setTimeout(() => alert.remove(), 400);
            });
        }, 5000);
    </script>
</body>
</html>