<?php
// staff/clinic-dashboard.php - Clinic Staff Dashboard
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
// DATABASE CONNECTION (Must be before SessionManager)
// ============================================
$database = new Database();
$db = $database->getConnection();
$queueManager = new QueueManager($db);

// ============================================
// SESSION TIMEOUT CHECK (Now $db exists!)
// ============================================
require_once dirname(__DIR__) . '/includes/SessionManager.php';
$sessionManager = new SessionManager($db);
if (!$sessionManager->checkTimeout()) {
    exit(); // Already redirected to login
}
$sessionManager->logActivity('Viewed clinic dashboard');

// ============================================
// CLINIC SETUP
// ============================================

// Get clinic ID from URL (default to user's clinic if not specified)
$clinic_id = isset($_GET['clinic_id']) ? (int)$_GET['clinic_id'] : ($_SESSION['clinic_id'] ?? 1);

// Check if user has access to this clinic (for non-admin users)
if ($_SESSION['role'] != 'admin') {
    if (!isset($_SESSION['clinic_id']) || $_SESSION['clinic_id'] != $clinic_id) {
        $_SESSION['error'] = "You don't have permission to access this clinic.";
        header('Location: clinic-dashboard.php?clinic_id=' . $_SESSION['clinic_id']);
        exit();
    }
}

// Get clinic info
$query = "SELECT * FROM clinics WHERE id = :id";
$stmt = $db->prepare($query);
$stmt->bindParam(':id', $clinic_id);
$stmt->execute();
$clinic = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$clinic) {
    die("Clinic not found. Please check the clinic ID.");
}

// Handle calling next patient
if (isset($_POST['call_next'])) {
    $queueManager->callNextPatient($clinic_id);
    header('Location: clinic-dashboard.php?clinic_id=' . $clinic_id);
    exit();
}

// Handle patient status update
if (isset($_POST['update_status'])) {
    $query = "UPDATE queue_entries SET status = :status";
    
    if ($_POST['status'] == 'completed') {
        $query .= ", completed_at = NOW()";
    } elseif ($_POST['status'] == 'called') {
        $query .= ", called_at = NOW()";
    }
    
    $query .= " WHERE id = :id";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':status', $_POST['status']);
    $stmt->bindParam(':id', $_POST['queue_id']);
    
    if ($stmt->execute() && $_POST['status'] == 'completed') {
        $queue_query = "SELECT q.patient_id, p.patient_type, q.clinic_id 
                        FROM queue_entries q
                        JOIN patients p ON q.patient_id = p.id
                        WHERE q.id = :queue_id";
        $queue_stmt = $db->prepare($queue_query);
        $queue_stmt->bindParam(':queue_id', $_POST['queue_id']);
        $queue_stmt->execute();
        $queue_data = $queue_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($queue_data && $queue_data['patient_type'] == 'military') {
            $next_result = $queueManager->queueForNextClinic($queue_data['patient_id'], $queue_data['clinic_id']);
            
            if ($next_result['success']) {
                $_SESSION['next_queue'] = "Patient queued for next clinic: " . $next_result['clinic'];
            } elseif (isset($next_result['all_completed']) && $next_result['all_completed']) {
                $_SESSION['next_queue'] = "Patient has completed all clinics!";
            }
        }
    }
    
    header('Location: clinic-dashboard.php?clinic_id=' . $clinic_id);
    exit();
}

// Get current queue
$query = "SELECT q.*, p.first_name, p.last_name, p.date_of_birth,
                 p.patient_type, p.is_pwd, p.is_senior, p.is_pregnant,
                 TIMESTAMPDIFF(MINUTE, q.registered_at, NOW()) as waiting_minutes,
                 HOUR(q.batch_hour) as batch_number,
                 DATE_FORMAT(q.batch_hour, '%h:%i %p') as batch_time,
                 (SELECT COUNT(*) FROM queue_entries 
                  WHERE clinic_id = q.clinic_id 
                  AND status IN ('waiting', 'called') 
                  AND batch_hour = q.batch_hour
                  AND id < q.id) + 1 as position_in_batch
          FROM queue_entries q
          JOIN patients p ON q.patient_id = p.id
          WHERE q.clinic_id = :clinic_id 
          AND q.status IN ('waiting', 'called', 'in-progress')
          AND DATE(q.registered_at) = CURDATE()
          ORDER BY 
            q.batch_hour ASC,
            FIELD(q.priority_level, 'PR1', 'PR2', 'PR3'),
            FIELD(q.status, 'called', 'in-progress', 'waiting'),
            q.registered_at ASC";

