<?php
// staff/submit-result.php - Submit Clinic Results
// Camp Evangelista Station Hospital

require_once dirname(__DIR__) . '/config/database.php';

session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit();
}

$allowed_staff_roles = ['admin', 'doctor', 'nurse', 'technician', 'staff'];
if (!in_array($_SESSION['role'], $allowed_staff_roles)) {
    header('Location: ../unauthorized.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

require_once dirname(__DIR__) . '/includes/SessionManager.php';
$sessionManager = new SessionManager($db);
if (!$sessionManager->checkTimeout()) {
    exit();
}

$clinic_id = isset($_GET['clinic_id']) ? (int)$_GET['clinic_id'] : ($_SESSION['clinic_id'] ?? 0);
$queue_entry_id = isset($_GET['queue_id']) ? (int)$_GET['queue_id'] : 0;
$message = '';
$error = '';

// Get clinic info
$clinic_query = "SELECT * FROM clinics WHERE id = :id";
$clinic_stmt = $db->prepare($clinic_query);
$clinic_stmt->bindParam(':id', $clinic_id);
$clinic_stmt->execute();
$clinic = $clinic_stmt->fetch(PDO::FETCH_ASSOC);

// Get patient and queue info
$queue_query = "SELECT q.*, p.first_name, p.last_name, p.mrn, p.date_of_birth
                FROM queue_entries q
                JOIN patients p ON q.patient_id = p.id
                WHERE q.id = :queue_id AND q.clinic_id = :clinic_id";
$queue_stmt = $db->prepare($queue_query);
$queue_stmt->bindParam(':queue_id', $queue_entry_id);
$queue_stmt->bindParam(':clinic_id', $clinic_id);
$queue_stmt->execute();
$queue_entry = $queue_stmt->fetch(PDO::FETCH_ASSOC);

if (!$queue_entry) {
    die("Invalid queue entry");
}

// Get result templates for this clinic
$template_query = "SELECT * FROM result_templates WHERE clinic_id = :clinic_id ORDER BY sort_order";
$template_stmt = $db->prepare($template_query);
$template_stmt->bindParam(':clinic_id', $clinic_id);
$template_stmt->execute();
$templates = $template_stmt->fetchAll(PDO::FETCH_ASSOC);

// Check if result already exists
$existing_query = "SELECT * FROM clinic_results 
                   WHERE queue_entry_id = :queue_id AND clinic_id = :clinic_id";
$existing_stmt = $db->prepare($existing_query);
$existing_stmt->bindParam(':queue_id', $queue_entry_id);
$existing_stmt->bindParam(':clinic_id', $clinic_id);
$existing_stmt->execute();
$existing_result = $existing_stmt->fetch(PDO::FETCH_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_result'])) {
    $result_data = [];
    $findings = $_POST['findings'] ?? '';
    $recommendations = $_POST['recommendations'] ?? '';
    
    // Collect all field values
    foreach ($templates as $template) {
        $field_name = $template['field_name'];
        $result_data[$field_name] = $_POST[$field_name] ?? '';
    }
    
    $result_data_json = json_encode($result_data);
    $submitted_by = $_SESSION['user_id'];
    
    if ($existing_result) {
        // Update existing result
        $update_query = "UPDATE clinic_results 
                         SET result_data = :result_data, 
                             findings = :findings, 
                             recommendations = :recommendations,
                             status = 'completed',
                             submitted_by = :submitted_by,
                             submitted_at = NOW()
                         WHERE id = :id";
        $update_stmt = $db->prepare($update_query);
        $update_stmt->bindParam(':result_data', $result_data_json);
        $update_stmt->bindParam(':findings', $findings);
        $update_stmt->bindParam(':recommendations', $recommendations);
        $update_stmt->bindParam(':submitted_by', $submitted_by);
        $update_stmt->bindParam(':id', $existing_result['id']);
        
        if ($update_stmt->execute()) {
            $message = "Result updated successfully!";
        } else {
            $error = "Failed to update result.";
        }
    } else {
        // Insert new result
        $insert_query = "INSERT INTO clinic_results 
                         (queue_entry_id, patient_id, clinic_id, result_data, 
                          findings, recommendations, status, submitted_by) 
                         VALUES 
                         (:queue_id, :patient_id, :clinic_id, :result_data, 
                          :findings, :recommendations, 'completed', :submitted_by)";
        $insert_stmt = $db->prepare($insert_query);
        $insert_stmt->bindParam(':queue_id', $queue_entry_id);
        $insert_stmt->bindParam(':patient_id', $queue_entry['patient_id']);
        $insert_stmt->bindParam(':clinic_id', $clinic_id);
        $insert_stmt->bindParam(':result_data', $result_data_json);
        $insert_stmt->bindParam(':findings', $findings);
        $insert_stmt->bindParam(':recommendations', $recommendations);
        $insert_stmt->bindParam(':submitted_by', $submitted_by);
        
        if ($insert_stmt->execute()) {
            $message = "Result submitted successfully!";
        } else {
            $error = "Failed to submit result.";
        }
    }
    
    // Refresh to show updated data
    $existing_stmt->execute();
    $existing_result = $existing_stmt->fetch(PDO::FETCH_ASSOC);
}

// Load existing result data for display
$existing_data = [];
if ($existing_result) {
    $existing_data = json_decode($existing_result['result_data'], true) ?: [];
    $existing_findings = $existing_result['findings'] ?? '';
    $existing_recommendations = $existing_result['recommendations'] ?? '';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submit Result - <?php echo htmlspecialchars($clinic['name']); ?> | Camp Evangelista</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    
    <style>
        :root {
            --soft-blue: #4A90E2;
            --soft-blue-dark: #3A7BC8;
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
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: var(--light-gray); color: var(--charcoal); line-height: 1.5; }

        /* Sidebar */
        .sidebar {
            position: fixed; top: 0; left: 0; width: 280px; height: 100vh;
            background: var(--white); box-shadow: var(--shadow-md); overflow-y: auto;
            border-right: 1px solid var(--border-light);
        }
        .sidebar-logo { padding: 28px 24px; border-bottom: 1px solid var(--border-light); margin-bottom: 24px; }
        .sidebar-logo h2 { color: var(--soft-blue); font-size: 1.1rem; font-weight: 700; }
        .sidebar-logo p { color: var(--charcoal); font-size: 0.7rem; opacity: 0.7; }
        .nav-menu { list-style: none; padding: 0 16px; }
        .nav-item { margin-bottom: 4px; }
        .nav-link { display: flex; align-items: center; gap: 12px; padding: 12px 16px; border-radius: 12px; color: var(--charcoal); text-decoration: none; font-weight: 500; transition: all 0.2s; }
        .nav-link i { width: 22px; color: var(--soft-blue); }
        .nav-link:hover { background: var(--light-gray); color: var(--soft-blue); }
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
        .user-avatar { width: 44px; height: 44px; background: var(--light-gray); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: var(--soft-blue); font-weight: 600; }

        /* Form Card */
        .form-card { background: var(--white); border-radius: 24px; border: 1px solid var(--border-light); overflow: hidden; box-shadow: var(--shadow-md); margin-bottom: 24px; }
        .card-header { padding: 20px 28px; border-bottom: 1px solid var(--border-light); background: var(--white); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; }
        .card-header h2 { color: var(--dark-gray); font-size: 1.2rem; font-weight: 600; display: flex; align-items: center; gap: 8px; }
        .card-header h2 i { color: var(--soft-blue); }
        .card-body { padding: 28px; }

        /* Patient Info Bar */
        .patient-info-bar { background: var(--light-gray); border-radius: 16px; padding: 16px 20px; margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; }
        .patient-details h3 { color: var(--dark-gray); font-size: 1.1rem; margin-bottom: 4px; }
        .patient-details p { color: var(--charcoal); font-size: 0.8rem; }
        .queue-badge { background: var(--soft-blue); color: white; padding: 6px 16px; border-radius: 30px; font-weight: 600; font-family: monospace; }

        /* Form Grid */
        .form-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; }
        .form-group { display: flex; flex-direction: column; gap: 6px; }
        .form-group.full-width { grid-column: span 2; }
        .form-group label { font-size: 0.75rem; font-weight: 600; color: var(--charcoal); text-transform: uppercase; letter-spacing: 0.5px; }
        .form-group label i { color: var(--soft-blue); margin-right: 4px; }
        .form-group label.required::after { content: " *"; color: var(--light-coral); }
        .form-control { padding: 12px 16px; border: 1px solid var(--border-light); border-radius: 12px; font-family: inherit; font-size: 0.9rem; transition: all 0.2s; background: var(--white); width: 100%; }
        .form-control:focus { outline: none; border-color: var(--soft-blue); box-shadow: 0 0 0 3px rgba(74, 144, 226, 0.1); }
        textarea.form-control { resize: vertical; min-height: 80px; }

        /* Buttons */
        .btn-primary { background: var(--teal); color: white; border: none; padding: 12px 28px; border-radius: 12px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; transition: all 0.2s; }
        .btn-primary:hover { background: var(--teal-dark); transform: translateY(-1px); }
        .btn-secondary { background: var(--light-gray); color: var(--charcoal); border: 1px solid var(--border-light); padding: 12px 28px; border-radius: 12px; font-weight: 500; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; text-decoration: none; transition: all 0.2s; }
        .btn-secondary:hover { background: var(--border-light); }
        .form-actions { display: flex; justify-content: flex-end; gap: 16px; margin-top: 24px; padding-top: 24px; border-top: 1px solid var(--border-light); }

        /* Alerts */
        .alert { padding: 14px 20px; border-radius: 16px; margin-bottom: 24px; display: flex; align-items: center; gap: 12px; }
        .alert-success { background: var(--soft-green); color: var(--dark-gray); border-left: 3px solid var(--teal); }
        .alert-danger { background: #FEF2F0; color: var(--light-coral); border-left: 3px solid var(--light-coral); }

        /* Back Link */
        .back-link { display: inline-flex; align-items: center; gap: 8px; color: var(--soft-blue); text-decoration: none; margin-top: 16px; }

        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); }
            .main-content { margin-left: 0; padding: 20px; }
            .form-grid { grid-template-columns: 1fr; }
            .form-group.full-width { grid-column: span 1; }
            .top-bar { flex-direction: column; align-items: flex-start; gap: 16px; }
        }
    </style>
