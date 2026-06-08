<?php
// admin/patient-edit.php - Edit Patient Information
// Camp Evangelista Station Hospital

require_once dirname(__DIR__) . '/config/database.php';

session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: ../index.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Get patient ID from URL
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

$message = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_patient'])) {
    
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
    $primary_physician = trim($_POST['primary_physician'] ?? '');
    $status = $_POST['status'] ?? 'Active';
    
    // Validate
    if (empty($first_name) || empty($last_name) || empty($dob) || empty($gender) || empty($patient_type)) {
        $error = "Please fill in all required fields.";
    } else {
        // Update patient
        $query = "UPDATE patients SET 
                    first_name = :first_name,
                    last_name = :last_name,
                    date_of_birth = :dob,
                    gender = :gender,
                    contact_number = :contact,
                    address = :address,
                    patient_type = :patient_type,
                    is_pwd = :is_pwd,
                    is_senior = :is_senior,
                    is_pregnant = :is_pregnant,
                    primary_physician = :primary_physician,
                    status = :status
                  WHERE id = :id";
        
        $stmt = $db->prepare($query);
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
        $stmt->bindParam(':primary_physician', $primary_physician);
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':id', $patient_id);
        
        if ($stmt->execute()) {
            $message = "Patient information updated successfully!";
            // Refresh patient data
            $stmt = $db->prepare("SELECT * FROM patients WHERE id = :id");
            $stmt->bindParam(':id', $patient_id);
            $stmt->execute();
            $patient = $stmt->fetch(PDO::FETCH_ASSOC);
        } else {
            $error = "Failed to update patient. Please try again.";
        }
    }
}

// Get patient queue history
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

// Calculate age
function calculateAge($dob) {
    if (!$dob || $dob == '0000-00-00') return '—';
    $birthDate = new DateTime($dob);
    $today = new DateTime();
    return $today->diff($birthDate)->y;
}