$stmt = $db->prepare($query);
$stmt->bindParam(':clinic_id', $clinic_id);
$stmt->execute();
$queue = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get today's stats
$query = "SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
            SUM(CASE WHEN status = 'waiting' THEN 1 ELSE 0 END) as waiting,
            SUM(CASE WHEN status = 'called' THEN 1 ELSE 0 END) as called,
            SUM(CASE WHEN status = 'in-progress' THEN 1 ELSE 0 END) as in_progress,
            AVG(TIMESTAMPDIFF(MINUTE, registered_at, completed_at)) as avg_time
          FROM queue_entries 
          WHERE clinic_id = :clinic_id 
          AND DATE(registered_at) = CURDATE()";

$stmt = $db->prepare($query);
$stmt->bindParam(':clinic_id', $clinic_id);
$stmt->execute();
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Get batch information
$batch_info = $queueManager->getCurrentBatch();

// Get completed patients today
$query = "SELECT q.*, p.first_name, p.last_name, p.patient_type,
                 DATE_FORMAT(q.completed_at, '%h:%i %p') as completed_time
          FROM queue_entries q
          JOIN patients p ON q.patient_id = p.id
          WHERE q.clinic_id = :clinic_id 
          AND q.status = 'completed'
          AND DATE(q.registered_at) = CURDATE()
          ORDER BY q.completed_at DESC
          LIMIT 10";

$stmt = $db->prepare($query);
$stmt->bindParam(':clinic_id', $clinic_id);
$stmt->execute();
$completed_patients = $stmt->fetchAll(PDO::FETCH_ASSOC);

