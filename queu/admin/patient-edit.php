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
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Patient | <?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?> | Camp Evangelista</title>
    
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
            font-family: 'Inter', sans-serif;
            background-color: var(--light-gray);
            color: var(--charcoal);
            line-height: 1.5;
        }

        /* Sidebar Navigation */
        .sidebar {
            position: fixed; top: 0; left: 0; width: 280px; height: 100vh;
            background: var(--white); box-shadow: var(--shadow-md); z-index: 1000;
            overflow-y: auto; border-right: 1px solid var(--border-light);
        }
        .sidebar-logo { padding: 28px 24px; border-bottom: 1px solid var(--border-light); margin-bottom: 24px; }
        .sidebar-logo h2 { color: var(--soft-blue); font-size: 1.1rem; font-weight: 700; }
        .sidebar-logo p { color: var(--charcoal); font-size: 0.7rem; opacity: 0.7; }
        .nav-menu { list-style: none; padding: 0 16px; }
        .nav-item { margin-bottom: 4px; }
        .nav-link {
            display: flex; align-items: center; gap: 12px; padding: 12px 16px;
            border-radius: 12px; color: var(--charcoal); text-decoration: none;
            font-weight: 500; transition: all 0.2s ease;
        }
        .nav-link i { width: 22px; color: var(--soft-blue); }
        .nav-link:hover { background: var(--soft-blue-light); color: var(--soft-blue); }
        .nav-link.active { background: var(--soft-blue); color: white; }
        .nav-link.active i { color: white; }

        /* Main Content */
        .main-content { margin-left: 280px; padding: 28px 36px; min-height: 100vh; }
        .top-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 32px; padding-bottom: 20px; border-bottom: 1px solid var(--border-light); }
        .page-title h1 { color: var(--dark-gray); font-size: 1.75rem; font-weight: 600; margin-bottom: 4px; }
        .page-title p { color: var(--charcoal); font-size: 0.85rem; opacity: 0.7; }
        .user-info { display: flex; align-items: center; gap: 20px; }
        .date-time { text-align: right; font-size: 0.85rem; }
        .date { color: var(--charcoal); font-weight: 500; }
        .time { color: var(--soft-blue); font-weight: 600; }
        .user-avatar { width: 44px; height: 44px; background: var(--soft-blue-light); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: var(--soft-blue); font-weight: 600; }

        /* Patient Header */
        .patient-header {
            background: var(--white);
            border-radius: 20px;
            padding: 20px 24px;
            margin-bottom: 24px;
            border: 1px solid var(--border-light);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
        }
        .patient-info h2 { color: var(--dark-gray); font-size: 1.3rem; margin-bottom: 4px; }
        .patient-info p { color: var(--charcoal); font-size: 0.85rem; }
        .patient-badge {
            background: var(--soft-blue-light);
            color: var(--soft-blue);
            padding: 8px 16px;
            border-radius: 30px;
            font-weight: 600;
        }

        /* Form Card */
        .form-card {
            background: var(--white);
            border-radius: 20px;
            border: 1px solid var(--border-light);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
            margin-bottom: 24px;
        }
        .card-header {
            padding: 18px 24px;
            border-bottom: 1px solid var(--border-light);
            background: var(--white);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
        }
        .card-header h3 {
            color: var(--dark-gray);
            font-size: 1rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .card-header h3 i { color: var(--soft-blue); }
        .card-body { padding: 24px; }

        /* Form Styles */
        .form-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; }
        .form-group { display: flex; flex-direction: column; gap: 6px; }
        .form-group.full-width { grid-column: span 2; }
        .form-label { font-size: 0.7rem; font-weight: 600; color: var(--charcoal); text-transform: uppercase; letter-spacing: 0.5px; }
        .form-label i { color: var(--soft-blue); margin-right: 4px; }
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
        .form-control:focus { outline: none; border-color: var(--soft-blue); box-shadow: 0 0 0 3px rgba(74, 144, 226, 0.1); }
        select.form-control { cursor: pointer; }
        textarea.form-control { resize: vertical; min-height: 80px; }

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
        .checkbox-item input { width: 18px; height: 18px; accent-color: var(--teal); }
        .checkbox-item label { font-size: 0.85rem; cursor: pointer; }

        /* Status Badge */
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        .status-active { background: var(--soft-green); color: var(--dark-gray); }
        .status-archived { background: var(--light-gray); color: var(--charcoal); }

        /* Buttons */
        .btn-primary {
            background: var(--teal); color: white; border: none;
            padding: 12px 24px; border-radius: 12px; font-weight: 600;
            cursor: pointer; display: inline-flex; align-items: center; gap: 8px;
            transition: all 0.2s;
        }
        .btn-primary:hover { background: var(--teal-dark); transform: translateY(-1px); }
        .btn-secondary {
            background: var(--light-gray); color: var(--charcoal);
            border: 1px solid var(--border-light); padding: 12px 24px;
            border-radius: 12px; font-weight: 500; cursor: pointer;
            display: inline-flex; align-items: center; gap: 8px;
            text-decoration: none; transition: all 0.2s;
        }
        .btn-secondary:hover { background: var(--border-light); }
        .form-actions { display: flex; justify-content: flex-end; gap: 12px; margin-top: 24px; padding-top: 24px; border-top: 1px solid var(--border-light); }

        /* Table */
        .table-container { overflow-x: auto; }
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th {
            text-align: left; padding: 12px; background: var(--light-gray);
            font-weight: 600; color: var(--dark-gray); font-size: 0.7rem;
            text-transform: uppercase; border-bottom: 1px solid var(--border-light);
        }
        .data-table td { padding: 12px; border-bottom: 1px solid var(--border-light); color: var(--charcoal); font-size: 0.85rem; }
        .data-table tr:hover td { background: var(--soft-blue-light); }

        /* Alert */
        .alert {
            padding: 14px 20px; border-radius: 16px; margin-bottom: 24px;
            display: flex; align-items: center; gap: 12px;
        }
        .alert-success { background: var(--soft-green); color: var(--dark-gray); border-left: 3px solid var(--teal); }
        .alert-danger { background: #FEF2F0; color: var(--light-coral); border-left: 3px solid var(--light-coral); }

        /* Action Links */
        .action-links {
            display: flex;
            gap: 16px;
            margin-top: 16px;
            margin-bottom: 24px;
            flex-wrap: wrap;
        }
        .action-link {
            background: var(--white);
            color: var(--soft-blue);
            text-decoration: none;
            font-size: 0.85rem;
            padding: 8px 16px;
            border-radius: 20px;
            border: 1px solid var(--border-light);
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s;
        }
        .action-link:hover {
            background: var(--soft-blue-light);
            border-color: var(--soft-blue);
        }

        /* Responsive */
        @media (max-width: 1024px) { .form-grid { grid-template-columns: 1fr; } .form-group.full-width { grid-column: span 1; } }
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); transition: transform 0.3s; }
            .main-content { margin-left: 0; padding: 20px; }
            .top-bar { flex-direction: column; align-items: flex-start; gap: 16px; }
            .patient-header { flex-direction: column; text-align: center; }
            .form-actions { flex-direction: column; }
            .form-actions .btn-primary, .form-actions .btn-secondary { width: 100%; justify-content: center; }
        }
    </style>
