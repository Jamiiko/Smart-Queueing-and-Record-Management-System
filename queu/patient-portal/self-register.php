<?php
// patient-portal/self-register.php - Patient Self Registration
// Camp Evangelista Station Hospital

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/QueueManager.php';

$database = new Database();
$db = $database->getConnection();
$queueManager = new QueueManager($db);

$error_message = '';
$registered_patient = null;

// Get all clinics
$query = "SELECT * FROM clinics WHERE is_active = 1 ORDER BY id";
$clinics = $db->query($query)->fetchAll(PDO::FETCH_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['self_register'])) {
    
    // Validate required fields
    $required_fields = ['first_name', 'last_name', 'dob', 'gender', 'patient_type'];
    $missing = [];
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            $missing[] = $field;
        }
    }
    
    if (empty($missing)) {
        // Generate Medical Record Number
        $mrn = 'MRN' . date('Ymd') . rand(100, 999);
        
        // Get form data
        $first_name = trim($_POST['first_name']);
        $last_name = trim($_POST['last_name']);
        $dob = $_POST['dob'];
        $gender = $_POST['gender'];
        $contact = trim($_POST['contact'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $patient_type = $_POST['patient_type'];
        $is_pwd = isset($_POST['is_pwd']) ? 1 : 0;
        $is_senior = isset($_POST['is_senior']) ? 1 : 0;
        $is_pregnant = isset($_POST['is_pregnant']) ? 1 : 0;
        
        // Insert patient data
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
            
            // For military personnel - register to least congested clinic first
            if ($patient_type == 'military') {
                
                $clinics_by_congestion = $queueManager->findLeastCongestedClinic();
                
                if (empty($clinics_by_congestion)) {
                    $error_message = "No clinics available for registration.";
                } else {
                    $first_clinic = $clinics_by_congestion[0];
                    $clinic_id = $first_clinic['id'];
                    
                    $batch_info = $queueManager->getCurrentBatch();
                    $batch_hour = $batch_info['is_full'] ? $batch_info['next_hour'] : $batch_info['current_hour'];
                    
                    $queue_result = $queueManager->addToQueue($patient_id, $clinic_id, null, $batch_hour);
                    
                    if ($queue_result['success']) {
                        $registered_patient = [
                            'queue_number' => $queue_result['queue_number'],
                            'transaction_token' => $queue_result['transaction_token']
                        ];
                    } else {
                        $error_message = "Error adding to queue: " . ($queue_result['error'] ?? 'Unknown error');
                    }
                }
            } 
            else {
                $queue_result = null;
                if (isset($_POST['clinic_id']) && !empty($_POST['clinic_id'])) {
                    $clinic_id = $_POST['clinic_id'];
                    $appointment_time = !empty($_POST['appointment_time']) ? $_POST['appointment_time'] : null;
                    $queue_result = $queueManager->addToQueue($patient_id, $clinic_id, $appointment_time);
                }
                
                if ($queue_result && $queue_result['success']) {
                    $registered_patient = [
                        'queue_number' => $queue_result['queue_number'],
                        'transaction_token' => $queue_result['transaction_token']
                    ];
                } else {
                    // Registered but no queue
                    $registered_patient = [
                        'queue_number' => null,
                        'transaction_token' => null
                    ];
                }
            }
        } else {
            $error_message = "Registration failed. Please try again.";
        }
    } else {
        $error_message = "Please fill in all required fields.";
    }
}

// If patient is registered, redirect to ticket page (in same folder)
if ($registered_patient && $registered_patient['transaction_token']) {
    header('Location: print-ticket.php?token=' . urlencode($registered_patient['transaction_token']));
    exit();
}
?>