// Get patient status (with fallback)
$patient_status = isset($patient['status']) ? $patient['status'] : 'Active';
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Patient | <?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?> | Camp Evangelista</title>
    
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

    <aside id="sidebar" class="fixed top-0 left-0 h-screen bg-white dark:bg-[#1f2937] border-r border-slate-300/60 dark:border-slate-700/80 shadow-xl md:shadow-none z-[1000] flex flex-col justify-between overflow-x-hidden transition-all duration-300 ease-in-out group/sidebar -translate-x-full md:translate-x-0 w-[260px] md:w-[80px] md:hover:w-[260px]">
        <div>
            <div class="p-4 border-b border-slate-200 dark:border-slate-700/60 mb-5 flex flex-col items-center justify-center min-h-[120px]">
                <div class="hidden md:flex md:group-hover/sidebar:hidden flex-col items-center justify-center font-extrabold text-xl tracking-wider text-sky-600 dark:text-sky-400 leading-tight select-none">
                    <span>C</span><span>E</span><span>S</span><span>H</span>
                </div>
                <div class="flex md:hidden md:group-hover/sidebar:flex flex-col items-center">
                    <h2 class="text-slate-800 dark:text-slate-100 text-sm font-extrabold tracking-tight text-center whitespace-nowrap">4ID Station Hospital</h2>
                    <p class="text-slate-400 dark:text-slate-500 text-[10px] font-bold uppercase tracking-widest text-center whitespace-nowrap mt-1">Camp Evangelista</p>
                </div>
            </div>
            
            <nav class="px-3 md:group-hover/sidebar:px-4 transition-all duration-200">
                <ul class="space-y-1.5 list-none p-0">
                    <li>
                        <a href="dashboard.php" class="flex items-center rounded-xl font-medium transition-all duration-150 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700/50 p-3 justify-center md:justify-start gap-0 md:group-hover/sidebar:gap-4 group/link">
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
                        <a href="queue-monitor.php" class="flex items-center rounded-xl font-medium transition-all duration-150 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700/50 p-3 justify-center md:justify-start gap-0 md:group-hover/sidebar:gap-4 group/link">
                            <div class="w-6 h-6 flex items-center justify-center shrink-0">
                                <i class="fas fa-chart-line text-base text-slate-400 group-hover/link:text-sky-500 transition-colors"></i>
                            </div>
                            <span class="opacity-100 md:opacity-0 md:group-hover/sidebar:opacity-100 text-xs tracking-wide whitespace-nowrap transition-opacity duration-200 origin-left">Queue Monitor</span>
                        </a>
                    </li>
                    <li>
                        <a href="clinic-congestion.php" class="flex items-center rounded-xl font-medium transition-all duration-150 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700/50 p-3 justify-center md:justify-start gap-0 md:group-hover/sidebar:gap-4 group/link">
                            <div class="w-6 h-6 flex items-center justify-center shrink-0">
                                <i class="fas fa-chart-pie text-base text-slate-400 group-hover/link:text-sky-500 transition-colors"></i>
                            </div>
                            <span class="opacity-100 md:opacity-0 md:group-hover/sidebar:opacity-100 text-xs tracking-wide whitespace-nowrap transition-opacity duration-200 origin-left">Clinic Congestion</span>
                        </a>
                    </li>
                    <li>
                        <a href="reports.php" class="flex items-center rounded-xl font-medium transition-all duration-150 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700/50 p-3 justify-center md:justify-start gap-0 md:group-hover/sidebar:gap-4 group/link">
                            <div class="w-6 h-6 flex items-center justify-center shrink-0">
                                <i class="fas fa-chart-bar text-base text-slate-400 group-hover/link:text-sky-500 transition-colors"></i>
                            </div>
                            <span class="opacity-100 md:opacity-0 md:group-hover/sidebar:opacity-100 text-xs tracking-wide whitespace-nowrap transition-opacity duration-200 origin-left">Reports</span>
                        </a>
                    </li>
                    <li>
                        <a href="users.php" class="flex items-center rounded-xl font-medium transition-all duration-150 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700/50 p-3 justify-center md:justify-start gap-0 md:group-hover/sidebar:gap-4 group/link">
                            <div class="w-6 h-6 flex items-center justify-center shrink-0">
                                <i class="fas fa-users-cog text-base text-slate-400 group-hover/link:text-sky-500 transition-colors"></i>
                            </div>
                            <span class="opacity-100 md:opacity-0 md:group-hover/sidebar:opacity-100 text-xs tracking-wide whitespace-nowrap transition-opacity duration-200 origin-left">User Management</span>
                        </a>
                    </li>
                    <li>
                        <a href="login-monitor.php" class="flex items-center rounded-xl font-medium transition-all duration-150 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700/50 p-3 justify-center md:justify-start gap-0 md:group-hover/sidebar:gap-4 group/link">
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

    <main class="min-h-screen ml-0 md:ml-[80px] hover:translate-x-0 transition-all duration-300 px-4 sm:px-8 py-8 lg:pl-12 max-w-[1600px] mx-auto">
        
        <header class="flex flex-col sm:flex-row justify-between sm:items-center mb-8 pb-5 border-b border-slate-200 dark:border-slate-700/80 gap-4">
            <div class="flex items-center gap-4">
                <button id="mobileMenuBtn" class="md:hidden p-2 text-slate-600 dark:text-slate-300 bg-white dark:bg-[#1f2937] border border-slate-300 dark:border-slate-700 rounded-xl shadow-sm">
                    <i class="fas fa-bars text-lg"></i>
                </button>
                <div>
                    <h1 class="text-slate-900 dark:text-white text-2xl font-extrabold tracking-tight flex items-center gap-2">
                        <i class="fas fa-user-edit text-sky-500 text-xl hidden sm:inline"></i> Edit Patient Record
                    </h1>
                    <p class="text-slate-500 dark:text-slate-400 text-xs font-medium">Update patient demographic properties and global registry status parameters</p>
                </div>
            </div>
            
            <div class="flex items-center justify-between sm:justify-end gap-4">
                <div class="text-right text-xs hidden sm:block">
                    <div class="text-slate-700 dark:text-slate-300 font-bold" id="currentDate"></div>
                    <div class="text-sky-600 dark:text-sky-400 font-bold font-mono text-xs mt-0.5" id="currentTime"></div>
                </div>

                <button id="themeToggleBtn" class="w-9 h-9 flex items-center justify-center bg-white dark:bg-[#1f2937] border border-slate-300 dark:border-slate-700 rounded-xl transition-all shadow-sm text-slate-500 dark:text-amber-400" title="Toggle Adaptive Theme Light/Dark Mode">
                    <i id="themeToggleIcon" class="fas fa-moon text-sm"></i>
                </button>

                <div class="w-9 h-9 bg-sky-50 dark:bg-sky-500/10 rounded-full flex items-center justify-center text-sky-600 dark:text-sky-400 border border-slate-200 dark:border-slate-700/60 shadow-sm">
                    <i class="fas fa-user-shield text-sm"></i>
                </div>
            </div>
        </header>

        <section class="bg-white dark:bg-[#1f2937] border border-slate-200 dark:border-slate-700/70 rounded-2xl p-5 mb-6 flex flex-col md:flex-row justify-between items-start md:items-center gap-4 shadow-sm">
            <div>
                <h2 class="text-slate-900 dark:text-white text-lg font-bold tracking-tight"><?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?></h2>
                <div class="flex flex-wrap items-center gap-x-4 gap-y-1 mt-1 text-xs font-semibold text-slate-500 dark:text-slate-400">
                    <span class="flex items-center gap-1.5"><i class="fas fa-id-card text-slate-400"></i> MRN: <b class="text-slate-700 dark:text-slate-200 font-mono"><?php echo htmlspecialchars($patient['mrn']); ?></b></span>
                    <span class="hidden sm:inline text-slate-300 dark:text-slate-700">|</span>
                    <span class="flex items-center gap-1.5"><i class="fas fa-calendar-alt text-slate-400"></i> Age: <b class="text-slate-700 dark:text-slate-200"><?php echo calculateAge($patient['date_of_birth']); ?> years</b></span>
                    <span class="hidden sm:inline text-slate-300 dark:text-slate-700">|</span>
                    <span class="flex items-center gap-1.5"><i class="fas fa-clock text-slate-400"></i> Registered: <b class="text-slate-700 dark:text-slate-200"><?php echo date('M d, Y', strtotime($patient['created_at'])); ?></b></span>
                </div>
            </div>
            <div class="bg-sky-50 dark:bg-sky-500/10 text-sky-600 dark:text-sky-400 px-4 py-2 rounded-xl text-xs font-bold tracking-wide flex items-center gap-2 border border-sky-100 dark:border-sky-500/10 self-stretch md:self-auto justify-center">
                <i class="fas fa-notes-medical"></i> <?php echo count($queue_history); ?> Total System Visits
            </div>
        </section>

        <?php if ($message): ?>
            <div class="alert bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-300 dark:border-emerald-500/30 text-emerald-800 dark:text-emerald-400 rounded-xl p-4 text-xs font-bold uppercase tracking-wide mb-6 flex items-center gap-2.5 shadow-sm">
                <i class="fas fa-check-circle text-base text-emerald-500"></i> <?php echo $message; ?>
            </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert bg-rose-50 dark:bg-rose-500/10 border border-rose-300 dark:border-rose-500/30 text-rose-800 dark:text-rose-400 rounded-xl p-4 text-xs font-bold uppercase tracking-wide mb-6 flex items-center gap-2.5 shadow-sm">
                <i class="fas fa-exclamation-circle text-base text-rose-500"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <nav class="flex flex-wrap gap-3 mb-6">
            <a href="medical_history.php?patient_id=<?php echo $patient_id; ?>" class="inline-flex items-center gap-2 bg-white dark:bg-[#1f2937] border border-slate-300 dark:border-slate-700 hover:border-slate-400 dark:hover:border-slate-600 text-slate-700 dark:text-slate-300 text-xs font-bold px-4 py-2.5 rounded-xl shadow-sm transition-all">
                <i class="fas fa-notes-medical text-sky-500"></i> Medical History
            </a>
            <a href="patient-results.php?id=<?php echo $patient_id; ?>" class="inline-flex items-center gap-2 bg-white dark:bg-[#1f2937] border border-slate-300 dark:border-slate-700 hover:border-slate-400 dark:hover:border-slate-600 text-slate-700 dark:text-slate-300 text-xs font-bold px-4 py-2.5 rounded-xl shadow-sm transition-all">
                <i class="fas fa-file-alt text-sky-500"></i> Lab Results
            </a>
            <a href="../staff/registration.php?edit=<?php echo $patient_id; ?>" class="inline-flex items-center gap-2 bg-white dark:bg-[#1f2937] border border-slate-300 dark:border-slate-700 hover:border-slate-400 dark:hover:border-slate-600 text-slate-700 dark:text-slate-300 text-xs font-bold px-4 py-2.5 rounded-xl shadow-sm transition-all">
                <i class="fas fa-calendar-plus text-sky-500"></i> Schedule Appointment
            </a>
        </nav>

        <div class="space-y-6">
            
            <section class="bg-white dark:bg-[#1f2937] border border-slate-200 dark:border-slate-700/70 rounded-2xl shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b border-slate-200 dark:border-slate-700/60 bg-slate-50/50 dark:bg-slate-800/10 flex items-center justify-between gap-4">
                    <h3 class="text-xs font-extrabold uppercase text-slate-900 dark:text-white tracking-wider flex items-center gap-2">
                        <i class="fas fa-user-md text-sky-500 text-sm"></i> Core Profile Management Fields
                    </h3>
                    <div>
                        <span class="px-3 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider <?php echo $patient_status == 'Active' ? 'bg-emerald-50 dark:bg-emerald-500/10 text-emerald-600 dark:text-emerald-400' : 'bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400'; ?> flex items-center gap-1">
                            <i class="fas <?php echo $patient_status == 'Active' ? 'fa-check-circle' : 'fa-archive'; ?>"></i> <?php echo $patient_status; ?>
                        </span>
                    </div>
                </div>
                
                <div class="p-6">
                    <form method="POST">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                            
                            <div class="flex flex-col gap-1.5">
                                <label class="text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-400">First Name <span class="text-rose-500">*</span></label>
                                <input type="text" name="first_name" class="w-full text-xs font-bold text-slate-800 dark:text-slate-100 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 focus:border-sky-500 focus:ring-1 focus:ring-sky-500 rounded-xl p-3 outline-none transition-all" value="<?php echo htmlspecialchars($patient['first_name']); ?>" required>
                            </div>
                            
                            <div class="flex flex-col gap-1.5">
                                <label class="text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-400">Last Name <span class="text-rose-500">*</span></label>
                                <input type="text" name="last_name" class="w-full text-xs font-bold text-slate-800 dark:text-slate-100 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 focus:border-sky-500 focus:ring-1 focus:ring-sky-500 rounded-xl p-3 outline-none transition-all" value="<?php echo htmlspecialchars($patient['last_name']); ?>" required>
                            </div>
                            
                            <div class="flex flex-col gap-1.5">
                                <label class="text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-400">Date of Birth <span class="text-rose-500">*</span></label>
                                <input type="date" name="dob" class="w-full text-xs font-bold text-slate-800 dark:text-slate-100 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 focus:border-sky-500 focus:ring-1 focus:ring-sky-500 rounded-xl p-3 outline-none transition-all cursor-pointer" value="<?php echo $patient['date_of_birth']; ?>" required>
                            </div>
                            
                            <div class="flex flex-col gap-1.5">
                                <label class="text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-400">Gender <span class="text-rose-500">*</span></label>
                                <select name="gender" class="w-full text-xs font-bold text-slate-800 dark:text-slate-100 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 focus:border-sky-500 focus:ring-1 focus:ring-sky-500 rounded-xl p-3 outline-none transition-all cursor-pointer" required>
                                    <option value="Male" <?php echo $patient['gender'] == 'Male' ? 'selected' : ''; ?>>Male</option>
                                    <option value="Female" <?php echo $patient['gender'] == 'Female' ? 'selected' : ''; ?>>Female</option>
                                    <option value="Other" <?php echo $patient['gender'] == 'Other' ? 'selected' : ''; ?>>Other</option>
                                </select>
                            </div>
                            
                            <div class="flex flex-col gap-1.5">
                                <label class="text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-400">Contact Number</label>
                                <input type="tel" name="contact" class="w-full text-xs font-mono font-bold text-slate-800 dark:text-slate-100 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 focus:border-sky-500 focus:ring-1 focus:ring-sky-500 rounded-xl p-3 outline-none transition-all" value="<?php echo htmlspecialchars($patient['contact_number']); ?>">
                            </div>
                            
                            <div class="flex flex-col gap-1.5">
                                <label class="text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-400">Address</label>
                                <input type="text" name="address" class="w-full text-xs font-bold text-slate-800 dark:text-slate-100 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 focus:border-sky-500 focus:ring-1 focus:ring-sky-500 rounded-xl p-3 outline-none transition-all" value="<?php echo htmlspecialchars($patient['address']); ?>">
                            </div>
                            
                            <div class="flex flex-col gap-1.5">
                                <label class="text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-400">Patient Type <span class="text-rose-500">*</span></label>
                                <select name="patient_type" class="w-full text-xs font-bold text-slate-800 dark:text-slate-100 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 focus:border-sky-500 focus:ring-1 focus:ring-sky-500 rounded-xl p-3 outline-none transition-all cursor-pointer" required>
                                    <option value="military" <?php echo $patient['patient_type'] == 'military' ? 'selected' : ''; ?>>Military Personnel</option>
                                    <option value="dependent" <?php echo $patient['patient_type'] == 'dependent' ? 'selected' : ''; ?>>Dependent</option>
                                </select>
                            </div>
                            
                            <div class="flex flex-col gap-1.5">
                                <label class="text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-400">Primary Physician</label>
                                <input type="text" name="primary_physician" class="w-full text-xs font-bold text-slate-800 dark:text-slate-100 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 focus:border-sky-500 focus:ring-1 focus:ring-sky-500 rounded-xl p-3 outline-none transition-all" value="<?php echo htmlspecialchars($patient['primary_physician'] ?? ''); ?>">
                            </div>
                            
                            <div class="md:col-span-2 flex flex-col gap-2 mt-2">
                                <label class="text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-400">Priority Operational Indexes</label>
                                <div class="bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl p-4 flex flex-wrap gap-x-6 gap-y-3">
                                    <label class="flex items-center gap-2 text-xs font-bold text-slate-700 dark:text-slate-300 cursor-pointer select-none">
                                        <input type="checkbox" name="is_pwd" value="1" <?php echo $patient['is_pwd'] ? 'checked' : ''; ?> class="w-4 h-4 rounded border-slate-300 text-sky-600 focus:ring-sky-500 accent-sky-600 cursor-pointer">
                                        <span>PWD (Person with Disability)</span>
                                    </label>
                                    <label class="flex items-center gap-2 text-xs font-bold text-slate-700 dark:text-slate-300 cursor-pointer select-none">
                                        <input type="checkbox" name="is_senior" value="1" <?php echo $patient['is_senior'] ? 'checked' : ''; ?> class="w-4 h-4 rounded border-slate-300 text-sky-600 focus:ring-sky-500 accent-sky-600 cursor-pointer">
                                        <span>Senior Citizen (60+)</span>
                                    </label>
                                    <label class="flex items-center gap-2 text-xs font-bold text-slate-700 dark:text-slate-300 cursor-pointer select-none">
                                        <input type="checkbox" name="is_pregnant" value="1" <?php echo $patient['is_pregnant'] ? 'checked' : ''; ?> class="w-4 h-4 rounded border-slate-300 text-sky-600 focus:ring-sky-500 accent-sky-600 cursor-pointer">
                                        <span>Pregnant</span>
                                    </label>
                                </div>
                            </div>
                            
                            <div class="flex flex-col gap-1.5">
                                <label class="text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-400">Global Registry Status</label>
                                <select name="status" class="w-full text-xs font-bold text-slate-800 dark:text-slate-100 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 focus:border-sky-500 focus:ring-1 focus:ring-sky-500 rounded-xl p-3 outline-none transition-all cursor-pointer">
                                    <option value="Active" <?php echo $patient_status == 'Active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="Archived" <?php echo $patient_status == 'Archived' ? 'selected' : ''; ?>>Archived</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="flex flex-col sm:flex-row justify-end gap-3 pt-6 mt-6 border-t border-slate-200 dark:border-slate-700/60">
                            <a href="patients.php" class="inline-flex items-center justify-center gap-2 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700/60 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 text-xs font-bold uppercase tracking-wider px-5 py-3 rounded-xl transition-all">
                                <i class="fas fa-arrow-left"></i> Cancel Changes
                            </a>
                            <button type="submit" name="update_patient" class="inline-flex items-center justify-center gap-2 bg-sky-600 hover:bg-sky-500 text-white text-xs font-bold uppercase tracking-wider px-5 py-3 rounded-xl shadow-md hover:shadow-lg transition-all cursor-pointer">
                                <i class="fas fa-save"></i> Save System Registry Entries
                            </button>
                        </div>
                    </form>
                </div>
            </section>

            <section class="bg-white dark:bg-[#1f2937] border border-slate-200 dark:border-slate-700/70 rounded-2xl shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b border-slate-200 dark:border-slate-700/60 bg-slate-50/50 dark:bg-slate-800/10 flex items-center justify-between gap-4">
                    <h3 class="text-xs font-extrabold uppercase text-slate-900 dark:text-white tracking-wider flex items-center gap-2">
                        <i class="fas fa-history text-sky-500 text-sm"></i> Comprehensive Patient Queue Ledger
                    </h3>
                    <span class="text-[10px] font-bold uppercase tracking-wide text-slate-400"><i class="fas fa-clock"></i> Max Bound: Last 20 Visits</span>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50/70 dark:bg-slate-900/40 border-b border-slate-200 dark:border-slate-700 text-[10px] font-bold uppercase text-slate-400 tracking-wider">
                                <th class="p-4">Date</th>
                                <th class="p-4">Time Entry</th>
                                <th class="p-4">Queue Identifier</th>
                                <th class="p-4">Target Clinic Unit</th>
                                <th class="p-4">Priority Code</th>
                                <th class="p-4 text-right">Status Flag</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50 text-xs font-medium">
                            <?php if (empty($queue_history)): ?>
                                <tr>
                                    <td colspan="6" class="p-8 text-center text-slate-400 font-bold uppercase tracking-wider text-[10px]">
                                        <div class="flex flex-col items-center gap-2">
                                            <i class="fas fa-inbox text-2xl text-slate-300 dark:text-slate-700"></i>
                                            <span>No historical execution queue parameters parsed.</span>
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($queue_history as $queue): ?>
                                    <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/20 transition-colors">
                                        <td class="p-4 font-semibold text-slate-700 dark:text-slate-300"><?php echo date('M d, Y', strtotime($queue['registered_at'])); ?></td>
                                        <td class="p-4 text-slate-500"><?php echo date('h:i A', strtotime($queue['registered_at'])); ?></td>
                                        <td class="p-4 font-mono font-bold text-sky-600 dark:text-sky-400"><?php echo htmlspecialchars($queue['queue_number']); ?></td>
                                        <td class="p-4 font-bold text-slate-900 dark:text-white"><?php echo htmlspecialchars($queue['clinic_name']); ?></td>
                                        <td class="p-4">
                                            <?php 
                                                $pr = $queue['priority_level'];
                                                $prColor = $pr == 'PR1' ? 'bg-rose-50 dark:bg-rose-500/10 text-rose-600 dark:text-rose-400' : ($pr == 'PR2' ? 'bg-amber-50 dark:bg-amber-500/10 text-amber-600 dark:text-amber-400' : 'bg-emerald-50 dark:bg-emerald-500/10 text-emerald-600 dark:text-emerald-400');
                                            ?>
                                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase tracking-wide <?php echo $prColor; ?>">
                                                <?php echo $pr; ?>
                                            </span>
                                        </td>
                                        <td class="p-4 text-right">
                                            <?php 
                                                $qStatus = $queue['status'];
                                                $stColor = $qStatus == 'completed' ? 'bg-emerald-50 dark:bg-emerald-500/10 text-emerald-600 dark:text-emerald-400' : ($qStatus == 'in-progress' ? 'bg-sky-50 dark:bg-sky-500/10 text-sky-600 dark:text-sky-400 animate-pulse' : 'bg-amber-50 dark:bg-amber-500/10 text-amber-600 dark:text-amber-400');
                                            ?>
                                            <span class="inline-block px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase tracking-wide <?php echo $stColor; ?>">
                                                <?php echo ucfirst($qStatus); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
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

        // Auto-dismiss execution banner notification loops safely
        setTimeout(() => {
            document.querySelectorAll('.alert').forEach(alert => {
                alert.style.transition = 'all 0.4s cubic-bezier(0.4, 0, 0.2, 1)';
                alert.style.opacity = '0';
                alert.style.transform = 'translateY(-8px)';
                setTimeout(() => alert.remove(), 400);
            });
        }, 5000);

        // Mobile responsive drawer action handler
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

        // Color Mode Rule Engine Sync Matrix
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
    </script>
</body>
</html>