</head>
<body>

<!-- Sidebar Navigation -->
<aside class="sidebar">
    <div class="sidebar-logo">
        <h2>4ID Station Hospital</h2>
        <p>Camp Evangelista</p>
    </div>
    <ul class="nav-menu">
        <li class="nav-item"><a href="dashboard.php" class="nav-link"><i class="fas fa-tachometer-alt"></i><span>Dashboard</span></a></li>
        <li class="nav-item"><a href="patients.php" class="nav-link"><i class="fas fa-users"></i><span>Patients</span></a></li>
        <li class="nav-item"><a href="queue-monitor.php" class="nav-link"><i class="fas fa-chart-line"></i><span>Queue Monitor</span></a></li>
        <li class="nav-item"><a href="clinic-congestion.php" class="nav-link"><i class="fas fa-chart-simple"></i><span>Clinic Congestion</span></a></li>
        <li class="nav-item"><a href="reports.php" class="nav-link"><i class="fas fa-chart-bar"></i><span>Reports</span></a></li>
        <li class="nav-item"><a href="users.php" class="nav-link"><i class="fas fa-users-cog"></i><span>User Management</span></a></li>
        <li class="nav-item"><a href="login-monitor.php" class="nav-link"><i class="fas fa-history"></i><span>Login Monitor</span></a></li>
        <li class="nav-item" style="margin-top: 20px; border-top: 1px solid var(--border-light); padding-top: 16px;">
            <a href="../logout.php" class="nav-link" style="color: var(--light-coral);" onclick="return confirm('Are you sure you want to logout?')">
                <i class="fas fa-sign-out-alt"></i><span>Logout</span>
            </a>
        </li>
    </ul>