<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Self Registration | Camp Evangelista Hospital</title>
    
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
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-4px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body class="bg-slate-50 dark:bg-[#111827] text-slate-800 dark:text-slate-100 font-sans antialiased min-h-screen flex flex-col transition-colors duration-200 relative">

    <div class="bg-teal-600 dark:bg-teal-500/10 dark:border-b dark:border-teal-500/20 text-white dark:text-teal-400 text-center py-2 px-4 text-xs font-bold tracking-widest uppercase flex items-center justify-center gap-2 select-none shadow-sm z-50">
        <span class="inline-block w-2 h-2 rounded-full bg-white dark:bg-teal-400 animate-ping"></span>
        <i class="fas fa-desktop text-sm"></i> Active Self-Registration Kiosk Terminal
    </div>

    <div class="fixed right-3 md:right-5 top-1/2 -translate-y-1/2 z-50 flex flex-col gap-3.5 group/dock">
        <a href="track-queue.php" class="group/btn flex items-center justify-end gap-3 bg-white dark:bg-[#1f2937] hover:bg-sky-50 dark:hover:bg-sky-950/40 text-slate-600 dark:text-slate-300 hover:text-sky-600 dark:hover:text-sky-400 p-3.5 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-xl transition-all duration-300 hover:-translate-x-1.5">
            <span class="text-xs font-bold tracking-wide max-w-0 overflow-hidden opacity-0 group-hover/btn:max-w-[120px] group-hover/btn:opacity-100 transition-all duration-300 ease-in-out whitespace-nowrap pl-0 group-hover/btn:pl-1">Live Tracker</span>
            <div class="w-5 h-5 flex items-center justify-center shrink-0">
                <i class="fas fa-search text-base"></i>
            </div>
        </a>
        
        <a href="../index.php" class="group/btn flex items-center justify-end gap-3 bg-white dark:bg-[#1f2937] hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white p-3.5 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-xl transition-all duration-300 hover:-translate-x-1.5">
            <span class="text-xs font-bold tracking-wide max-w-0 overflow-hidden opacity-0 group-hover/btn:max-w-[120px] group-hover/btn:opacity-100 transition-all duration-300 ease-in-out whitespace-nowrap pl-0 group-hover/btn:pl-1">Admin Auth</span>
            <div class="w-5 h-5 flex items-center justify-center shrink-0">
                <i class="fas fa-lock text-base"></i>
            </div>
        </a>
    </div>

    <header class="bg-white dark:bg-[#1f2937] border-b border-slate-200 dark:border-slate-800 py-4 px-6 sticky top-0 z-40 shadow-sm transition-colors">
        <div class="max-w-5xl mx-auto flex justify-between items-center gap-4">
            <div class="flex items-center gap-3">
                <img src="../assets/images/logo.png" alt="CESH Logo" class="max-w-[42px] h-auto dark:brightness-110 dark:contrast-125" onerror="this.style.display='none'">
                <div>
                    <h1 class="text-base font-extrabold text-slate-900 dark:text-white tracking-tight leading-tight">4ID Station Hospital</h1>
                    <p class="text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-widest mt-0.5">Camp Evangelista • Patient Entry System</p>
                </div>
            </div>
            
            <div class="flex items-center gap-3">
                <div class="hidden lg:inline-flex bg-sky-50 dark:bg-sky-500/10 text-sky-600 dark:text-sky-400 text-xs font-bold px-4 py-2 rounded-full items-center gap-2 border border-sky-100 dark:border-sky-900/30">
                    <i class="fas fa-user-plus text-xs"></i> Check-in Mode
                </div>
                
                <button id="themeToggleBtn" class="w-10 h-10 flex items-center justify-center rounded-xl bg-slate-50 dark:bg-[#111827] border border-slate-200 dark:border-slate-800 text-slate-500 dark:text-amber-400 shadow-sm hover:border-sky-500 transition-colors">
                    <i id="themeToggleIcon" class="fas fa-moon text-base"></i>
                </button>
            </div>
        </div>
    </header>

    <main class="flex-1 max-w-3xl w-full mx-auto px-4 py-8 md:py-12 pr-16 sm:pr-4">
        
        <div class="flex items-center justify-center gap-12 max-w-sm mx-auto mb-10 select-none">
            <div class="flex flex-col items-center gap-2 group">
                <div class="w-11 h-11 rounded-2xl bg-sky-600 text-white dark:bg-sky-500 font-extrabold text-sm flex items-center justify-center shadow-md shadow-sky-600/20 ring-4 ring-sky-100 dark:ring-sky-950/50 transition-all">1</div>
                <span class="text-xs font-bold text-sky-600 dark:text-sky-400 tracking-wide">Register Account</span>
            </div>
            
            <div class="w-16 h-0.5 bg-slate-200 dark:bg-slate-800 rounded-full shrink-0 -mt-5"></div>
            
            <div class="flex flex-col items-center gap-2 opacity-50">
                <div class="w-11 h-11 rounded-2xl bg-white dark:bg-[#1f2937] border-2 border-slate-200 dark:border-slate-700 text-slate-400 dark:text-slate-500 font-bold text-sm flex items-center justify-center">2</div>
                <span class="text-xs font-semibold text-slate-500 dark:text-slate-400 tracking-wide">Get Ticket</span>
            </div>
        </div>

        <?php if ($error_message): ?>
            <div class="p-4 mb-6 bg-rose-50 dark:bg-rose-500/10 border border-rose-300 dark:border-rose-500/20 text-rose-600 dark:text-rose-400 rounded-2xl flex items-center gap-3 shadow-sm animate-[fadeIn_0.25s_ease-out]">
                <i class="fas fa-exclamation-triangle text-base shrink-0"></i> 
                <span class="text-xs font-bold uppercase tracking-wide leading-relaxed"><?= htmlspecialchars($error_message) ?></span>
            </div>
        <?php endif; ?>
        
        <div class="bg-white dark:bg-[#1f2937] border border-slate-200 dark:border-slate-800 rounded-3xl shadow-md overflow-hidden transition-all duration-300">
            <div class="px-6 py-5 border-b border-slate-100 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-800/30 flex items-center gap-3">
                <div class="w-8 h-8 rounded-lg bg-sky-500/10 flex items-center justify-center text-sky-500"><i class="fas fa-notes-medical"></i></div>
                <h2 class="text-sm font-extrabold text-slate-900 dark:text-white uppercase tracking-wider">Patient Demographic Credentials</h2>
            </div>
            
            <div class="p-6 md:p-8">
                <form method="POST" action="" id="registrationForm" class="space-y-8">
                    
                    <div class="space-y-4">
                        <div class="text-[11px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-widest flex items-center gap-2 mb-2">
                            <i class="fas fa-user-circle text-sky-500"></i> Personal Information Matrix
                        </div>
                        
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div class="flex flex-col gap-1.5">
                                <label class="text-xs font-bold text-slate-500 dark:text-slate-400 tracking-wide">First Name <span class="text-rose-500">*</span></label>
                                <input type="text" name="first_name" required autocomplete="off" class="w-full px-4 py-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-[#111827] text-slate-900 dark:text-white text-sm font-medium focus:outline-none focus:ring-1 focus:ring-sky-500 focus:border-sky-500 transition-all">
                            </div>
                            <div class="flex flex-col gap-1.5">
                                <label class="text-xs font-bold text-slate-500 dark:text-slate-400 tracking-wide">Last Name <span class="text-rose-500">*</span></label>
                                <input type="text" name="last_name" required autocomplete="off" class="w-full px-4 py-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-[#111827] text-slate-900 dark:text-white text-sm font-medium focus:outline-none focus:ring-1 focus:ring-sky-500 focus:border-sky-500 transition-all">
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div class="flex flex-col gap-1.5">
                                <label class="text-xs font-bold text-slate-500 dark:text-slate-400 tracking-wide">Date of Birth <span class="text-rose-500">*</span></label>
                                <input type="date" name="dob" id="dob" required max="<?php echo date('Y-m-d', strtotime('-1 day')); ?>" class="w-full px-4 py-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-[#111827] text-slate-900 dark:text-white text-sm font-mono focus:outline-none focus:ring-1 focus:ring-sky-500 focus:border-sky-500 transition-all">
                            </div>
                            <div class="flex flex-col gap-1.5">
                                <label class="text-xs font-bold text-slate-500 dark:text-slate-400 tracking-wide">Biological Gender <span class="text-rose-500">*</span></label>
                                <select name="gender" required class="w-full px-4 py-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-[#111827] text-slate-900 dark:text-white text-sm font-medium focus:outline-none focus:ring-1 focus:ring-sky-500 focus:border-sky-500 transition-all">
                                    <option value="" class="text-slate-400">Select Gender Mapping</option>
                                    <option value="Male">Male</option>
                                    <option value="Female">Female</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div class="flex flex-col gap-1.5">
                                <label class="text-xs font-bold text-slate-500 dark:text-slate-400 tracking-wide">Contact Number</label>
                                <input type="tel" name="contact" placeholder="+63 9XX XXX XXXX" autocomplete="off" class="w-full px-4 py-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-[#111827] text-slate-900 dark:text-white text-sm font-medium focus:outline-none focus:ring-1 focus:ring-sky-500 focus:border-sky-500 transition-all">
                            </div>
                            <div class="flex flex-col gap-1.5">
                                <label class="text-xs font-bold text-slate-500 dark:text-slate-400 tracking-wide">Complete Residential Address</label>
                                <input type="text" name="address" placeholder="Barangay, Municipality, Province" autocomplete="off" class="w-full px-4 py-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-[#111827] text-slate-900 dark:text-white text-sm font-medium focus:outline-none focus:ring-1 focus:ring-sky-500 focus:border-sky-500 transition-all">
                            </div>
                        </div>
                    </div>
                    
                    <div class="space-y-5">
                        <div class="text-[11px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-widest flex items-center gap-2 mb-2">
                            <i class="fas fa-tags text-sky-500"></i> Operational Context & Classification
                        </div>
                        
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div class="flex flex-col gap-1.5">
                                <label class="text-xs font-bold text-slate-500 dark:text-slate-400 tracking-wide">Classification Type <span class="text-rose-500">*</span></label>
                                <select name="patient_type" id="patientType" required class="w-full px-4 py-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-[#111827] text-slate-900 dark:text-white text-sm font-bold focus:outline-none focus:ring-1 focus:ring-sky-500 focus:border-sky-500 transition-all">
                                    <option value="">Choose Registry Scope</option>
                                    <option value="dependent">Dependent / Civilian</option>
                                    <option value="military">Active Military Personnel</option>
                                </select>
                            </div>
                            
                            <div class="flex flex-col gap-1.5" id="clinicSelection">
                                <label class="text-xs font-bold text-slate-500 dark:text-slate-400 tracking-wide">Target Diagnostic Clinic</label>
                                <select name="clinic_id" class="w-full px-4 py-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-[#111827] text-slate-900 dark:text-white text-sm font-medium focus:outline-none focus:ring-1 focus:ring-sky-500 focus:border-sky-500 transition-all">
                                    <option value="">-- Core Data Base Only (No Queue Log) --</option>
                                    <?php foreach ($clinics as $clinic): ?>
                                        <option value="<?php echo $clinic['id']; ?>"><?php echo htmlspecialchars($clinic['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="flex flex-col gap-2">
                            <label class="text-xs font-bold text-slate-500 dark:text-slate-400 tracking-wide">Physiological / Priority Context Attributes</label>
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 bg-slate-50 dark:bg-slate-900 border border-slate-100 dark:border-slate-800 p-4 rounded-2xl">
                                <label class="flex items-center gap-3 px-3 py-2 bg-white dark:bg-[#1f2937] rounded-xl border border-slate-200 dark:border-slate-700/60 cursor-pointer select-none transition-all hover:border-slate-300">
                                    <input type="checkbox" name="is_pwd" id="isPwd" value="1" class="w-4 h-4 rounded text-teal-600 focus:ring-teal-500 bg-slate-100 border-slate-300 accent-teal-600">
                                    <span class="text-xs font-bold text-slate-700 dark:text-slate-300">PWD Attribute</span>
                                </label>
                                <label class="flex items-center gap-3 px-3 py-2 bg-white dark:bg-[#1f2937] rounded-xl border border-slate-200 dark:border-slate-700/60 cursor-pointer select-none transition-all hover:border-slate-300">
                                    <input type="checkbox" name="is_senior" id="isSenior" value="1" class="w-4 h-4 rounded text-teal-600 focus:ring-teal-500 bg-slate-100 border-slate-300 accent-teal-600">
                                    <span class="text-xs font-bold text-slate-700 dark:text-slate-300">Senior Citizen</span>
                                </label>
                                <label class="flex items-center gap-3 px-3 py-2 bg-white dark:bg-[#1f2937] rounded-xl border border-slate-200 dark:border-slate-700/60 cursor-pointer select-none transition-all hover:border-slate-300">
                                    <input type="checkbox" name="is_pregnant" id="isPregnant" value="1" class="w-4 h-4 rounded text-teal-600 focus:ring-teal-500 bg-slate-100 border-slate-300 accent-teal-600">
                                    <span class="text-xs font-bold text-slate-700 dark:text-slate-300">Gestational Care</span>
                                </label>
                            </div>
                        </div>
                        
                        <div id="militaryNote" class="hidden p-4 bg-sky-50 dark:bg-sky-500/10 border border-sky-200 dark:border-sky-500/20 text-sky-700 dark:text-sky-400 rounded-2xl text-xs font-medium leading-relaxed animate-[fadeIn_0.2s_ease-out]">
                            <div class="flex items-start gap-2.5">
                                <i class="fas fa-shield-alt text-base mt-0.5 shrink-0"></i>
                                <span><strong>Operational Doctrine Mandate:</strong> Active Combatants/Military personnel are routed dynamically across localized diagnostic pathways sequentially via prioritized cross-congestion matrices.</span>
                            </div>
                        </div>
                        
                        <div id="priorityPreview" class="hidden bg-slate-50 dark:bg-slate-900 border border-slate-100 dark:border-slate-800 p-4 rounded-2xl items-center justify-between flex-wrap gap-4 animate-[fadeIn_0.2s_ease-out]">
                            <div class="flex items-center gap-2 text-xs font-bold text-slate-600 dark:text-slate-400">
                                <i class="fas fa-info-circle text-sky-500 text-sm"></i> Dynamic Processing Clearance Tier:
                            </div>
                            <div class="flex items-center gap-2">
                                <span id="priorityBadge" class="font-mono font-black text-xs px-3 py-1 rounded-md tracking-wider">PR3</span>
                                <span id="priorityDescription" class="text-xs font-bold text-slate-800 dark:text-slate-200">Regular Patient Portfolio</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="pt-2">
                        <label class="flex items-start gap-3 p-4 bg-slate-50 dark:bg-slate-900 border border-slate-100 dark:border-slate-800 rounded-2xl cursor-pointer select-none">
                            <input type="checkbox" id="agreeTerms" required class="w-4 h-4 rounded text-teal-600 focus:ring-teal-500 bg-slate-100 border-slate-300 accent-teal-600 mt-0.5 shrink-0">
                            <span class="text-xs font-semibold text-slate-600 dark:text-slate-400 leading-relaxed">I execute validation that all individual properties provided above match valid tracking identities and physiological state parameters under the medical authority context rules.</span>
                        </label>
                    </div>
                    
                    <button type="submit" name="self_register" id="registerBtn" class="w-full inline-flex items-center justify-center gap-2 px-6 py-4 bg-teal-600 hover:bg-teal-700 dark:bg-teal-600 dark:hover:bg-teal-700 text-white rounded-2xl font-bold text-sm uppercase tracking-wider shadow-md transition-all active:scale-[0.99]">
                        <i class="fas fa-check-circle text-base"></i> Process Registry & Spool Entry Ticket
                    </button>
                </form>
            </div>
        </div>
    </main>

    <footer class="bg-white dark:bg-[#1f2937] border-t border-slate-200 dark:border-slate-800 py-6 px-6 mt-12 transition-colors">
        <div class="max-w-5xl mx-auto flex justify-center items-center text-xs font-semibold text-slate-500 dark:text-slate-400">
            <div class="flex items-center gap-1.5 font-medium text-center">
                <i class="fas fa-shield-alt text-slate-400"></i> <?php echo date('Y'); ?> 4th Infantry Division • Camp Evangelista Station Hospital
            </div>
        </div>
    </footer>

    <script>
        // Check local caching or browser profiles for active application theme variables
        if (localStorage.getItem('theme') === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
            document.getElementById('themeToggleIcon').className = 'fas fa-sun text-lg text-amber-400';
        } else {
            document.documentElement.classList.remove('dark');
            document.getElementById('themeToggleIcon').className = 'fas fa-moon text-lg text-slate-500';
        }

        // Handle live visual theme changes via toggle trigger context
        document.getElementById('themeToggleBtn').addEventListener('click', () => {
            if (document.documentElement.classList.contains('dark')) {
                document.documentElement.classList.remove('dark');
                document.getElementById('themeToggleIcon').className = 'fas fa-moon text-lg text-slate-500';
                localStorage.setItem('theme', 'light');
            } else {
                document.documentElement.classList.add('dark');
                document.getElementById('themeToggleIcon').className = 'fas fa-sun text-lg text-amber-400';
                localStorage.setItem('theme', 'dark');
            }
        });

        // Real-time Priority Preview Matrix and Component Class Modifiers
        function updatePriorityPreview() {
            const patientType = document.getElementById('patientType').value;
            const isPwd = document.getElementById('isPwd').checked;
            const isSenior = document.getElementById('isSenior').checked;
            const isPregnant = document.getElementById('isPregnant').checked;
            
            const militaryNote = document.getElementById('militaryNote');
            const clinicSelection = document.getElementById('clinicSelection');
            const registerBtn = document.getElementById('registerBtn');
            const preview = document.getElementById('priorityPreview');
            const badge = document.getElementById('priorityBadge');
            const desc = document.getElementById('priorityDescription');
            
            let priority = 'PR3';
            let badgeClasses = 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-900/40';
            let description = 'Regular Patient Portfolio';
            
            if (patientType === 'military') {
                priority = 'PR1';
                badgeClasses = 'bg-rose-50 text-rose-700 dark:bg-rose-950/40 dark:text-rose-400 border border-rose-200 dark:border-rose-900/40';
                description = 'Active Combatant / Tactical Priority Clearance';
                
                militaryNote.classList.remove('hidden');
                clinicSelection.classList.add('hidden');
                
                registerBtn.className = "w-full inline-flex items-center justify-center gap-2 px-6 py-4 bg-rose-600 hover:bg-rose-700 text-white rounded-2xl font-bold text-sm uppercase tracking-wider shadow-md shadow-rose-600/10 transition-all active:scale-[0.99]";
            } else {
                militaryNote.classList.add('hidden');
                clinicSelection.classList.remove('hidden');
                
                registerBtn.className = "w-full inline-flex items-center justify-center gap-2 px-6 py-4 bg-teal-600 hover:bg-teal-700 text-white rounded-2xl font-bold text-sm uppercase tracking-wider shadow-md shadow-teal-600/10 transition-all active:scale-[0.99]";
                
                if (isPwd || isSenior || isPregnant) {
                    priority = 'PR2';
                    badgeClasses = 'bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-400 border border-amber-200 dark:border-amber-900/40';
                    
                    let conditions = [];
                    if (isPwd) conditions.push('PWD');
                    if (isSenior) conditions.push('Senior');
                    if (isPregnant) conditions.push('Gestational');
                    description = 'Priority Care Routine (' + conditions.join(', ') + ')';
                }
            }
            
            if (patientType || isPwd || isSenior || isPregnant) {
                preview.classList.remove('hidden');
                preview.classList.add('flex');
                badge.className = 'font-mono font-black text-xs px-3 py-1 rounded-md tracking-wider' + badgeClasses;
                badge.textContent = priority;
                desc.textContent = description;
            } else {
                preview.classList.add('hidden');
                preview.classList.remove('flex');
            }
        }
        
        // Element Event Mapping Hooks
        document.getElementById('patientType')?.addEventListener('change', updatePriorityPreview);
        document.getElementById('isPwd')?.addEventListener('change', updatePriorityPreview);
        document.getElementById('isSenior')?.addEventListener('change', updatePriorityPreview);
        document.getElementById('isPregnant')?.addEventListener('change', updatePriorityPreview);
        
        // Automated DoB Parser checking age thresholds against Senior Citizen validation parameters
        document.getElementById('dob')?.addEventListener('change', function() {
            if (!this.value) return;
            const dob = new Date(this.value);
            const today = new Date();
            let age = today.getFullYear() - dob.getFullYear();
            const monthDiff = today.getMonth() - dob.getMonth();
            
            if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < dob.getDate())) {
                age--;
            }
            
            if (age >= 60 && !document.getElementById('isSenior').checked) {
                document.getElementById('isSenior').checked = true;
                updatePriorityPreview();
            }
        });
        
        // Form compilation init deployment
        updatePriorityPreview();
    </script>
</body>
</html>