</head>
<body>

<aside class="sidebar">
    <div class="sidebar-logo"><h2>4ID Station Hospital</h2><p>Camp Evangelista</p></div>
    <ul class="nav-menu">
        <li class="nav-item"><a href="clinic-dashboard.php?clinic_id=<?php echo $clinic_id; ?>" class="nav-link"><i class="fas fa-tachometer-alt"></i><span>Dashboard</span></a></li>
        <li class="nav-item"><a href="registration.php" class="nav-link"><i class="fas fa-user-plus"></i><span>Register Patient</span></a></li>
        <li class="nav-item"><a href="patient-queue.php" class="nav-link"><i class="fas fa-list"></i><span>All Clinics</span></a></li>
        <li class="nav-item"><a href="submit-result.php?clinic_id=<?php echo $clinic_id; ?>&queue_id=<?php echo $queue_entry_id; ?>" class="nav-link active"><i class="fas fa-file-alt"></i><span>Submit Result</span></a></li>
        <li class="nav-item"><a href="profile.php" class="nav-link"><i class="fas fa-user-circle"></i><span>My Profile</span></a></li>
        <li class="nav-item"><a href="../logout.php" class="nav-link" style="color: var(--light-coral);"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a></li>
    </ul>
</aside>

<main class="main-content">
    <div class="top-bar">
        <div class="page-title">
            <h1><i class="fas fa-file-alt"></i> Submit Result</h1>
            <p><?php echo htmlspecialchars($clinic['name']); ?> - Patient Examination Results</p>
        </div>
        <div class="user-info">
            <div class="date-time"><div class="date" id="currentDate"></div><div class="time" id="currentTime"></div></div>
            <div class="user-avatar"><i class="fas fa-user-md"></i></div>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $message; ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
    <?php endif; ?>

    <div class="form-card">
        <div class="card-header">
            <h2><i class="fas fa-notes-medical"></i> Clinical Results</h2>
            <div class="queue-badge">Queue #: <?php echo htmlspecialchars($queue_entry['queue_number']); ?></div>
        </div>
        <div class="card-body">
            <div class="patient-info-bar">
                <div class="patient-details">
                    <h3><?php echo htmlspecialchars($queue_entry['first_name'] . ' ' . $queue_entry['last_name']); ?></h3>
                    <p><i class="fas fa-id-card"></i> MRN: <?php echo htmlspecialchars($queue_entry['mrn']); ?> | 
                       <i class="fas fa-calendar-alt"></i> DOB: <?php echo date('M d, Y', strtotime($queue_entry['date_of_birth'])); ?></p>
                </div>
                <div class="queue-badge" style="background: var(--teal);">
                    <i class="fas fa-calendar-check"></i> <?php echo date('F d, Y'); ?>
                </div>
            </div>

            <form method="POST">
                <div class="form-grid">
                    <?php foreach ($templates as $template): ?>
                        <div class="form-group <?php echo $template['field_type'] == 'textarea' ? 'full-width' : ''; ?>">
                            <label class="<?php echo $template['is_required'] ? 'required' : ''; ?>">
                                <i class="fas fa-stethoscope"></i> <?php echo htmlspecialchars($template['field_label']); ?>
                            </label>
                            <?php 
                            $field_value = $existing_data[$template['field_name']] ?? '';
                            $field_name = $template['field_name'];
                            $field_type = $template['field_type'];
                            ?>
                            <?php if ($field_type == 'textarea'): ?>
                                <textarea name="<?php echo $field_name; ?>" class="form-control" rows="3" <?php echo $template['is_required'] ? 'required' : ''; ?>><?php echo htmlspecialchars($field_value); ?></textarea>
                            <?php elseif ($field_type == 'number'): ?>
                                <input type="number" name="<?php echo $field_name; ?>" class="form-control" value="<?php echo htmlspecialchars($field_value); ?>" step="any" <?php echo $template['is_required'] ? 'required' : ''; ?>>
                            <?php else: ?>
                                <input type="text" name="<?php echo $field_name; ?>" class="form-control" value="<?php echo htmlspecialchars($field_value); ?>" <?php echo $template['is_required'] ? 'required' : ''; ?>>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                    
                    <div class="form-group full-width">
                        <label><i class="fas fa-stethoscope"></i> General Findings / Notes</label>
                        <textarea name="findings" class="form-control" rows="4" placeholder="Enter any additional findings or clinical notes..."><?php echo htmlspecialchars($existing_result['findings'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="form-group full-width">
                        <label><i class="fas fa-clinic-medical"></i> Recommendations / Follow-up</label>
                        <textarea name="recommendations" class="form-control" rows="3" placeholder="Enter recommendations for patient..."><?php echo htmlspecialchars($existing_result['recommendations'] ?? ''); ?></textarea>
                    </div>
                </div>
                
                <div class="form-actions">
                    <a href="clinic-dashboard.php?clinic_id=<?php echo $clinic_id; ?>" class="btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                    <button type="submit" name="submit_result" class="btn-primary">
                        <i class="fas fa-save"></i> Submit Result
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <a href="clinic-dashboard.php?clinic_id=<?php echo $clinic_id; ?>" class="back-link">
        <i class="fas fa-arrow-left"></i> Back to Dashboard
    </a>
</main>

<script>
    function updateDateTime() {
        const now = new Date();
        document.getElementById('currentDate').textContent = now.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
        document.getElementById('currentTime').textContent = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
    }
    updateDateTime();
    setInterval(updateDateTime, 1000);
    
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