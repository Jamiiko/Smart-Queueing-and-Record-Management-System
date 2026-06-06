<?php
// staff/registration.php - Patient Registration Form Engine
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
// DATABASE CONNECTION
// ============================================
$database = new Database();
$db = $database->getConnection();
$queueManager = new QueueManager($db);

// ============================================
// SESSION TIMEOUT CHECK (After $db exists)
// ============================================
require_once dirname(__DIR__) . '/includes/SessionManager.php';
$sessionManager = new SessionManager($db);
if (!$sessionManager->checkTimeout()) {
    exit(); // Already redirected to login
}
$sessionManager->logActivity('Viewed registration page');

// Get clinics for dropdown
$query = "SELECT * FROM clinics WHERE is_active = 1 ORDER BY name";
$clinics = $db->query($query)->fetchAll(PDO::FETCH_ASSOC);

// Handle form submission
$success_message = '';
$error_message = '';
$last_registered_patient = null;

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['register_patient'])) {
    
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
    
    // Insert patient data matching the exact schema configurations
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
        
        // Add to queue if clinic selected
        $queue_result = null;
        if (isset($_POST['clinic_id']) && !empty($_POST['clinic_id'])) {
            $clinic_id = $_POST['clinic_id'];
            $appointment_time = !empty($_POST['appointment_time']) ? $_POST['appointment_time'] : null;
            $queue_result = $queueManager->addToQueue($patient_id, $clinic_id, $appointment_time);
        }
        
        // Store last registered patient for token/print actions
        $last_registered_patient = [
            'patient_id' => $patient_id,
            'mrn' => $mrn,
            'queue_number' => $queue_result['queue_number'] ?? null,
            'transaction_token' => $queue_result['transaction_token'] ?? null
        ];
        
        // If logged in as an administrator, safely push them back to the unaltered table ledger view
        if ($_SESSION['role'] === 'admin') {
            header('Location: ../admin/patients.php?msg=' . urlencode("Patient profiles dynamically updated. New MRN issued: " . $mrn));
            exit();
        }

        // Standard staff success view message context
        $success_message = "Patient registered successfully!";
        $success_message .= "<br><strong>MRN:</strong> " . $mrn;
        if ($queue_result && $queue_result['success']) {
            $success_message .= "<br><strong>Queue Number:</strong> " . $queue_result['queue_number'];
            $success_message .= "<br><strong>Transaction Token:</strong> <code>" . $queue_result['transaction_token'] . "</code>";
        }
    } else {
        $error_message = "Error registering patient. Please try again.";
    }
}

// Get today's queue stats for display
$query = "SELECT 
            COUNT(*) as total_today,
            SUM(CASE WHEN priority_level = 'PR1' THEN 1 ELSE 0 END) as pr1_count,
            SUM(CASE WHEN priority_level = 'PR2' THEN 1 ELSE 0 END) as pr2_count,
            SUM(CASE WHEN priority_level = 'PR3' THEN 1 ELSE 0 END) as pr3_count
          FROM queue_entries 
          WHERE DATE(registered_at) = CURDATE()";
