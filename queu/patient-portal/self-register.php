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
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Self Registration | Camp Evangelista Hospital</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
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
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, var(--pale-blue) 0%, var(--light-gray) 100%);
            color: var(--charcoal);
            line-height: 1.5;
            min-height: 100vh;
        }

        /* Kiosk Mode Header */
        .kiosk-header {
            background: var(--teal);
            color: white;
            text-align: center;
            padding: 10px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .kiosk-header i { margin-right: 8px; }

        /* Header */
        .header {
            background: var(--white);
            border-bottom: 1px solid var(--border-light);
            padding: 20px 0;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: var(--shadow-sm);
        }

        .header-container {
            max-width: 900px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
        }

        .logo h1 {
            color: var(--soft-blue);
            font-size: 1.2rem;
            font-weight: 700;
        }

        .logo p {
            color: var(--charcoal);
            font-size: 0.65rem;
            opacity: 0.7;
        }

        .header-badge {
            background: var(--soft-blue-light);
            padding: 6px 14px;
            border-radius: 40px;
            font-size: 0.75rem;
            font-weight: 500;
            color: var(--soft-blue);
        }

        /* Main Container */
        .main-container {
            max-width: 900px;
            margin: 0 auto;
            padding: 40px 20px;
        }

        /* Steps - Only 2 steps now */
        .steps {
            display: flex;
            justify-content: center;
            gap: 40px;
            margin-bottom: 32px;
            max-width: 300px;
            margin-left: auto;
            margin-right: auto;
        }

        .step { text-align: center; }
        .step-circle {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: var(--white);
            border: 2px solid var(--border-light);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 8px;
            font-weight: 700;
            font-size: 1.1rem;
        }
        .step.active .step-circle {
            background: var(--soft-blue);
            color: white;
            border-color: var(--soft-blue);
        }
        .step.completed .step-circle {
            background: var(--teal);
            color: white;
            border-color: var(--teal);
        }
        .step-label { font-size: 0.75rem; color: var(--charcoal); }

        /* Form Card */
        .form-card {
            background: var(--white);
            border-radius: 20px;
            border: 1px solid var(--border-light);
            overflow: hidden;
            box-shadow: var(--shadow-md);
        }

        .card-header {
            padding: 20px 24px;
            border-bottom: 1px solid var(--border-light);
            background: var(--white);
        }
        .card-header h2 { font-size: 1.2rem; font-weight: 600; color: var(--dark-gray); }

        .card-body { padding: 24px; }

        /* Form */
        .form-section { margin-bottom: 24px; }
        .form-section-title {
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--dark-gray);
            margin-bottom: 16px;
            padding-bottom: 8px;
            border-bottom: 2px solid var(--soft-blue-light);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .form-section-title i { color: var(--soft-blue); }

        .form-row {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 16px;
            margin-bottom: 16px;
        }

        .form-group { display: flex; flex-direction: column; gap: 6px; }
        .form-group.full-width { grid-column: span 2; }

        .form-label {
            font-size: 0.7rem;
            font-weight: 600;
            color: var(--charcoal);
            text-transform: uppercase;
        }
        .form-label.required:after { content: " *"; color: var(--light-coral); }

        .form-control {
            padding: 12px 14px;
            border: 1px solid var(--border-light);
            border-radius: 12px;
            font-family: inherit;
            font-size: 0.9rem;
            transition: all 0.2s;
            background: var(--white);
        }
        .form-control:focus {
            outline: none;
            border-color: var(--soft-blue);
            box-shadow: 0 0 0 3px rgba(74, 144, 226, 0.1);
        }

        /* Checkbox Group */
        .checkbox-group {
            background: var(--light-gray);
            border-radius: 12px;
            padding: 12px;
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }
        .checkbox-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .checkbox-item input {
            width: 18px;
            height: 18px;
            accent-color: var(--teal);
        }
        .checkbox-item label { font-size: 0.8rem; cursor: pointer; }

        /* Military Note */
        .military-note {
            background: var(--soft-blue-light);
            border-left: 3px solid var(--soft-blue);
            padding: 12px;
            border-radius: 10px;
            margin: 16px 0;
            font-size: 0.75rem;
            display: none;
        }

        /* Priority Preview */
        .priority-preview {
            background: var(--pale-blue);
            border-radius: 12px;
            padding: 12px 16px;
            margin: 16px 0;
            display: none;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
        }
        .priority-preview.show { display: flex; }
        .priority-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 30px;
            font-size: 0.7rem;
            font-weight: 700;
        }
        .priority-PR1 { background: var(--light-coral); color: white; }
        .priority-PR2 { background: var(--warm-yellow); color: var(--dark-gray); }
        .priority-PR3 { background: var(--soft-green); color: var(--dark-gray); }

        /* Terms */
        .terms-checkbox {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 20px 0;
            padding: 12px;
            background: var(--light-gray);
            border-radius: 12px;
        }
        .terms-checkbox input { width: 18px; height: 18px; accent-color: var(--teal); }
        .terms-checkbox label { font-size: 0.8rem; cursor: pointer; }

        /* Button */
        .btn-register {
            background: var(--teal);
            color: white;
            border: none;
            padding: 14px 24px;
            border-radius: 40px;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
            font-size: 1rem;
            transition: all 0.2s;
        }
        .btn-register:hover {
            background: var(--teal-dark);
            transform: translateY(-1px);
        }
        .btn-military { background: var(--light-coral); }
        .btn-military:hover { background: #e55a4e; }

        /* Alert */
        .alert {
            padding: 12px 16px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.85rem;
        }
        .alert-danger {
            background: #FEF2F0;
            color: var(--light-coral);
            border-left: 3px solid var(--light-coral);
        }

        /* Footer */
        .footer {
            background: var(--white);
            border-top: 1px solid var(--border-light);
            padding: 20px 0;
            margin-top: 40px;
        }
        .footer-container {
            max-width: 900px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            font-size: 0.7rem;
        }
        .footer-links { display: flex; gap: 20px; }
        .footer-links a { color: var(--charcoal); text-decoration: none; }
        .footer-links a:hover { color: var(--soft-blue); }

        /* Responsive */
        @media (max-width: 640px) {
            .form-row { grid-template-columns: 1fr; }
            .form-group.full-width { grid-column: span 1; }
            .steps { gap: 20px; }
            .step-circle { width: 36px; height: 36px; font-size: 0.9rem; }
            .step-label { font-size: 0.65rem; }
            .card-header { padding: 16px 20px; }
            .card-body { padding: 20px; }
        }
    </style>
</head>
<body>

<!-- Kiosk Header -->
<div class="kiosk-header">
    <i class="fas fa-desktop"></i> SELF-REGISTRATION KIOSK
</div>

<!-- Header -->
<header class="header">
    <div class="header-container">
        <div class="logo">
            <h1>4ID Station Hospital</h1>
            <p>Camp Evangelista • Patient Portal</p>
        </div>
        <div class="header-badge">
            <i class="fas fa-user-plus"></i> Self Registration
        </div>
    </div>
</header>

<!-- Main Content -->
<main class="main-container">
    <!-- Steps - Only 2 steps (Register → Get Ticket) -->
    <div class="steps">
        <div class="step active">
            <div class="step-circle">1</div>
            <div class="step-label">Register</div>
        </div>
        <div class="step">
            <div class="step-circle">2</div>
            <div class="step-label">Get Ticket</div>
        </div>
    </div>

    <?php if ($error_message): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
        </div>
    <?php endif; ?>
    
    <!-- Registration Form -->
    <div class="form-card">
        <div class="card-header">
            <h2><i class="fas fa-notes-medical"></i> Patient Information</h2>
        </div>
        <div class="card-body">
            <form method="POST" action="" id="registrationForm">
                <!-- Personal Information -->
                <div class="form-section">
                    <div class="form-section-title">
                        <i class="fas fa-user-circle"></i> Personal Information
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label required">First Name</label>
                            <input type="text" name="first_name" class="form-control" required autocomplete="off">
                        </div>
                        <div class="form-group">
                            <label class="form-label required">Last Name</label>
                            <input type="text" name="last_name" class="form-control" required autocomplete="off">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label required">Date of Birth</label>
                            <input type="date" name="dob" class="form-control" required max="<?php echo date('Y-m-d', strtotime('-1 day')); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label required">Gender</label>
                            <select name="gender" class="form-control" required>
                                <option value="">Select</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Contact Number</label>
                            <input type="tel" name="contact" class="form-control" placeholder="+63 XXX XXX XXXX" autocomplete="off">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Address</label>
                            <input type="text" name="address" class="form-control" placeholder="Complete address" autocomplete="off">
                        </div>
                    </div>
                </div>
                
                <!-- Patient Classification -->
                <div class="form-section">
                    <div class="form-section-title">
                        <i class="fas fa-tags"></i> Patient Classification
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label required">I am a:</label>
                            <select name="patient_type" class="form-control" required id="patientType">
                                <option value="">Select Type</option>
                                <option value="dependent">Dependent</option>
                                <option value="military">Military Personnel</option>
                            </select>
                        </div>
                        <div class="form-group" id="clinicSelection">
                            <label class="form-label">Select Clinic (for Dependents)</label>
                            <select name="clinic_id" class="form-control">
                                <option value="">-- Register without queue --</option>
                                <?php foreach ($clinics as $clinic): ?>
                                    <option value="<?php echo $clinic['id']; ?>"><?php echo htmlspecialchars($clinic['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Special Conditions</label>
                        <div class="checkbox-group">
                            <label class="checkbox-item"><input type="checkbox" name="is_pwd" id="isPwd" value="1"> PWD</label>
                            <label class="checkbox-item"><input type="checkbox" name="is_senior" id="isSenior" value="1"> Senior Citizen</label>
                            <label class="checkbox-item"><input type="checkbox" name="is_pregnant" id="isPregnant" value="1"> Pregnant</label>
                        </div>
                    </div>
                    
                    <div class="military-note" id="militaryNote">
                        <i class="fas fa-shield-alt"></i> Military personnel will be processed through ALL clinics automatically.
                    </div>
                    
                    <div class="priority-preview" id="priorityPreview">
                        <div><i class="fas fa-info-circle"></i> <strong>Your Priority Level:</strong></div>
                        <div>
                            <span id="priorityBadge" class="priority-badge priority-PR3">PR3</span>
                            <span id="priorityDescription" style="margin-left: 8px;">Regular Patient</span>
                        </div>
                    </div>
                </div>
                
                <!-- Terms -->
                <div class="terms-checkbox">
                    <input type="checkbox" id="agreeTerms" required>
                    <label for="agreeTerms">I confirm that the information provided is true and correct.</label>
                </div>
                
                <button type="submit" name="self_register" class="btn-register" id="registerBtn">
                    <i class="fas fa-check-circle"></i> Register & Get Ticket
                </button>
            </form>
        </div>
    </div>
</main>

<!-- Footer -->
<footer class="footer">
    <div class="footer-container">
        <div class="footer-links">
            <a href="track-queue.php"><i class="fas fa-search"></i> Track Queue</a>
            <a href="../index.php"><i class="fas fa-lock"></i> Staff Login</a>
        </div>
        <div class="footer-copyright">
            <i class="fas fa-shield-alt"></i> <?php echo date('Y'); ?> 4th Infantry Division, Camp Evangelista
        </div>
    </div>
</footer>

<script>
    function updatePriorityPreview() {
        const patientType = document.getElementById('patientType').value;
        const isPwd = document.getElementById('isPwd').checked;
        const isSenior = document.getElementById('isSenior').checked;
        const isPregnant = document.getElementById('isPregnant').checked;
        const militaryNote = document.getElementById('militaryNote');
        const clinicSelection = document.getElementById('clinicSelection');
        const registerBtn = document.getElementById('registerBtn');
        
        let priority = 'PR3';
        let priorityClass = 'priority-PR3';
        let description = 'Regular Patient';
        
        if (patientType === 'military') {
            priority = 'PR1';
            priorityClass = 'priority-PR1';
            description = 'Military Personnel';
            militaryNote.style.display = 'block';
            clinicSelection.style.display = 'none';
            registerBtn.classList.add('btn-military');
        } else {
            militaryNote.style.display = 'none';
            clinicSelection.style.display = 'block';
            registerBtn.classList.remove('btn-military');
            
            if (isPwd || isSenior || isPregnant) {
                priority = 'PR2';
                priorityClass = 'priority-PR2';
                let conditions = [];
                if (isPwd) conditions.push('PWD');
                if (isSenior) conditions.push('Senior');
                if (isPregnant) conditions.push('Pregnant');
                description = 'Priority (' + conditions.join(', ') + ')';
            }
        }
        
        const preview = document.getElementById('priorityPreview');
        const badge = document.getElementById('priorityBadge');
        const desc = document.getElementById('priorityDescription');
        
        if (patientType || isPwd || isSenior || isPregnant) {
            preview.classList.add('show');
            badge.className = 'priority-badge ' + priorityClass;
            badge.textContent = priority;
            desc.textContent = description;
        } else {
            preview.classList.remove('show');
        }
    }
    
    document.getElementById('patientType')?.addEventListener('change', updatePriorityPreview);
    document.getElementById('isPwd')?.addEventListener('change', updatePriorityPreview);
    document.getElementById('isSenior')?.addEventListener('change', updatePriorityPreview);
    document.getElementById('isPregnant')?.addEventListener('change', updatePriorityPreview);
    
    document.getElementById('dob')?.addEventListener('change', function() {
        const dob = new Date(this.value);
        const today = new Date();
        let age = today.getFullYear() - dob.getFullYear();
        const monthDiff = today.getMonth() - dob.getMonth();
        if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < dob.getDate())) age--;
        if (age >= 60 && !document.getElementById('isSenior').checked) {
            document.getElementById('isSenior').checked = true;
            updatePriorityPreview();
        }
    });
    
    updatePriorityPreview();
</script>
</body>
</html>