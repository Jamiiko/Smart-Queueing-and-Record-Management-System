<?php
// patient-portal/track-queue.php - Track Queue Status with Medical Journey Roadmap
// Camp Evangelista Station Hospital

require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

$queue_info = null;
$error = '';
$searched = false;
$search_term = '';
$all_clinics = [];
$completed_clinics = [];
$pending_clinics = [];
$current_clinic = null;

// ============================================
// HELPER FUNCTIONS
// ============================================

/**
 * Get all active clinics
 */
function getAllClinics($db) {
    $query = "SELECT id, name, description, capacity_per_hour, clinic_order 
              FROM clinics WHERE is_active = 1 
              ORDER BY clinic_order ASC, id ASC";
    $stmt = $db->prepare($query);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get completed clinics for a patient today
 */
function getCompletedClinics($db, $patient_id) {
    $query = "SELECT DISTINCT q.clinic_id, c.name, q.status, q.completed_at
              FROM queue_entries q
              JOIN clinics c ON q.clinic_id = c.id
              WHERE q.patient_id = :patient_id 
              AND q.status = 'completed'
              AND DATE(q.registered_at) = CURDATE()
              ORDER BY q.completed_at ASC";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':patient_id', $patient_id);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get current/in-progress clinic
 */
function getCurrentClinic($db, $patient_id) {
    $query = "SELECT q.clinic_id, c.name, q.status, q.called_at, q.registered_at
              FROM queue_entries q
              JOIN clinics c ON q.clinic_id = c.id
              WHERE q.patient_id = :patient_id 
              AND q.status IN ('called', 'in-progress')
              AND DATE(q.registered_at) = CURDATE()
              ORDER BY q.registered_at DESC
              LIMIT 1";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':patient_id', $patient_id);
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Get pending clinics (patient is registered but not yet completed)
 */
function getPendingClinics($db, $patient_id, $all_clinic_ids, $completed_ids, $current_clinic_id = null) {
    $pending = [];
    foreach ($all_clinic_ids as $clinic_id) {
        if (in_array($clinic_id, $completed_ids)) {
            continue;
        }
        if ($current_clinic_id && $clinic_id == $current_clinic_id) {
            continue;
        }
        
        // Check if patient is registered for this clinic
        $query = "SELECT q.status, q.queue_number, q.priority_level,
                         (SELECT COUNT(*) + 1 FROM queue_entries 
                          WHERE clinic_id = q.clinic_id 
                          AND status IN ('waiting', 'called')
                          AND DATE(registered_at) = CURDATE()
                          AND registered_at < q.registered_at) as position
                  FROM queue_entries q
                  WHERE q.patient_id = :patient_id 
                  AND q.clinic_id = :clinic_id
                  AND DATE(q.registered_at) = CURDATE()";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':patient_id', $patient_id);
        $stmt->bindParam(':clinic_id', $clinic_id);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            $pending[] = [
                'clinic_id' => $clinic_id,
                'status' => $result['status'],
                'queue_number' => $result['queue_number'],
                'position' => $result['position'] ?? '?'
            ];
        } else {
            // Not yet registered - will be auto-queued after completing current
            $pending[] = [
                'clinic_id' => $clinic_id,
                'status' => 'pending',
                'queue_number' => null,
                'position' => null
            ];
        }
    }
    return $pending;
}

// ============================================
// SEARCH LOGIC
// ============================================

// Check if token is provided in URL (from QR code or direct link)
if (isset($_GET['token']) && !empty($_GET['token'])) {
    $searched = true;
    $search_term = trim($_GET['token']);
    
    $query = "SELECT q.*, p.id as patient_id, p.first_name, p.last_name, p.patient_type, p.is_pwd, p.is_senior, p.is_pregnant, c.name as clinic_name,
                     TIMESTAMPDIFF(MINUTE, q.registered_at, NOW()) as waiting_minutes,
                     (SELECT COUNT(*) + 1 FROM queue_entries 
                      WHERE clinic_id = q.clinic_id 
                      AND status IN ('waiting', 'called')
                      AND DATE(registered_at) = CURDATE()
                      AND registered_at < q.registered_at) as position_in_queue
              FROM queue_entries q
              JOIN patients p ON q.patient_id = p.id
              JOIN clinics c ON q.clinic_id = c.id
              WHERE q.transaction_token = :token";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':token', $search_term);
    $stmt->execute();
    
    if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $queue_info = $row;
        
        // Build medical journey roadmap for military patients
        if ($queue_info['patient_type'] == 'military') {
            $all_clinics = getAllClinics($db);
            $completed_clinics = getCompletedClinics($db, $queue_info['patient_id']);
            $current_clinic = getCurrentClinic($db, $queue_info['patient_id']);
            
            $completed_ids = array_column($completed_clinics, 'clinic_id');
            $all_clinic_ids = array_column($all_clinics, 'id');
            $current_clinic_id = $current_clinic ? $current_clinic['clinic_id'] : null;
            
            $pending_clinics = getPendingClinics($db, $queue_info['patient_id'], $all_clinic_ids, $completed_ids, $current_clinic_id);
        }
    } else {
        $error = 'Invalid tracking token. Please check your token and try again.';
    }
}
// Check if queue number is provided in URL
elseif (isset($_GET['q']) && !empty($_GET['q'])) {
    $searched = true;
    $search_term = trim($_GET['q']);
    
    $query = "SELECT q.*, p.id as patient_id, p.first_name, p.last_name, p.patient_type, p.is_pwd, p.is_senior, p.is_pregnant, c.name as clinic_name,
                     TIMESTAMPDIFF(MINUTE, q.registered_at, NOW()) as waiting_minutes,
                     (SELECT COUNT(*) + 1 FROM queue_entries 
                      WHERE clinic_id = q.clinic_id 
                      AND status IN ('waiting', 'called')
                      AND DATE(registered_at) = CURDATE()
                      AND registered_at < q.registered_at) as position_in_queue
              FROM queue_entries q
              JOIN patients p ON q.patient_id = p.id
              JOIN clinics c ON q.clinic_id = c.id
              WHERE q.queue_number = :queue_number";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':queue_number', $search_term);
    $stmt->execute();
    
    if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $queue_info = $row;
        
        // Build medical journey roadmap for military patients
        if ($queue_info['patient_type'] == 'military') {
            $all_clinics = getAllClinics($db);
            $completed_clinics = getCompletedClinics($db, $queue_info['patient_id']);
            $current_clinic = getCurrentClinic($db, $queue_info['patient_id']);
            
            $completed_ids = array_column($completed_clinics, 'clinic_id');
            $all_clinic_ids = array_column($all_clinics, 'id');
            $current_clinic_id = $current_clinic ? $current_clinic['clinic_id'] : null;
            
            $pending_clinics = getPendingClinics($db, $queue_info['patient_id'], $all_clinic_ids, $completed_ids, $current_clinic_id);
        }
    } else {
        $error = 'Queue number not found. Please check and try again.';
    }
}
// Handle POST form submission
elseif (isset($_POST['track'])) {
    $searched = true;
    $search_term = trim($_POST['search_term']);
    
    // Search by both queue number AND transaction token
    $query = "SELECT q.*, p.id as patient_id, p.first_name, p.last_name, p.patient_type, p.is_pwd, p.is_senior, p.is_pregnant, c.name as clinic_name,
                     TIMESTAMPDIFF(MINUTE, q.registered_at, NOW()) as waiting_minutes,
                     (SELECT COUNT(*) + 1 FROM queue_entries 
                      WHERE clinic_id = q.clinic_id 
                      AND status IN ('waiting', 'called')
                      AND DATE(registered_at) = CURDATE()
                      AND registered_at < q.registered_at) as position_in_queue
              FROM queue_entries q
              JOIN patients p ON q.patient_id = p.id
              JOIN clinics c ON q.clinic_id = c.id
              WHERE q.queue_number = :search_term OR q.transaction_token = :search_term";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':search_term', $search_term);
    $stmt->execute();
    
    if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $queue_info = $row;
        
        // Build medical journey roadmap for military patients
        if ($queue_info['patient_type'] == 'military') {
            $all_clinics = getAllClinics($db);
            $completed_clinics = getCompletedClinics($db, $queue_info['patient_id']);
            $current_clinic = getCurrentClinic($db, $queue_info['patient_id']);
            
            $completed_ids = array_column($completed_clinics, 'clinic_id');
            $all_clinic_ids = array_column($all_clinics, 'id');
            $current_clinic_id = $current_clinic ? $current_clinic['clinic_id'] : null;
            
            $pending_clinics = getPendingClinics($db, $queue_info['patient_id'], $all_clinic_ids, $completed_ids, $current_clinic_id);
        }
    } else {
        $error = 'No record found. Please check your queue number or token and try again.';
    }
}