$stats = $db->query($query)->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Registration | Staff | Camp Evangelista Hospital</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    
    <style>
        :root {
            --soft-blue: #4A90E2;
            --soft-blue-dark: #3A7BC8;
            --soft-blue-light: #E7F3FB;
            --teal: #009688;
            --teal-dark: #00796B;
            --soft-green: #A4D1B1;
            --warm-yellow: #FFB84D;
            --light-coral: #FF6F61;
            --white: #FFFFFF;
            --light-gray: #F2F2F2;
            --dark-gray: #212121;
            --charcoal: #333333;
            --border-light: #E5E9F0;
            --shadow-sm: 0 2px 8px rgba(0, 0, 0, 0.04);
            --shadow-md: 0 4px 16px rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 8px 24px rgba(0, 0, 0, 0.08);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: var(--light-gray);
            color: var(--charcoal);
            line-height: 1.5;
        }

        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: 280px;
            height: 100vh;
            background: var(--white);
            box-shadow: var(--shadow-md);
            z-index: 1000;
            overflow-y: auto;
            border-right: 1px solid var(--border-light);
        }

        .sidebar-logo {
            padding: 28px 24px;
            border-bottom: 1px solid var(--border-light);
            margin-bottom: 24px;
        }

        .sidebar-logo h2 {
            color: var(--soft-blue);
            font-size: 1.1rem;
            font-weight: 700;
            letter-spacing: -0.3px;
            margin-bottom: 4px;
        }

        .sidebar-logo p {
            color: var(--charcoal);
            font-size: 0.7rem;
            opacity: 0.7;
        }

        .nav-menu { list-style: none; padding: 0 16px; }
        .nav-item { margin-bottom: 4px; }
        .nav-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            border-radius: 12px;
            color: var(--charcoal);
            text-decoration: none;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .nav-link i { width: 22px; color: var(--soft-blue); font-size: 1.1rem; }
        .nav-link:hover { background: var(--soft-blue-light); color: var(--soft-blue); }
        .nav-link.active { background: var(--soft-blue); color: white; }
        .nav-link.active i { color: white; }

        .main-content { margin-left: 280px; padding: 28px 36px; min-height: 100vh; }
        .top-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 32px; padding-bottom: 20px; border-bottom: 1px solid var(--border-light); }
        .page-title h1 { color: var(--dark-gray); font-size: 1.75rem; font-weight: 600; letter-spacing: -0.02em; margin-bottom: 4px; }
        .page-title p { color: var(--charcoal); font-size: 0.85rem; opacity: 0.7; }
        
        .alert { padding: 16px; border-radius: 12px; margin-bottom: 24px; font-size: 0.95rem; display: flex; align-items: flex-start; gap: 12px; }
        .alert-success { background-color: #E8F5E9; border: 1px solid #C8E6C9; color: #2E7D32; }
        .alert-error { background-color: #FFEBEE; border: 1px solid #FFCDD2; color: #C62828; }

        .form-panel { background: var(--white); border-radius: 16px; border: 1px solid var(--border-light); box-shadow: var(--shadow-sm); overflow: hidden; margin-bottom: 24px; }
        .panel-header { padding: 20px 24px; border-bottom: 1px solid var(--border-light); background: var(--white); font-weight: 600; color: var(--dark-gray); display: flex; align-items: center; gap: 10px; }
        .panel-body { padding: 24px; }
        
        .form-row { display: grid; grid-template-columns: repeat(2, 1fr); gap: 24px; margin-bottom: 20px; }
        @media (max-width: 768px) { .form-row { grid-template-columns: 1fr; } }
        
        .form-group { display: flex; flex-direction: column; gap: 6px; }
        .form-group label { font-size: 0.85rem; font-weight: 600; color: var(--charcoal); }
        .form-control { padding: 12px 16px; border: 1px solid var(--border-light); border-radius: 10px; font-size: 0.95rem; background: #F8FAFC; transition: all 0.2s; }
        .form-control:focus { outline: none; border-color: var(--soft-blue); background: var(--white); box-shadow: 0 0 0 3px rgba(74, 144, 226, 0.15); }
        
        .checkbox-group { display: flex; gap: 20px; background: #F8FAFC; padding: 12px 16px; border-radius: 10px; border: 1px solid var(--border-light); height: 100%; align-items: center; }
        .checkbox-group label { display: flex; align-items: center; gap: 8px; font-weight: 500; cursor: pointer; font-size: 0.9rem; }
        .checkbox-group input[type="checkbox"] { width: 16px; height: 16px; accent-color: var(--teal); }

        .form-actions { display: flex; justify-content: flex-end; gap: 12px; margin-top: 12px; border-top: 1px solid var(--border-light); padding-top: 24px; }
        
        .btn { padding: 12px 24px; border-radius: 10px; font-size: 0.95rem; font-weight: 600; cursor: pointer; transition: all 0.2s; display: inline-flex; align-items: center; gap: 8px; border: none; text-decoration: none; }
        .btn-primary { background: var(--teal); color: white; }
        .btn-primary:hover { background: var(--teal-dark); }
        .btn-secondary { background: #F1F5F9; color: var(--charcoal); border: 1px solid var(--border-light); }
        .btn-secondary:hover { background: #E2E8F0; }

        .priority-preview { background: var(--soft-blue-light); padding: 16px; border-radius: 12px; margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center; border: 1px solid rgba(74,144,226,0.2); }
        .priority-preview span { font-weight: 600; font-size: 0.9rem; }
        .priority-badge { padding: 6px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 700; color: white; text-transform: uppercase; letter-spacing: 0.5px; }
        .pr1 { background: var(--light-coral); }
        .pr2 { background: var(--warm-yellow); color: #5C3E00; }
        .pr3 { background: var(--soft-green); color: #2E5A36; }
        .hidden { display: none !important; }
    </style>
</head>
<body>

<aside class="sidebar">
    <div class="sidebar-logo">
        <h2>4ID Station Hospital</h2>
        <p>Camp Evangelista</p>
    </div>
    <ul class="nav-menu">
        <?php if ($_SESSION['role'] === 'admin'): ?>
            <li class="nav-item"><a href="../admin/patients.php" class="nav-link"><i class="fas fa-arrow-left"></i> <span>Back to Directory</span></a></li>
        <?php endif; ?>
        <li class="nav-item"><a href="registration.php" class="nav-link active"><i class="fas fa-user-plus"></i> <span>Register Patient</span></a></li>
    </ul>
</aside>

<main class="main-content">
    <div class="top-bar">
        <div class="page-title">
            <h1>Patient Intake Registration</h1>
            <p>Onboard demographic data directly to the medical tracking engine</p>
        </div>
        <div>
            <?php if ($_SESSION['role'] === 'admin'): ?>
                <a href="../admin/patients.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Return to Directory</a>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!empty($success_message)): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle" style="font-size: 1.25rem; margin-top: 2px;"></i>
            <div><?= $success_message; ?></div>
        </div>
    <?php endif; ?>

    <?php if (!empty($error_message)): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle" style="font-size: 1.25rem; margin-top: 2px;"></i>
            <div><?= $error_message; ?></div>
        </div>
    <?php endif; ?>

    <div class="form-panel">
        <div class="panel-header"><i class="fas fa-id-card"></i> Clinical Profile Demographics</div>
        <div class="panel-body">
            <form method="POST" action="">
                <div class="form-row">
                    <div class="form-group">
                        <label>First Name *</label>
                        <input type="text" name="first_name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Last Name *</label>
                        <input type="text" name="last_name" class="form-control" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Date of Birth *</label>
                        <input type="date" name="dob" id="dob" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Gender *</label>
                        <select name="gender" class="form-control" required>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Contact Number</label>
                        <input type="tel" name="contact" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>Residential Address</label>
                        <input type="text" name="address" class="form-control">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Patient Scope Type *</label>
                        <select name="patient_type" class="form-control" required id="patientType">
                            <option value="">-- Choose Designation --</option>
                            <option value="Dependent">Dependent</option>
                            <option value="Military Personnel">Military Personnel</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Triage Priority Qualifiers</label>
                        <div class="checkbox-group">
                            <label><input type="checkbox" name="is_pwd" id="isPwd" value="1"> PWD</label>
                            <label><input type="checkbox" name="is_senior" id="isSenior" value="1"> Senior</label>
                            <label><input type="checkbox" name="is_pregnant" id="isPregnant" value="1"> Pregnant</label>
                        </div>
                    </div>
                </div>

                <div id="priorityPreview" class="priority-preview hidden">
                    <span style="color: var(--dark-gray);">Evaluated Priority Classification Level:</span>
                    <div>
                        <span id="priorityBadge" class="priority-badge pr3">PR3</span> 
                        <span id="priorityDescription" style="margin-left: 8px; color: var(--charcoal); font-size: 0.85rem;">Regular Status</span>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Clinic Assignment Target Area</label>
                        <select name="clinic_id" class="form-control">
                            <option value="">-- Save Record to Profile Registry Only --</option>
                            <?php foreach ($clinics as $clinic): ?>
                                <option value="<?= $clinic['id']; ?>"><?= htmlspecialchars($clinic['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Appointment Queue Time Target</label>
                        <input type="datetime-local" name="appointment_time" class="form-control">
                    </div>
                </div>

                <div class="form-actions">
                    <button type="reset" class="btn btn-secondary">Reset Fields</button>
                    <button type="submit" name="register_patient" class="btn btn-primary"><i class="fas fa-save"></i> Save System Profile Record</button>
                </div>
            </form>
        </div>
    </div>
</main>

<script>
    function updatePriorityPreview() {
        const type = document.getElementById('patientType').value;
        const isPwd = document.getElementById('isPwd').checked;
        const isSenior = document.getElementById('isSenior').checked;
        const isPregnant = document.getElementById('isPregnant').checked;
        
        let priority = 'PR3'; 
        let desc = 'Regular Queue Processing'; 
        let badgeClass = 'pr3';
        
        if (type === 'Military Personnel') { 
            priority = 'PR1'; 
            desc = 'Active Service Personnel Priority'; 
            badgeClass = 'pr1'; 
        } else if (isPwd || isSenior || isPregnant) { 
            priority = 'PR2'; 
            desc = 'Special Privilege Priority Allocation'; 
            badgeClass = 'pr2'; 
        }
        
        const preview = document.getElementById('priorityPreview');
        if (type || isPwd || isSenior || isPregnant) {
            preview.classList.remove('hidden');
            const badge = document.getElementById('priorityBadge');
            badge.textContent = priority;
            badge.className = 'priority-badge' + badgeClass;
            document.getElementById('priorityDescription').textContent = desc;
        } else { 
            preview.classList.add('hidden'); 
        }
    }
    
    document.getElementById('patientType').addEventListener('change', updatePriorityPreview);
    document.getElementById('isPwd').addEventListener('change', updatePriorityPreview);
    document.getElementById('isSenior').addEventListener('change', updatePriorityPreview);
    document.getElementById('isPregnant').addEventListener('change', updatePriorityPreview);
    
    document.getElementById('dob')?.addEventListener('change', function() {
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
</script>
</body>
</html>