</aside>

<!-- Main Content -->
<main class="main-content">
    <div class="top-bar">
        <div class="page-title">
            <h1><i class="fas fa-user-edit"></i> Edit Patient</h1>
            <p>Update patient information and demographics</p>
        </div>
        <div class="user-info">
            <div class="date-time"><div class="date" id="currentDate"></div><div class="time" id="currentTime"></div></div>
            <div class="user-avatar"><i class="fas fa-user-shield"></i></div>
        </div>
    </div>

    <!-- Patient Header -->
    <div class="patient-header">
        <div class="patient-info">
            <h2><?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?></h2>
            <p><i class="fas fa-id-card"></i> MRN: <?php echo htmlspecialchars($patient['mrn']); ?> | 
               <i class="fas fa-calendar-alt"></i> Age: <?php echo calculateAge($patient['date_of_birth']); ?> years | 
               <i class="fas fa-clock"></i> Registered: <?php echo date('M d, Y', strtotime($patient['created_at'])); ?></p>
        </div>
        <div class="patient-badge">
            <i class="fas fa-notes-medical"></i> <?php echo count($queue_history); ?> total visits
        </div>
    </div>

    <!-- Alert Messages -->
    <?php if ($message): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $message; ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
    <?php endif; ?>

    <!-- Action Links -->
    <div class="action-links">
        <a href="medical_history.php?patient_id=<?php echo $patient_id; ?>" class="action-link">
            <i class="fas fa-notes-medical"></i> Medical History
        </a>
        <a href="patient-results.php?id=<?php echo $patient_id; ?>" class="action-link">
            <i class="fas fa-file-alt"></i> Lab Results
        </a>
        <a href="../staff/registration.php?edit=<?php echo $patient_id; ?>" class="action-link">
            <i class="fas fa-calendar-plus"></i> Schedule Appointment
        </a>
    </div>

    <!-- Edit Form -->
    <div class="form-card">
        <div class="card-header">
            <h3><i class="fas fa-user-md"></i> Patient Information</h3>
            <div>
                <span class="status-badge <?php echo $patient_status == 'Active' ? 'status-active' : 'status-archived'; ?>">
                    <i class="fas <?php echo $patient_status == 'Active' ? 'fa-check-circle' : 'fa-archive'; ?>"></i>
                    <?php echo $patient_status; ?>
                </span>
            </div>
        </div>
        <div class="card-body">
            <form method="POST">
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label required"><i class="fas fa-user"></i> First Name</label>
                        <input type="text" name="first_name" class="form-control" value="<?php echo htmlspecialchars($patient['first_name']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label required"><i class="fas fa-user"></i> Last Name</label>
                        <input type="text" name="last_name" class="form-control" value="<?php echo htmlspecialchars($patient['last_name']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label required"><i class="fas fa-calendar-alt"></i> Date of Birth</label>
                        <input type="date" name="dob" class="form-control" value="<?php echo $patient['date_of_birth']; ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label required"><i class="fas fa-venus-mars"></i> Gender</label>
                        <select name="gender" class="form-control" required>
                            <option value="Male" <?php echo $patient['gender'] == 'Male' ? 'selected' : ''; ?>>Male</option>
                            <option value="Female" <?php echo $patient['gender'] == 'Female' ? 'selected' : ''; ?>>Female</option>
                            <option value="Other" <?php echo $patient['gender'] == 'Other' ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-phone"></i> Contact Number</label>
                        <input type="tel" name="contact" class="form-control" value="<?php echo htmlspecialchars($patient['contact_number']); ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-map-marker-alt"></i> Address</label>
                        <input type="text" name="address" class="form-control" value="<?php echo htmlspecialchars($patient['address']); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label required"><i class="fas fa-tag"></i> Patient Type</label>
                        <select name="patient_type" class="form-control" required>
                            <option value="military" <?php echo $patient['patient_type'] == 'military' ? 'selected' : ''; ?>>Military Personnel</option>
                            <option value="dependent" <?php echo $patient['patient_type'] == 'dependent' ? 'selected' : ''; ?>>Dependent</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-user-md"></i> Primary Physician</label>
                        <input type="text" name="primary_physician" class="form-control" value="<?php echo htmlspecialchars($patient['primary_physician'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group full-width">
                        <label class="form-label"><i class="fas fa-heartbeat"></i> Priority Conditions</label>
                        <div class="checkbox-group">
                            <label class="checkbox-item">
                                <input type="checkbox" name="is_pwd" value="1" <?php echo $patient['is_pwd'] ? 'checked' : ''; ?>>
                                <span>PWD (Person with Disability)</span>
                            </label>
                            <label class="checkbox-item">
                                <input type="checkbox" name="is_senior" value="1" <?php echo $patient['is_senior'] ? 'checked' : ''; ?>>
                                <span>Senior Citizen (60+)</span>
                            </label>
                            <label class="checkbox-item">
                                <input type="checkbox" name="is_pregnant" value="1" <?php echo $patient['is_pregnant'] ? 'checked' : ''; ?>>
                                <span>Pregnant</span>
                            </label>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-flag-checkered"></i> Status</label>
                        <select name="status" class="form-control">
                            <option value="Active" <?php echo $patient_status == 'Active' ? 'selected' : ''; ?>>Active</option>
                            <option value="Archived" <?php echo $patient_status == 'Archived' ? 'selected' : ''; ?>>Archived</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-actions">
                    <a href="patients.php" class="btn-secondary">
                        <i class="fas fa-arrow-left"></i> Cancel
                    </a>
                    <button type="submit" name="update_patient" class="btn-primary">
                        <i class="fas fa-save"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Queue History -->
    <div class="form-card">
        <div class="card-header">
            <h3><i class="fas fa-history"></i> Queue History</h3>
            <span><i class="fas fa-clock"></i> Last 20 visits</span>
        </div>
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Queue #</th>
                        <th>Clinic</th>
                        <th>Priority</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($queue_history)): ?>
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 40px;">
                                <i class="fas fa-inbox"></i> No queue history found
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($queue_history as $queue): ?>
                            <tr>
                                <td><?php echo date('M d, Y', strtotime($queue['registered_at'])); ?></td>
                                <td><?php echo date('h:i A', strtotime($queue['registered_at'])); ?></td>
                                <td><strong><?php echo htmlspecialchars($queue['queue_number']); ?></strong></td>
                                <td><?php echo htmlspecialchars($queue['clinic_name']); ?></td>
                                <td>
                                    <span class="status-badge" style="background: <?php 
                                        echo $queue['priority_level'] == 'PR1' ? '#FF6F61' : ($queue['priority_level'] == 'PR2' ? '#FFB84D' : '#A4D1B1'); 
                                    ?>; color: <?php 
                                        echo $queue['priority_level'] == 'PR1' ? 'white' : '#333'; 
                                    ?>;">
                                        <?php echo $queue['priority_level']; ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="status-badge" style="background: <?php 
                                        echo $queue['status'] == 'completed' ? '#A4D1B1' : ($queue['status'] == 'in-progress' ? '#4A90E2' : '#FFB84D'); 
                                    ?>;">
                                        <?php echo ucfirst($queue['status']); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<script>
    function updateDateTime() {
        const now = new Date();
        const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
        document.getElementById('currentDate').textContent = now.toLocaleDateString('en-US', options);
        document.getElementById('currentTime').textContent = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
    }
    updateDateTime();
    setInterval(updateDateTime, 1000);

    // Auto-hide alerts after 5 seconds
    setTimeout(() => {
        document.querySelectorAll('.alert').forEach(alert => {
            alert.style.transition = 'opacity 0.5s';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 500);
        });
    }, 5000);
</script>
</body>
</html>