// Get estimated wait times for all clinics
$query = "SELECT c.id, c.name, c.capacity_per_hour,
                 COUNT(CASE WHEN q.status = 'waiting' THEN 1 END) as waiting_count,
                 COUNT(CASE WHEN q.status = 'in-progress' THEN 1 END) as in_progress_count,
                 COUNT(CASE WHEN q.status = 'completed' AND DATE(q.registered_at) = CURDATE() THEN 1 END) as completed_today
          FROM clinics c
          LEFT JOIN queue_entries q ON c.id = q.clinic_id AND DATE(q.registered_at) = CURDATE()
          WHERE c.is_active = 1
          GROUP BY c.id
          ORDER BY c.name";
$clinics = $db->query($query)->fetchAll(PDO::FETCH_ASSOC);

// Calculate journey progress percentage
$journey_progress = 0;
if ($queue_info && $queue_info['patient_type'] == 'military' && !empty($all_clinics)) {
    $total_clinics = count($all_clinics);
    $completed_count = count($completed_clinics);
    $journey_progress = ($completed_count / $total_clinics) * 100;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Track Queue | Patient Portal | Camp Evangelista Hospital</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    
    <style>
        /* ============================================
           CSS Variables - Color Palette
           ============================================ */
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
            --light-beige: #F4F1EC;
            --pale-blue: #E7F3FB;
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
            background: linear-gradient(135deg, var(--pale-blue) 0%, var(--light-gray) 100%);
            color: var(--charcoal);
            line-height: 1.5;
            min-height: 100vh;
        }

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
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 32px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
        }

        .logo h1 {
            color: var(--soft-blue);
            font-size: 1.3rem;
            font-weight: 700;
            letter-spacing: -0.3px;
        }

        .logo p {
            color: var(--charcoal);
            font-size: 0.7rem;
            opacity: 0.7;
        }

        .header-badge {
            background: var(--soft-blue-light);
            padding: 8px 16px;
            border-radius: 40px;
            font-size: 0.8rem;
            font-weight: 500;
            color: var(--soft-blue);
        }

        /* Main Container */
        .main-container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 48px 32px;
        }

        /* Search Card */
        .search-card {
            background: var(--white);
            border-radius: 24px;
            border: 1px solid var(--border-light);
            overflow: hidden;
            box-shadow: var(--shadow-md);
            margin-bottom: 32px;
        }

        .card-header {
            padding: 24px 28px;
            border-bottom: 1px solid var(--border-light);
            background: var(--white);
            text-align: center;
        }

        .card-header h2 {
            color: var(--dark-gray);
            font-size: 1.3rem;
            font-weight: 600;
        }

        .card-header p {
            color: var(--charcoal);
            font-size: 0.85rem;
            margin-top: 8px;
        }

        .card-body {
            padding: 28px;
        }

        /* Search Form */
        .search-form {
            display: flex;
            gap: 12px;
            align-items: center;
        }

        .search-input-wrapper {
            flex: 1;
            position: relative;
        }

        .search-input-wrapper i {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--soft-blue);
        }

        .search-input {
            width: 100%;
            padding: 14px 16px 14px 48px;
            border: 1px solid var(--border-light);
            border-radius: 16px;
            font-family: inherit;
            font-size: 1rem;
            transition: all 0.2s;
            background: var(--white);
        }

        .search-input:focus {
            outline: none;
            border-color: var(--soft-blue);
            box-shadow: 0 0 0 3px rgba(74, 144, 226, 0.1);
        }

        .btn-search {
            background: var(--teal);
            color: white;
            border: none;
            padding: 14px 32px;
            border-radius: 16px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: all 0.2s;
            font-size: 1rem;
        }

        .btn-search:hover {
            background: var(--teal-dark);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .tracking-options {
            display: flex;
            gap: 20px;
            margin-top: 16px;
            padding-top: 16px;
            border-top: 1px solid var(--border-light);
            justify-content: center;
            flex-wrap: wrap;
        }

        .tracking-options span {
            font-size: 0.75rem;
            color: var(--charcoal);
        }

        .tracking-options code {
            background: var(--light-gray);
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 0.7rem;
            font-family: monospace;
        }

        /* Alert */
        .alert {
            padding: 14px 20px;
            border-radius: 16px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .alert-danger {
            background: #FEF2F0;
            color: var(--light-coral);
            border-left: 3px solid var(--light-coral);
        }

        .alert-info {
            background: var(--soft-blue-light);
            color: var(--soft-blue);
            border-left: 3px solid var(--soft-blue);
        }

        .alert-success {
            background: var(--soft-green);
            color: var(--dark-gray);
            border-left: 3px solid var(--teal);
        }

        /* Queue Status Card */
        .queue-status-card {
            background: var(--white);
            border-radius: 24px;
            border: 1px solid var(--border-light);
            overflow: hidden;
            box-shadow: var(--shadow-md);
            margin-bottom: 32px;
            animation: fadeIn 0.5s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .status-header {
            padding: 20px 28px;
            background: var(--light-gray);
            border-bottom: 1px solid var(--border-light);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
        }

        .status-badge-large {
            display: inline-block;
            padding: 8px 20px;
            border-radius: 40px;
            font-size: 0.9rem;
            font-weight: 700;
        }

        .status-waiting {
            background: var(--warm-yellow);
            color: var(--dark-gray);
        }

        .status-called {
            background: var(--soft-blue-light);
            color: var(--soft-blue);
        }

        .status-progress {
            background: var(--soft-blue);
            color: white;
        }

        .status-completed {
            background: var(--soft-green);
            color: var(--dark-gray);
        }

        .queue-number-large {
            font-size: 2rem;
            font-weight: 800;
            color: var(--dark-gray);
            font-family: monospace;
        }

        .status-body {
            padding: 28px;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 24px;
        }

        .info-item {
            background: var(--light-gray);
            border-radius: 16px;
            padding: 16px;
        }

        .info-label {
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--charcoal);
            opacity: 0.7;
            margin-bottom: 8px;
        }

        .info-value {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--dark-gray);
        }

        .token-section {
            background: var(--soft-blue-light);
            border-radius: 16px;
            padding: 16px;
            margin-top: 16px;
            text-align: center;
        }

        .token-label {
            font-size: 0.65rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--soft-blue);
            margin-bottom: 4px;
        }

        .token-value {
            font-size: 0.85rem;
            font-weight: 600;
            font-family: monospace;
            color: var(--dark-gray);
            word-break: break-all;
        }

        .priority-badge {
            display: inline-block;
            padding: 6px 14px;
            border-radius: 30px;
            font-size: 0.8rem;
            font-weight: 700;
        }

        .priority-PR1 {
            background: var(--light-coral);
            color: white;
        }

        .priority-PR2 {
            background: var(--warm-yellow);
            color: var(--dark-gray);
        }

        .priority-PR3 {
            background: var(--soft-green);
            color: var(--dark-gray);
        }

        .progress-container {
            margin-top: 24px;
            padding-top: 24px;
            border-top: 1px solid var(--border-light);
        }

        .progress-label {
            display: flex;
            justify-content: space-between;
            font-size: 0.8rem;
            margin-bottom: 8px;
        }

        .progress-bar-bg {
            background: var(--light-gray);
            height: 8px;
            border-radius: 4px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background: var(--teal);
            border-radius: 4px;
            transition: width 0.3s;
        }

        .estimation-text {
            text-align: center;
            margin-top: 12px;
            font-size: 0.8rem;
            color: var(--charcoal);
        }

        /* ============================================
           MEDICAL JOURNEY ROADMAP - NEW SECTION
           ============================================ */
        .journey-card {
            background: var(--white);
            border-radius: 24px;
            border: 1px solid var(--border-light);
            overflow: hidden;
            box-shadow: var(--shadow-md);
            margin-bottom: 32px;
        }

        .journey-header {
            padding: 20px 28px;
            background: linear-gradient(135deg, var(--teal) 0%, var(--teal-dark) 100%);
            color: white;
        }

        .journey-header h3 {
            font-size: 1.2rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .journey-header p {
            font-size: 0.8rem;
            opacity: 0.9;
            margin-top: 6px;
        }

        .journey-progress-bar {
            padding: 20px 28px;
            background: var(--light-gray);
            border-bottom: 1px solid var(--border-light);
        }

        .journey-stats {
            display: flex;
            justify-content: space-between;
            margin-bottom: 12px;
            font-size: 0.8rem;
        }

        .journey-progress-bg {
            background: var(--border-light);
            height: 12px;
            border-radius: 6px;
            overflow: hidden;
        }

        .journey-progress-fill {
            height: 100%;
            background: var(--teal);
            border-radius: 6px;
            transition: width 0.5s ease;
            position: relative;
        }

        .journey-progress-fill::after {
            content: attr(data-progress);
            position: absolute;
            right: 5px;
            top: -20px;
            font-size: 0.7rem;
            color: var(--teal);
            font-weight: 600;
        }

        /* Timeline / Roadmap */
        .timeline {
            padding: 20px 28px;
        }

        .timeline-item {
            display: flex;
            margin-bottom: 24px;
            position: relative;
        }

        .timeline-item:last-child {
            margin-bottom: 0;
        }

        .timeline-marker {
            flex-shrink: 0;
            width: 48px;
            position: relative;
        }

        .timeline-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            background: var(--light-gray);
            color: var(--charcoal);
            border: 2px solid var(--border-light);
            transition: all 0.3s;
        }

        .timeline-icon.completed {
            background: var(--teal);
            border-color: var(--teal);
            color: white;
        }

        .timeline-icon.in-progress {
            background: var(--soft-blue);
            border-color: var(--soft-blue);
            color: white;
            animation: pulse 1.5s infinite;
        }

        .timeline-icon.pending {
            background: var(--light-gray);
            border-color: var(--border-light);
            color: #aaa;
        }

        .timeline-icon.waiting {
            background: var(--warm-yellow);
            border-color: var(--warm-yellow);
            color: var(--dark-gray);
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(74, 144, 226, 0.4); }
            50% { transform: scale(1.05); box-shadow: 0 0 0 8px rgba(74, 144, 226, 0); }
        }

        .timeline-line {
            position: absolute;
            left: 20px;
            top: 40px;
            width: 2px;
            height: calc(100% - 40px);
            background: var(--border-light);
        }

        .timeline-item:last-child .timeline-line {
            display: none;
        }

        .timeline-content {
            flex: 1;
            padding-left: 16px;
            padding-bottom: 8px;
        }

        .timeline-clinic-name {
            font-weight: 700;
            color: var(--dark-gray);
            font-size: 1rem;
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .timeline-status {
            font-size: 0.7rem;
            padding: 2px 10px;
            border-radius: 20px;
            font-weight: 600;
        }

        .status-completed-badge {
            background: var(--teal);
            color: white;
        }

        .status-inprogress-badge {
            background: var(--soft-blue);
            color: white;
        }

        .status-waiting-badge {
            background: var(--warm-yellow);
            color: var(--dark-gray);
        }

        .status-pending-badge {
            background: var(--light-gray);
            color: var(--charcoal);
        }

        .timeline-details {
            font-size: 0.75rem;
            color: var(--charcoal);
            margin-top: 6px;
        }

        .timeline-details i {
            margin-right: 4px;
            color: var(--soft-blue);
        }

        .completion-check {
            color: var(--teal);
            margin-left: 8px;
            font-size: 0.8rem;
        }

        .current-queue-info {
            background: var(--soft-blue-light);
            border-radius: 12px;
            padding: 8px 12px;
            margin-top: 6px;
            display: inline-block;
            font-size: 0.7rem;
        }

        /* Clinics Grid */
        .clinics-section {
            background: var(--white);
            border-radius: 24px;
            border: 1px solid var(--border-light);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
        }

        .clinics-header {
            padding: 20px 28px;
            border-bottom: 1px solid var(--border-light);
            background: var(--white);
        }

        .clinics-header h3 {
            color: var(--dark-gray);
            font-size: 1.1rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .clinics-header h3 i {
            color: var(--soft-blue);
        }

        .clinics-grid {
            padding: 20px 28px;
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 16px;
        }

        .clinic-card {
            background: var(--light-gray);
            border-radius: 16px;
            padding: 16px;
            transition: all 0.2s;
        }

        .clinic-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-sm);
        }

        .clinic-name {
            font-weight: 700;
            color: var(--dark-gray);
            margin-bottom: 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .wait-badge {
            background: var(--soft-blue-light);
            color: var(--soft-blue);
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
        }

        .wait-time {
            font-size: 0.8rem;
            color: var(--charcoal);
            margin-top: 8px;
        }

        /* Footer */
        .footer {
            background: var(--white);
            border-top: 1px solid var(--border-light);
            padding: 24px 0;
            margin-top: 48px;
        }

        .footer-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 32px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }

        .footer-links {
            display: flex;
            gap: 24px;
        }

        .footer-links a {
            color: var(--charcoal);
            text-decoration: none;
            font-size: 0.8rem;
            transition: color 0.2s;
        }

        .footer-links a:hover {
            color: var(--soft-blue);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .main-container {
                padding: 32px 20px;
            }
            .search-form {
                flex-direction: column;
            }
            .btn-search {
                width: 100%;
                justify-content: center;
            }
            .info-grid {
                grid-template-columns: 1fr;
            }
            .status-header {
                flex-direction: column;
                text-align: center;
            }
            .clinics-grid {
                grid-template-columns: 1fr;
            }
            .footer-container {
                flex-direction: column;
                text-align: center;
            }
            .header-container {
                flex-direction: column;
                text-align: center;
            }
            .timeline-item {
                flex-direction: column;
            }
            .timeline-marker {
                margin-bottom: 12px;
            }
            .timeline-line {
                display: none;
            }
        }
    </style>