$completion_rate = 0;
if ($stats['total'] > 0) {
    $completion_rate = round(($stats['completed'] / $stats['total']) * 100, 1);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($clinic['name']); ?> | Staff Dashboard | Camp Evangelista</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <meta http-equiv="refresh" content="30">
    
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

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: var(--light-gray);
            color: var(--charcoal);
            line-height: 1.5;
        }

        /* Sidebar Navigation */
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

        .nav-menu {
            list-style: none;
            padding: 0 16px;
        }

        .nav-item {
            margin-bottom: 4px;
        }

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

        .nav-link i {
            width: 22px;
            color: var(--soft-blue);
            font-size: 1.1rem;
        }

        .nav-link:hover {
            background: var(--soft-blue-light);
            color: var(--soft-blue);
        }

        .nav-link.active {
            background: var(--soft-blue);
            color: white;
        }

        .nav-link.active i {
            color: white;
        }

        /* Main Content */
        .main-content {
            margin-left: 280px;
            padding: 28px 36px;
            min-height: 100vh;
        }

        /* Top Bar */
        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 32px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--border-light);
        }

        .page-title h1 {
            color: var(--dark-gray);
            font-size: 1.75rem;
            font-weight: 600;
            letter-spacing: -0.02em;
            margin-bottom: 4px;
        }

        .page-title p {
            color: var(--charcoal);
            font-size: 0.85rem;
            opacity: 0.7;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .date-time {
            text-align: right;
            font-size: 0.85rem;
        }

        .date {
            color: var(--charcoal);
            font-weight: 500;
        }

        .time {
            color: var(--soft-blue);
            font-weight: 600;
        }

        .user-avatar {
            width: 44px;
            height: 44px;
            background: var(--soft-blue-light);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--soft-blue);
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }

        .user-avatar:hover {
            background: var(--soft-blue);
            color: white;
        }

        /* Dropdown Menu */
        .dropdown-menu {
            display: none;
            position: absolute;
            right: 0;
            top: 50px;
            background: var(--white);
            border-radius: 16px;
            box-shadow: var(--shadow-lg);
            min-width: 200px;
            z-index: 1000;
            overflow: hidden;
            border: 1px solid var(--border-light);
        }

        .dropdown-menu.show {
            display: block;
        }

        .dropdown-header {
            padding: 12px 16px;
            border-bottom: 1px solid var(--border-light);
        }

        .dropdown-header strong {
            color: var(--dark-gray);
        }

        .dropdown-header small {
            color: var(--charcoal);
            font-size: 0.7rem;
        }

        .dropdown-divider {
            height: 1px;
            background: var(--border-light);
            margin: 8px 0;
        }

        .dropdown-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 16px;
            color: var(--light-coral);
            text-decoration: none;
            transition: background 0.2s;
        }

        .dropdown-item:hover {
            background: var(--light-gray);
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 16px;
            margin-bottom: 32px;
        }

        .stat-card {
            background: var(--white);
            border-radius: 20px;
            padding: 20px 12px;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border-light);
            transition: all 0.2s ease;
            text-align: center;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            background: var(--soft-blue-light);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--soft-blue);
            font-size: 1.3rem;
            margin: 0 auto 12px;
        }

        .stat-value {
            font-size: 1.6rem;
            font-weight: 700;
            color: var(--dark-gray);
            margin-bottom: 4px;
        }

        .stat-label {
            color: var(--charcoal);
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Batch Bar */
        .batch-bar {
            background: var(--white);
            border-radius: 20px;
            padding: 20px 24px;
            margin-bottom: 32px;
            border: 1px solid var(--border-light);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }

        .batch-info {
            display: flex;
            gap: 40px;
            flex-wrap: wrap;
        }

        .batch-item {
            display: flex;
            flex-direction: column;
        }

        .batch-label {
            font-size: 0.7rem;
            color: var(--charcoal);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .batch-value {
            font-size: 1rem;
            font-weight: 600;
            color: var(--dark-gray);
        }

        .batch-value.highlight {
            color: var(--soft-blue);
            font-size: 1.2rem;
        }

        .progress-container {
            display: flex;
            align-items: center;
            gap: 16px;
            min-width: 200px;
        }

        .progress-bar-bg {
            flex: 1;
            height: 8px;
            background: var(--light-gray);
            border-radius: 4px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background: var(--teal);
            border-radius: 4px;
            transition: width 0.3s;
        }

        .progress-stats {
            font-size: 0.75rem;
            color: var(--charcoal);
        }

        /* Now Serving */
        .now-serving {
            background: linear-gradient(135deg, var(--soft-blue) 0%, var(--teal) 100%);
            border-radius: 24px;
            padding: 28px;
            margin-bottom: 32px;
            text-align: center;
            color: white;
        }

        .now-serving-label {
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 2px;
            opacity: 0.9;
        }

        .now-serving-number {
            font-size: 4rem;
            font-weight: 800;
            margin: 12px 0;
            letter-spacing: 2px;
        }

        .now-serving-name {
            font-size: 1.2rem;
            font-weight: 500;
            margin-bottom: 12px;
        }

        .now-serving-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            background: rgba(255, 255, 255, 0.2);
        }

        /* Panel */
        .panel {
            background: var(--white);
            border-radius: 20px;
            border: 1px solid var(--border-light);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
            margin-bottom: 24px;
        }

        .panel-header {
            padding: 18px 24px;
            border-bottom: 1px solid var(--border-light);
            background: var(--white);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
        }

        .panel-header h3 {
            color: var(--dark-gray);
            font-size: 1rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .panel-header h3 i {
            color: var(--soft-blue);
        }

        /* Buttons */
        .btn-primary {
            background: var(--teal);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 12px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
            font-size: 0.85rem;
        }

        .btn-primary:hover {
            background: var(--teal-dark);
            transform: translateY(-1px);
        }

        .btn-secondary {
            background: var(--light-gray);
            color: var(--charcoal);
            border: 1px solid var(--border-light);
            padding: 10px 20px;
            border-radius: 12px;
            font-weight: 500;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
            text-decoration: none;
        }

        .btn-secondary:hover {
            background: var(--border-light);
        }

        /* Queue Cards */
        .queue-list {
            padding: 20px 24px;
        }

        .queue-card {
            background: var(--white);
            border-radius: 16px;
            padding: 16px 20px;
            margin-bottom: 12px;
            border: 1px solid var(--border-light);
            border-left: 4px solid;
            transition: all 0.2s;
        }

        .queue-card:hover {
            transform: translateX(4px);
            box-shadow: var(--shadow-sm);
        }

        .queue-card.PR1 { border-left-color: var(--light-coral); }
        .queue-card.PR2 { border-left-color: var(--warm-yellow); }
        .queue-card.PR3 { border-left-color: var(--soft-green); }
        .queue-card.calling { background: var(--soft-blue-light); animation: pulse 1s infinite; }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }

        .queue-row {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
        }

        .priority-badge {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1.1rem;
        }

        .priority-PR1 { background: var(--light-coral); color: white; }
        .priority-PR2 { background: var(--warm-yellow); color: var(--dark-gray); }
        .priority-PR3 { background: var(--soft-green); color: var(--dark-gray); }

        .queue-details { flex: 1; }
        .queue-number { font-size: 1.1rem; font-weight: 700; color: var(--dark-gray); }
        .batch-tag { background: var(--light-gray); padding: 2px 8px; border-radius: 12px; font-size: 0.7rem; margin-left: 8px; }
        .patient-name { font-weight: 600; color: var(--dark-gray); margin: 4px 0; }
        .patient-info { font-size: 0.7rem; color: var(--charcoal); }

        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        .status-waiting { background: var(--warm-yellow); color: var(--dark-gray); }
        .status-called { background: var(--soft-blue-light); color: var(--soft-blue); }
        .status-progress { background: var(--soft-blue); color: white; }

        .btn-icon {
            background: none;
            border: none;
            cursor: pointer;
            padding: 8px 12px;
            border-radius: 10px;
            transition: all 0.2s;
            font-size: 0.75rem;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-weight: 500;
        }
        .btn-call { background: var(--warm-yellow); color: var(--dark-gray); }
        .btn-start { background: var(--soft-blue); color: white; }
        .btn-complete { background: var(--teal); color: white; }
.btn-result { background: var(--soft-blue); color: white; }
.btn-result:hover { background: var(--soft-blue-dark); }
        /* Info Cards */
        .info-card {
            background: var(--white);
            border-radius: 20px;
            border: 1px solid var(--border-light);
            overflow: hidden;
            margin-bottom: 24px;
        }

        .info-card .card-header {
            padding: 16px 20px;
            border-bottom: 1px solid var(--border-light);
            background: var(--light-gray);
            font-weight: 600;
            color: var(--dark-gray);
        }

        .info-card .card-body { padding: 20px; }

        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 12px;
        }
        .info-label { color: var(--charcoal); font-size: 0.8rem; }
        .info-value { font-weight: 600; color: var(--dark-gray); }

        .completed-list { list-style: none; }
        .completed-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid var(--border-light);
        }
        .completed-item:last-child { border-bottom: none; }
        .completed-number { font-weight: 700; color: var(--soft-blue); }
        .completed-name { font-size: 0.8rem; }
        .completed-time { font-size: 0.7rem; color: var(--charcoal); }

        /* Alerts */
        .alert {
            padding: 14px 20px;
            border-radius: 16px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .alert-success { background: var(--soft-green); color: var(--dark-gray); border-left: 3px solid var(--teal); }
        .alert-danger { background: #FEF2F0; color: var(--light-coral); border-left: 3px solid var(--light-coral); }

        .empty-state {
            text-align: center;
            padding: 48px;
            color: var(--charcoal);
            opacity: 0.6;
        }

        /* Responsive */
        @media (max-width: 1200px) { .stats-grid { grid-template-columns: repeat(3, 1fr); } }
        @media (max-width: 1024px) { .stats-grid { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); transition: transform 0.3s; }
            .main-content { margin-left: 0; padding: 20px; }
            .stats-grid { grid-template-columns: 1fr; }
            .batch-bar { flex-direction: column; align-items: flex-start; }
            .queue-row { flex-direction: column; align-items: flex-start; }
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
        <li class="nav-item">
            <a href="clinic-dashboard.php?clinic_id=<?php echo $clinic_id; ?>" class="nav-link active">
                <i class="fas fa-tachometer-alt"></i>
                <span>Dashboard</span>
            </a>
        </li>
        <li class="nav-item">
            <a href="registration.php" class="nav-link">
                <i class="fas fa-user-plus"></i>
                <span>Register Patient</span>
            </a>
        </li>
        <li class="nav-item">
            <a href="patient-queue.php" class="nav-link">
                <i class="fas fa-list"></i>
                <span>All Clinics</span>
            </a>
        </li>
        <li class="nav-item">
            <a href="../patient-portal/track-queue.php" target="_blank" class="nav-link">
                <i class="fas fa-search"></i>
                <span>Patient Portal</span>
            </a>
        </li>
        <li class="nav-item" style="margin-top: auto; border-top: 1px solid var(--border-light); padding-top: 16px;">
            <div style="padding: 12px 16px; background: var(--soft-blue-light); border-radius: 12px; margin-bottom: 8px;">
                <div style="font-size: 0.7rem; color: var(--charcoal);">Logged in as</div>
                <div style="font-weight: 600; color: var(--dark-gray);"><?php echo $_SESSION['full_name'] ?? $_SESSION['username']; ?></div>
                <div style="font-size: 0.7rem; color: var(--soft-blue);">
                    <i class="fas fa-tag"></i> Role: <?php echo ucfirst($_SESSION['role']); ?>
                </div>
            </div>
        </li>
        <li class="nav-item">
            <a href="profile.php" class="nav-link">
                <i class="fas fa-user-circle"></i>
                <span>My Profile</span>
            </a>
        </li>
        <li class="nav-item">
            <a href="../logout.php" class="nav-link" style="color: var(--light-coral);" onclick="return confirm('Are you sure you want to logout?')">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </li>
    </ul>
</aside>

<!-- Main Content -->
<main class="main-content">
    <!-- Top Bar -->
    <div class="top-bar">
        <div class="page-title">
            <h1><?php echo htmlspecialchars($clinic['name']); ?></h1>
            <p>Staff Dashboard • Manage patient queue and consultations</p>
        </div>
        <div class="user-info">
            <div class="date-time">
                <div class="date" id="currentDate"></div>
                <div class="time" id="currentTime"></div>
            </div>
            <div class="user-dropdown" style="position: relative;">
                <div class="user-avatar" onclick="toggleDropdown()">
                    <i class="fas fa-user-md"></i>
                </div>
                <div id="dropdownMenu" class="dropdown-menu">
                    <div class="dropdown-header">
                        <strong><?php echo $_SESSION['full_name'] ?? $_SESSION['username']; ?></strong><br>
                        <small><?php echo ucfirst($_SESSION['role']); ?></small>
                    </div>
                    <div class="dropdown-divider"></div>
                    <a href="profile.php" class="dropdown-item">
                        <i class="fas fa-user-circle"></i> My Profile
                    </a>
                    <a href="../logout.php" class="dropdown-item" onclick="return confirm('Are you sure you want to logout?')">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Alerts -->
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
    <?php endif; ?>
    <?php if (isset($_SESSION['next_queue'])): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $_SESSION['next_queue']; unset($_SESSION['next_queue']); ?></div>
    <?php endif; ?>

    <!-- Statistics -->
    <div class="stats-grid">
        <div class="stat-card"><div class="stat-icon"><i class="fas fa-users"></i></div><div class="stat-value"><?php echo $stats['total'] ?? 0; ?></div><div class="stat-label">Total Today</div></div>
        <div class="stat-card"><div class="stat-icon"><i class="fas fa-clock"></i></div><div class="stat-value"><?php echo $stats['waiting'] ?? 0; ?></div><div class="stat-label">Waiting</div></div>
        <div class="stat-card"><div class="stat-icon"><i class="fas fa-bell"></i></div><div class="stat-value"><?php echo $stats['called'] ?? 0; ?></div><div class="stat-label">Called</div></div>
        <div class="stat-card"><div class="stat-icon"><i class="fas fa-play-circle"></i></div><div class="stat-value"><?php echo $stats['in_progress'] ?? 0; ?></div><div class="stat-label">In Progress</div></div>
        <div class="stat-card"><div class="stat-icon"><i class="fas fa-check-circle"></i></div><div class="stat-value"><?php echo $stats['completed'] ?? 0; ?></div><div class="stat-label">Completed</div></div>
        <div class="stat-card"><div class="stat-icon"><i class="fas fa-hourglass-half"></i></div><div class="stat-value"><?php echo round($stats['avg_time'] ?? 0); ?> min</div><div class="stat-label">Avg Time</div></div>
    </div>

    <!-- Batch Bar -->
    <div class="batch-bar">
        <div class="batch-info">
            <div class="batch-item"><span class="batch-label">Current Batch</span><span class="batch-value highlight"><?php echo date('h:00 A', strtotime($batch_info['current_hour'])); ?></span></div>
            <div class="batch-item"><span class="batch-label">Patients in Batch</span><span class="batch-value"><?php echo $batch_info['current_count']; ?>/20</span></div>
            <div class="batch-item"><span class="batch-label">Remaining Slots</span><span class="batch-value"><?php echo $batch_info['remaining_slots']; ?></span></div>
            <div class="batch-item"><span class="batch-label">Next Batch</span><span class="batch-value"><?php echo date('h:00 A', strtotime($batch_info['next_hour'])); ?></span></div>
        </div>
        <div class="progress-container">
            <div class="progress-bar-bg"><div class="progress-fill" style="width: <?php echo ($batch_info['current_count'] / 20) * 100; ?>%"></div></div>
            <div class="progress-stats"><?php echo round(($batch_info['current_count'] / 20) * 100); ?>%</div>
        </div>
    </div>

    <!-- Now Serving -->
    <?php 
    $current_patient = null;
    foreach ($queue as $patient) { if ($patient['status'] == 'in-progress') { $current_patient = $patient; break; } }
    ?>
    <?php if ($current_patient): ?>
    <div class="now-serving">
        <div class="now-serving-label"><i class="fas fa-bell"></i> NOW SERVING</div>
        <div class="now-serving-number"><?php echo $current_patient['queue_number']; ?></div>
        <div class="now-serving-name"><?php echo $current_patient['first_name'] . ' ' . $current_patient['last_name']; ?></div>
        <div><span class="now-serving-badge"><?php echo $current_patient['priority_level']; ?></span></div>
    </div>
    <?php endif; ?>

    <div class="row" style="display: flex; gap: 24px; flex-wrap: wrap;">
        <!-- Queue List -->
        <div style="flex: 2; min-width: 300px;">
            <div class="panel">
                <div class="panel-header">
                    <h3><i class="fas fa-list-ol"></i> Current Queue</h3>
                    <div style="display: flex; gap: 12px;">
                        <form method="POST"><button type="submit" name="call_next" class="btn-primary"><i class="fas fa-bell"></i> Call Next Patient</button></form>
                        <a href="registration.php" class="btn-secondary"><i class="fas fa-user-plus"></i> Register</a>
                    </div>
                </div>
                <div class="queue-list">
                    <?php if (empty($queue)): ?>
                        <div class="empty-state"><i class="fas fa-check-circle"></i><p>No patients in queue</p><small>Queue is empty. New patients will appear here when registered.</small></div>
                    <?php else: ?>
                        <?php foreach ($queue as $patient): ?>
                            <div class="queue-card <?php echo $patient['priority_level']; ?> <?php echo $patient['status'] == 'called' ? 'calling' : ''; ?>">
                                <div class="queue-row">
                                    <div class="priority-badge priority-<?php echo $patient['priority_level']; ?>"><?php echo $patient['priority_level']; ?></div>
                                    <div class="queue-details">
                                        <div><span class="queue-number"><?php echo $patient['queue_number']; ?></span><span class="batch-tag">Batch <?php echo $patient['batch_number']; ?>:00</span></div>
                                        <div class="patient-name"><?php echo $patient['last_name'] . ', ' . $patient['first_name']; ?> <?php if ($patient['patient_type'] == 'military'): ?><i class="fas fa-shield-alt" style="color: var(--light-coral);"></i><?php endif; ?></div>
                                        <div class="patient-info">Age: <?php $dob = new DateTime($patient['date_of_birth']); echo (new DateTime())->diff($dob)->y; ?> yrs | Registered: <?php echo date('h:i A', strtotime($patient['registered_at'])); ?></div>
                                    </div>
                                    <div><span class="status-badge status-<?php echo $patient['status']; ?>"><?php echo ucfirst(str_replace('-', ' ', $patient['status'])); ?></span><div class="patient-info" style="margin-top: 4px;">Pos: <?php echo $patient['position_in_batch']; ?>/batch • Wait: <?php echo $patient['waiting_minutes']; ?> min</div></div>
                                    <div>
                                        <?php if ($patient['status'] == 'waiting'): ?>
                                            <form method="POST"><input type="hidden" name="queue_id" value="<?php echo $patient['id']; ?>"><input type="hidden" name="status" value="called"><button type="submit" name="update_status" class="btn-icon btn-call"><i class="fas fa-bell"></i> Call</button></form>
                                        <?php elseif ($patient['status'] == 'called'): ?>
                                            <form method="POST"><input type="hidden" name="queue_id" value="<?php echo $patient['id']; ?>"><input type="hidden" name="status" value="in-progress"><button type="submit" name="update_status" class="btn-icon btn-start"><i class="fas fa-play"></i> Start</button></form>
                                        <?php elseif ($patient['status'] == 'in-progress'): ?>
                                            <form method="POST"><input type="hidden" name="queue_id" value="<?php echo $patient['id']; ?>"><input type="hidden" name="status" value="completed"><button type="submit" name="update_status" class="btn-icon btn-complete"><i class="fas fa-check"></i> Complete</button></form>
                                        <?php endif; ?>
                                        <?php if ($patient['status'] == 'in-progress'): ?>
    <div style="display: flex; gap: 8px;">
        <form method="POST"><input type="hidden" name="queue_id" value="<?php echo $patient['id']; ?>"><input type="hidden" name="status" value="completed"><button type="submit" name="update_status" class="btn-icon btn-complete"><i class="fas fa-check"></i> Complete</button></form>
        <a href="submit-result.php?clinic_id=<?php echo $clinic_id; ?>&queue_id=<?php echo $patient['id']; ?>" class="btn-icon btn-result" style="background: var(--soft-blue); color: white;">
            <i class="fas fa-file-alt"></i> Submit Result
        </a>
    </div>
<?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Sidebar Info -->
        <div style="flex: 1; min-width: 280px;">
            <div class="info-card">
                <div class="card-header"><i class="fas fa-bolt"></i> Quick Actions</div>
                <div class="card-body">
                    <a href="registration.php" class="btn-primary" style="width: 100%; margin-bottom: 12px; justify-content: center;"><i class="fas fa-user-plus"></i> Register New Patient</a>
                    <a href="patient-queue.php" class="btn-secondary" style="width: 100%; margin-bottom: 12px; justify-content: center;"><i class="fas fa-list"></i> View All Clinics</a>
                    <button onclick="location.reload()" class="btn-secondary" style="width: 100%; justify-content: center;"><i class="fas fa-sync"></i> Refresh</button>
                </div>
            </div>

            <div class="info-card">
                <div class="card-header"><i class="fas fa-info-circle"></i> Clinic Information</div>
                <div class="card-body">
                    <div class="info-row"><span class="info-label">Capacity</span><span class="info-value"><?php echo $clinic['capacity_per_hour']; ?> patients/hour</span></div>
                    <div class="info-row"><span class="info-label">Current Load</span><span class="info-value"><?php echo ($stats['waiting'] + $stats['called'] + $stats['in_progress']); ?> patients</span></div>
                    <div class="info-row"><span class="info-label">Est. Wait Time</span><span class="info-value"><?php echo ($stats['waiting'] + $stats['called']) * 10; ?> minutes</span></div>
                    <div class="info-row"><span class="info-label">Completion Rate</span><span class="info-value"><?php echo $completion_rate; ?>%</span></div>
                </div>
            </div>

            <div class="info-card">
                <div class="card-header"><i class="fas fa-history"></i> Recently Completed</div>
                <div class="card-body">
                    <?php if (empty($completed_patients)): ?>
                        <p style="color: var(--charcoal); opacity: 0.6; text-align: center;">No completed patients yet today</p>
                    <?php else: ?>
                        <ul class="completed-list">
                            <?php foreach ($completed_patients as $completed): ?>
                                <li class="completed-item"><div><span class="completed-number"><?php echo $completed['queue_number']; ?></span><div class="completed-name"><?php echo $completed['first_name'] . ' ' . $completed['last_name']; ?></div></div><div class="completed-time"><?php echo $completed['completed_time']; ?></div></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</main>

<script>
    function updateDateTime() {
        const now = new Date();
        document.getElementById('currentDate').textContent = now.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
        document.getElementById('currentTime').textContent = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
    }
    updateDateTime();
    setInterval(updateDateTime, 1000);

    function toggleDropdown() {
        document.getElementById('dropdownMenu').classList.toggle('show');
    }
    document.addEventListener('click', function(e) {
        const dropdown = document.getElementById('dropdownMenu');
        const avatar = document.querySelector('.user-avatar');
        if (dropdown && avatar && !avatar.contains(e.target) && !dropdown.contains(e.target)) {
            dropdown.classList.remove('show');
        }
    });

    setTimeout(() => {
        document.querySelectorAll('.alert').forEach(alert => { alert.style.opacity = '0'; setTimeout(() => alert.remove(), 500); });
    }, 5000);
</script>
</body>
</html>