</head>
<body>

<!-- Header -->
<header class="header">
    <div class="header-container">
        <div class="logo">
            <h1>4ID Station Hospital</h1>
            <p>Camp Evangelista • Patient Portal</p>
        </div>
        <div class="header-badge">
            <i class="fas fa-search"></i>
            <span>Track Queue</span>
        </div>
    </div>
</header>

<!-- Main Content -->
<main class="main-container">
    <!-- Search Card -->
    <div class="search-card">
        <div class="card-header">
            <h2><i class="fas fa-search"></i> Track Your Queue Status</h2>
            <p>Enter your queue number or transaction token to see your current status</p>
        </div>
        <div class="card-body">
            <form method="POST" class="search-form">
                <div class="search-input-wrapper">
                    <i class="fas fa-ticket-alt"></i>
                    <input type="text" name="search_term" class="search-input" 
                           placeholder="Enter queue number (e.g., M-09-001) or transaction token" 
                           value="<?php echo isset($_POST['search_term']) ? htmlspecialchars($_POST['search_term']) : (isset($_GET['q']) ? htmlspecialchars($_GET['q']) : (isset($_GET['token']) ? htmlspecialchars($_GET['token']) : '')); ?>" 
                           required>
                </div>
                <button type="submit" name="track" class="btn-search">
                    <i class="fas fa-arrow-right"></i> Track
                </button>
            </form>
            <div class="tracking-options">
                <span><i class="fas fa-ticket-alt"></i> Queue Number: <code>M-09-001</code></span>
                <span><i class="fas fa-key"></i> Transaction Token: <code>TXN-20260126-000123</code></span>
            </div>
        </div>
    </div>

    <!-- Info Alert when searching -->
    <?php if ($searched && !$queue_info && !$error): ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i>
            Enter your queue number (e.g., M-09-001) or transaction token from your ticket.
        </div>
    <?php endif; ?>

    <!-- Error Message -->
    <?php if ($error): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo $error; ?>
        </div>
    <?php endif; ?>

    <!-- Queue Status -->
    <?php if ($queue_info): ?>
        <div class="queue-status-card">
            <div class="status-header">
                <div>
                    <div class="queue-number-large"><?php echo htmlspecialchars($queue_info['queue_number']); ?></div>
                    <div style="font-size: 0.8rem; color: var(--charcoal);">Queue Number</div>
                </div>
                <div>
                    <span class="status-badge-large status-<?php echo $queue_info['status']; ?>">
                        <i class="fas <?php 
                            echo $queue_info['status'] == 'waiting' ? 'fa-clock' : 
                                ($queue_info['status'] == 'called' ? 'fa-bell' : 
                                ($queue_info['status'] == 'in-progress' ? 'fa-play-circle' : 'fa-check-circle')); 
                        ?>"></i>
                        <?php echo strtoupper(str_replace('-', ' ', $queue_info['status'])); ?>
                    </span>
                </div>
            </div>
            <div class="status-body">
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label"><i class="fas fa-user"></i> Patient Name</div>
                        <div class="info-value"><?php echo htmlspecialchars($queue_info['first_name'] . ' ' . $queue_info['last_name']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label"><i class="fas fa-clinic-medical"></i> Current Clinic</div>
                        <div class="info-value"><?php echo htmlspecialchars($queue_info['clinic_name']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label"><i class="fas fa-chart-line"></i> Priority Level</div>
                        <div class="info-value">
                            <span class="priority-badge priority-<?php echo $queue_info['priority_level']; ?>">
                                <?php echo $queue_info['priority_level']; ?> - 
                                <?php 
                                    if ($queue_info['priority_level'] == 'PR1') echo 'Military Personnel';
                                    elseif ($queue_info['priority_level'] == 'PR2') echo 'Priority Patient';
                                    else echo 'Regular Patient';
                                ?>
                            </span>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="info-label"><i class="fas fa-calendar-alt"></i> Registered</div>
                        <div class="info-value"><?php echo date('h:i A', strtotime($queue_info['registered_at'])); ?></div>
                    </div>
                </div>

                <?php if ($queue_info['transaction_token']): ?>
                <div class="token-section">
                    <div class="token-label"><i class="fas fa-key"></i> Your Tracking Token</div>
                    <div class="token-value"><?php echo htmlspecialchars($queue_info['transaction_token']); ?></div>
                    <div style="font-size: 0.65rem; margin-top: 8px;">Use this token to track your queue privately</div>
                </div>
                <?php endif; ?>

                <?php if ($queue_info['status'] == 'waiting'): ?>
                    <div class="progress-container">
                        <div class="progress-label">
                            <span>Your Position in Queue</span>
                            <span><?php echo $queue_info['position_in_queue'] ?? 'Calculating...'; ?> ahead</span>
                        </div>
                        <div class="progress-bar-bg">
                            <?php 
                            $total = $queue_info['waiting_minutes'] ? $queue_info['waiting_minutes'] + 10 : 20;
                            $position = $queue_info['position_in_queue'] ?? 1;
                            $percentage = min(95, (($position - 1) / max($total, 1)) * 100);
                            ?>
                            <div class="progress-fill" style="width: <?php echo $percentage; ?>%"></div>
                        </div>
                        <div class="estimation-text">
                            <i class="fas fa-hourglass-half"></i>
                            Estimated wait time: <strong><?php echo ($queue_info['position_in_queue'] ?? 1) * 8; ?> minutes</strong>
                        </div>
                    </div>
                <?php elseif ($queue_info['status'] == 'called'): ?>
                    <div class="progress-container">
                        <div class="estimation-text" style="color: var(--soft-blue);">
                            <i class="fas fa-bell"></i>
                            <strong>Your number has been called! Please proceed to the clinic immediately.</strong>
                        </div>
                    </div>
                <?php elseif ($queue_info['status'] == 'in-progress'): ?>
                    <div class="progress-container">
                        <div class="estimation-text" style="color: var(--teal);">
                            <i class="fas fa-play-circle"></i>
                            <strong>You are currently being attended to. Please wait for further instructions.</strong>
                        </div>
                    </div>
                <?php elseif ($queue_info['status'] == 'completed'): ?>
                    <div class="progress-container">
                        <div class="estimation-text" style="color: var(--soft-green);">
                            <i class="fas fa-check-circle"></i>
                            <strong>Consultation completed! Thank you for visiting Camp Evangelista Hospital.</strong>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- MEDICAL JOURNEY ROADMAP - For Military Patients -->
        <?php if ($queue_info['patient_type'] == 'military' && !empty($all_clinics)): ?>
        <div class="journey-card">
            <div class="journey-header">
                <h3>
                    <i class="fas fa-route"></i> 
                    Medical Assessment Journey
                </h3>
                <p>Track your progress through all required clinics for military medical assessment</p>
            </div>
            
            <div class="journey-progress-bar">
                <div class="journey-stats">
                    <span><i class="fas fa-flag-checkered"></i> Overall Progress</span>
                    <span><strong><?php echo count($completed_clinics); ?></strong> of <strong><?php echo count($all_clinics); ?></strong> clinics completed</span>
                </div>
                <div class="journey-progress-bg">
                    <div class="journey-progress-fill" style="width: <?php echo $journey_progress; ?>%;" data-progress="<?php echo round($journey_progress); ?>%"></div>
                </div>
                <div style="text-align: center; margin-top: 8px; font-size: 0.7rem; color: var(--charcoal);">
                    <?php if ($journey_progress == 100): ?>
                        <i class="fas fa-trophy" style="color: var(--warm-yellow);"></i> Congratulations! You have completed all medical assessments.
                    <?php elseif ($journey_progress > 0): ?>
                        <i class="fas fa-chart-line"></i> Keep going! You're making great progress.
                    <?php else: ?>
                        <i class="fas fa-play-circle"></i> Your medical journey begins here.
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="timeline">
                <?php 
                $completed_ids = array_column($completed_clinics, 'clinic_id');
                $current_clinic_id = $current_clinic ? $current_clinic['clinic_id'] : null;
                $pending_by_clinic = [];
                foreach ($pending_clinics as $p) {
                    $pending_by_clinic[$p['clinic_id']] = $p;
                }
                
                foreach ($all_clinics as $index => $clinic):
                    $clinic_id = $clinic['id'];
                    $is_completed = in_array($clinic_id, $completed_ids);
                    $is_current = ($current_clinic_id == $clinic_id);
                    $is_pending = isset($pending_by_clinic[$clinic_id]);
                    $pending_info = $pending_by_clinic[$clinic_id] ?? null;
                    
                    $status_class = '';
                    $status_text = '';
                    $icon = '';
                    $extra_info = '';
                    
                    if ($is_completed) {
                        $status_class = 'completed';
                        $status_text = 'Completed';
                        $icon = 'fa-check-circle';
                        
                        foreach ($completed_clinics as $cc) {
                            if ($cc['clinic_id'] == $clinic_id) {
                                $extra_info = '<i class="fas fa-clock"></i> Completed at: ' . date('h:i A', strtotime($cc['completed_at']));
                                break;
                            }
                        }
                    } elseif ($is_current) {
                        $status_class = 'in-progress';
                        $status_text = 'In Progress';
                        $icon = 'fa-spinner fa-pulse';
                        $extra_info = '<i class="fas fa-bell"></i> Currently being attended';
                        if ($current_clinic && $current_clinic['called_at']) {
                            $extra_info .= ' - Called at: ' . date('h:i A', strtotime($current_clinic['called_at']));
                        }
                    } elseif ($pending_info && $pending_info['status'] == 'waiting') {
                        $status_class = 'waiting';
                        $status_text = 'In Queue';
                        $icon = 'fa-hourglass-half';
                        $extra_info = '<i class="fas fa-ticket-alt"></i> Queue #: ' . $pending_info['queue_number'] . ' | Position: ' . ($pending_info['position'] ?? '?') . ' in queue';
                    } else {
                        $status_class = 'pending';
                        $status_text = 'Upcoming';
                        $icon = 'fa-hourglass-start';
                        $extra_info = '<i class="fas fa-info-circle"></i> Will be queued automatically after current clinic';
                    }
                ?>
                <div class="timeline-item">
                    <div class="timeline-marker">
                        <div class="timeline-icon <?php echo $status_class; ?>">
                            <i class="fas <?php echo $icon; ?>"></i>
                        </div>
                        <?php if ($index < count($all_clinics) - 1): ?>
                            <div class="timeline-line"></div>
                        <?php endif; ?>
                    </div>
                    <div class="timeline-content">
                        <div class="timeline-clinic-name">
                            <?php echo htmlspecialchars($clinic['name']); ?>
                            <span class="timeline-status status-<?php echo $status_class; ?>-badge">
                                <?php echo $status_text; ?>
                            </span>
                            <?php if ($is_completed): ?>
                                <span class="completion-check"><i class="fas fa-check-circle"></i> Done</span>
                            <?php endif; ?>
                        </div>
                        <?php if ($clinic['description']): ?>
                            <div style="font-size: 0.7rem; color: var(--charcoal); opacity: 0.7; margin-top: 2px;">
                                <?php echo htmlspecialchars($clinic['description']); ?>
                            </div>
                        <?php endif; ?>
                        <div class="timeline-details">
                            <?php echo $extra_info; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Completion Message -->
            <?php if ($journey_progress == 100): ?>
                <div style="padding: 20px 28px; background: linear-gradient(135deg, var(--soft-green) 0%, var(--teal) 100%); color: white; text-align: center;">
                    <i class="fas fa-trophy" style="font-size: 1.5rem; margin-bottom: 8px;"></i>
                    <h4>Medical Assessment Complete!</h4>
                    <p style="font-size: 0.85rem;">You have successfully completed all required medical assessments. Thank you for your service!</p>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    <?php endif; ?>

    <!-- Clinic Wait Times -->
    <div class="clinics-section">
        <div class="clinics-header">
            <h3><i class="fas fa-chart-line"></i> Current Wait Times by Clinic</h3>
        </div>
        <div class="clinics-grid">
            <?php foreach ($clinics as $clinic): ?>
                <div class="clinic-card">
                    <div class="clinic-name">
                        <span><?php echo htmlspecialchars($clinic['name']); ?></span>
                        <span class="wait-badge">
                            <i class="fas fa-users"></i> <?php echo $clinic['waiting_count']; ?> waiting
                        </span>
                    </div>
                    <div class="wait-time">
                        <i class="fas fa-clock"></i> Estimated wait: <strong><?php echo $clinic['waiting_count'] * 8; ?> minutes</strong>
                    </div>
                    <div class="wait-time" style="margin-top: 4px;">
                        <i class="fas fa-chart-simple"></i> Today: <?php echo $clinic['completed_today']; ?> completed
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</main>

<!-- Footer -->
<footer class="footer">
    <div class="footer-container">
        <div class="footer-links">
            <a href="index.php"><i class="fas fa-home"></i> Home</a>
            <a href="self-register.php"><i class="fas fa-user-plus"></i> Self Registration</a>
            <a href="../index.php"><i class="fas fa-lock"></i> Staff Login</a>
        </div>
        <div class="footer-copyright">
            <i class="fas fa-shield-alt"></i>
            <?php echo date('Y'); ?> 4th Infantry Division, Camp Evangelista Station Hospital
        </div>
    </div>
</footer>

<script>
    // Date and Time Display (optional - can add to header if desired)
    
    // Auto-refresh queue status every 30 seconds if tracking
    <?php if ($queue_info): ?>
    setTimeout(function() {
        location.reload();
    }, 30000);
    <?php endif; ?>
    
    // Animate progress bar on load
    document.addEventListener('DOMContentLoaded', function() {
        const progressFill = document.querySelector('.journey-progress-fill');
        if (progressFill) {
            const width = progressFill.style.width;
            progressFill.style.width = '0%';
            setTimeout(() => {
                progressFill.style.width = width;
            }, 100);
        }
    });
</script>

</body>
</html>