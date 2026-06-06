<?php
// admin/patient-results.php - View Patient Results
// Camp Evangelista Station Hospital

require_once dirname(__DIR__) . '/config/database.php';

session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: ../index.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

$patient_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$message = '';

// Get patient info
$patient_query = "SELECT * FROM patients WHERE id = :id";
$patient_stmt = $db->prepare($patient_query);
$patient_stmt->bindParam(':id', $patient_id);
$patient_stmt->execute();
$patient = $patient_stmt->fetch(PDO::FETCH_ASSOC);

if (!$patient) {
    header('Location: patients.php');
    exit();
}

// Get all results for this patient
$results_query = "SELECT cr.*, c.name as clinic_name, u.full_name as doctor_name,
                         q.queue_number, q.registered_at
                  FROM clinic_results cr
                  JOIN clinics c ON cr.clinic_id = c.id
                  LEFT JOIN users u ON cr.submitted_by = u.id
                  JOIN queue_entries q ON cr.queue_entry_id = q.id
                  WHERE cr.patient_id = :patient_id
                  ORDER BY cr.submitted_at DESC";
$results_stmt = $db->prepare($results_query);
$results_stmt->bindParam(':patient_id', $patient_id);
$results_stmt->execute();
$results = $results_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Results | <?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?> | Camp Evangelista</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    
    <style>
        :root {
            --soft-blue: #4A90E2;
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
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: var(--light-gray); color: var(--charcoal); line-height: 1.5; }

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

        .main-content { margin-left: 280px; padding: 28px 36px; min-height: 100vh; }
        .top-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 32px; padding-bottom: 20px; border-bottom: 1px solid var(--border-light); }
        .page-title h1 { color: var(--dark-gray); font-size: 1.75rem; font-weight: 600; margin-bottom: 4px; }
        .page-title p { color: var(--charcoal); font-size: 0.85rem; opacity: 0.7; }
        .date-time { text-align: right; font-size: 0.85rem; }
        .date { color: var(--charcoal); font-weight: 500; }
        .time { color: var(--soft-blue); font-weight: 600; }

        .patient-header { background: var(--white); border-radius: 20px; padding: 24px; margin-bottom: 24px; border: 1px solid var(--border-light); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; }
        .patient-name h2 { color: var(--dark-gray); font-size: 1.3rem; margin-bottom: 4px; }
        .patient-name p { color: var(--charcoal); font-size: 0.85rem; }
        .mrn-badge { background: var(--soft-blue-light); color: var(--soft-blue); padding: 8px 16px; border-radius: 30px; font-weight: 600; }

        .results-grid { display: grid; gap: 24px; }
        .result-card { background: var(--white); border-radius: 20px; border: 1px solid var(--border-light); overflow: hidden; box-shadow: var(--shadow-sm); }
        .result-header { background: var(--light-gray); padding: 16px 24px; border-bottom: 1px solid var(--border-light); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; }
        .result-clinic { font-weight: 700; color: var(--dark-gray); display: flex; align-items: center; gap: 8px; }
        .result-clinic i { color: var(--soft-blue); }
        .result-date { font-size: 0.75rem; color: var(--charcoal); }
        .result-body { padding: 24px; }
        .result-section { margin-bottom: 20px; }
        .result-section:last-child { margin-bottom: 0; }
        .result-section-title { font-size: 0.7rem; font-weight: 600; color: var(--charcoal); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px; display: flex; align-items: center; gap: 6px; }
        .result-section-title i { color: var(--soft-blue); }
        .result-section-content { background: var(--light-gray); padding: 12px 16px; border-radius: 12px; font-size: 0.85rem; line-height: 1.6; }
        .result-data-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 12px; }
        .result-data-item { background: var(--light-gray); padding: 10px 12px; border-radius: 10px; }
        .result-data-label { font-size: 0.7rem; font-weight: 600; color: var(--charcoal); text-transform: uppercase; margin-bottom: 4px; }
        .result-data-value { font-size: 0.9rem; color: var(--dark-gray); font-weight: 500; }
        .empty-state { text-align: center; padding: 60px; background: var(--white); border-radius: 20px; border: 1px solid var(--border-light); color: var(--charcoal); opacity: 0.6; }
        .empty-state i { font-size: 3rem; margin-bottom: 16px; color: var(--soft-blue); }
        .btn-back { display: inline-flex; align-items: center; gap: 8px; background: var(--teal); color: white; padding: 10px 20px; border-radius: 12px; text-decoration: none; font-weight: 500; transition: all 0.2s; }
        .btn-back:hover { background: var(--teal-dark); transform: translateY(-1px); }

        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); }
            .main-content { margin-left: 0; padding: 20px; }
            .top-bar { flex-direction: column; align-items: flex-start; gap: 16px; }
            .result-header { flex-direction: column; align-items: flex-start; }
        }
    </style>
</head>
<body>

<aside class="sidebar">
    <div class="sidebar-logo"><h2>4ID Station Hospital</h2><p>Camp Evangelista</p></div>
    <ul class="nav-menu">
        <li class="nav-item"><a href="dashboard.php" class="nav-link"><i class="fas fa-tachometer-alt"></i><span>Dashboard</span></a></li>
        <li class="nav-item"><a href="patients.php" class="nav-link"><i class="fas fa-users"></i><span>Patients</span></a></li>
        <li class="nav-item"><a href="queue-monitor.php" class="nav-link"><i class="fas fa-chart-line"></i><span>Queue Monitor</span></a></li>
        <li class="nav-item"><a href="clinic-congestion.php" class="nav-link"><i class="fas fa-chart-simple"></i><span>Clinic Congestion</span></a></li>
        <li class="nav-item"><a href="reports.php" class="nav-link"><i class="fas fa-chart-bar"></i><span>Reports</span></a></li>
        <li class="nav-item"><a href="users.php" class="nav-link"><i class="fas fa-users-cog"></i><span>User Management</span></a></li>
        <li class="nav-item"><a href="../logout.php" class="nav-link" style="color: var(--light-coral);"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a></li>
    </ul>
</aside>

<main class="main-content">
    <div class="top-bar">
        <div class="page-title">
            <h1><i class="fas fa-file-alt"></i> Patient Results</h1>
            <p>View all clinical results and examinations</p>
        </div>
        <div class="date-time"><div class="date" id="currentDate"></div><div class="time" id="currentTime"></div></div>
    </div>

    <div class="patient-header">
        <div class="patient-name">
            <h2><?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?></h2>
            <p><i class="fas fa-calendar-alt"></i> DOB: <?php echo date('M d, Y', strtotime($patient['date_of_birth'])); ?> | 
               <i class="fas fa-venus-mars"></i> <?php echo $patient['gender']; ?></p>
        </div>
        <div class="mrn-badge"><i class="fas fa-id-card"></i> MRN: <?php echo htmlspecialchars($patient['mrn']); ?></div>
    </div>

    <?php if (empty($results)): ?>
        <div class="empty-state">
            <i class="fas fa-file-alt"></i>
            <p>No clinical results found for this patient</p>
            <small>Results will appear here once examinations are completed</small>
        </div>
    <?php else: ?>
        <div class="results-grid">
            <?php foreach ($results as $result): 
                $result_data = json_decode($result['result_data'], true);
            ?>
                <div class="result-card">
                    <div class="result-header">
                        <div class="result-clinic">
                            <i class="fas fa-clinic-medical"></i>
                            <span><?php echo htmlspecialchars($result['clinic_name']); ?></span>
                            <span style="font-size: 0.7rem; background: var(--soft-blue-light); padding: 2px 8px; border-radius: 20px;">
                                Queue: <?php echo htmlspecialchars($result['queue_number']); ?>
                            </span>
                        </div>
                        <div class="result-date">
                            <i class="fas fa-calendar-alt"></i> <?php echo date('F d, Y h:i A', strtotime($result['submitted_at'])); ?>
                            <?php if ($result['doctor_name']): ?>
                                <span style="margin-left: 12px;"><i class="fas fa-user-md"></i> <?php echo htmlspecialchars($result['doctor_name']); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="result-body">
                        <?php if ($result_data && is_array($result_data)): ?>
                            <div class="result-data-grid">
                                <?php foreach ($result_data as $key => $value): 
                                    if (empty($value)) continue;
                                    $label = ucwords(str_replace('_', ' ', $key));
                                ?>
                                    <div class="result-data-item">
                                        <div class="result-data-label"><?php echo htmlspecialchars($label); ?></div>
                                        <div class="result-data-value"><?php echo nl2br(htmlspecialchars($value)); ?></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($result['findings'])): ?>
                            <div class="result-section">
                                <div class="result-section-title"><i class="fas fa-stethoscope"></i> Clinical Findings</div>
                                <div class="result-section-content"><?php echo nl2br(htmlspecialchars($result['findings'])); ?></div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($result['recommendations'])): ?>
                            <div class="result-section">
                                <div class="result-section-title"><i class="fas fa-clinic-medical"></i> Recommendations</div>
                                <div class="result-section-content"><?php echo nl2br(htmlspecialchars($result['recommendations'])); ?></div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    
    <div style="margin-top: 24px;">
        <a href="patients.php" class="btn-back"><i class="fas fa-arrow-left"></i> Back to Patients</a>
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
    // ============================================
// AUTO-LOGOUT AFTER INACTIVITY
// ============================================

// Timeout in milliseconds (30 minutes = 30 * 60 * 1000)
const INACTIVITY_TIMEOUT = 30 * 60 * 1000; // 30 minutes
let inactivityTimer;
let warningTimer;
let warningShown = false;

// Function to reset the inactivity timer
function resetInactivityTimer() {
    // Clear existing timers
    if (inactivityTimer) clearTimeout(inactivityTimer);
    if (warningTimer) clearTimeout(warningTimer);
    warningShown = false;
    hideWarningModal();
    
    // Start new timer
    inactivityTimer = setTimeout(logoutUser, INACTIVITY_TIMEOUT);
    
    // Set warning timer (show warning 2 minutes before logout)
    warningTimer = setTimeout(showWarningModal, INACTIVITY_TIMEOUT - (2 * 60 * 1000));
    
    // Send heartbeat to server to keep session alive
    sendHeartbeat();
}

// Function to send heartbeat to server
function sendHeartbeat() {
    fetch('heartbeat.php', {
        method: 'POST',
        credentials: 'same-origin'
    }).catch(err => console.log('Heartbeat failed:', err));
}

// Function to logout the user
function logoutUser() {
    // Show logout message
    const logoutMsg = document.createElement('div');
    logoutMsg.innerHTML = `
        <div style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; 
                    background: rgba(0,0,0,0.8); z-index: 9999; display: flex; 
                    align-items: center; justify-content: center;">
            <div style="background: white; padding: 30px; border-radius: 16px; text-align: center; max-width: 400px;">
                <i class="fas fa-clock" style="font-size: 48px; color: #FF6F61; margin-bottom: 20px;"></i>
                <h3>Session Expired</h3>
                <p>You have been logged out due to inactivity.</p>
                <div style="margin-top: 20px;">
                    <div class="spinner"></div>
                    <p style="margin-top: 10px;">Redirecting to login page...</p>
                </div>
            </div>
        </div>
    `;
    document.body.appendChild(logoutMsg);
    
    // Redirect to logout page after 2 seconds
    setTimeout(function() {
        window.location.href = '../logout.php';
    }, 2000);
}

// Function to show warning modal
function showWarningModal() {
    if (warningShown) return;
    warningShown = true;
    
    // Create warning modal
    const modal = document.createElement('div');
    modal.id = 'sessionWarningModal';
    modal.innerHTML = `
        <div style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; 
                    background: rgba(0,0,0,0.5); z-index: 10000; display: flex; 
                    align-items: center; justify-content: center;">
            <div style="background: white; padding: 30px; border-radius: 16px; text-align: center; max-width: 400px; box-shadow: 0 4px 20px rgba(0,0,0,0.2);">
                <i class="fas fa-hourglass-half" style="font-size: 48px; color: #FFB84D; margin-bottom: 20px;"></i>
                <h3>Session About to Expire</h3>
                <p>You will be logged out due to inactivity.</p>
                <p id="countdownText" style="font-size: 24px; font-weight: bold; margin: 15px 0;">2:00</p>
                <button onclick="keepSessionAlive()" style="background: #009688; color: white; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer; font-weight: 600;">
                    <i class="fas fa-mouse-pointer"></i> Stay Logged In
                </button>
            </div>
        </div>
    `;
    document.body.appendChild(modal);
    
    // Start countdown
    let secondsLeft = 120;
    const countdownElement = document.getElementById('countdownText');
    
    const countdownInterval = setInterval(function() {
        secondsLeft--;
        const minutes = Math.floor(secondsLeft / 60);
        const seconds = secondsLeft % 60;
        if (countdownElement) {
            countdownElement.textContent = `${minutes}:${seconds.toString().padStart(2, '0')}`;
        }
        
        if (secondsLeft <= 0) {
            clearInterval(countdownInterval);
        }
    }, 1000);
}

// Function to keep session alive
function keepSessionAlive() {
    // Hide warning modal
    hideWarningModal();
    
    // Send heartbeat to refresh session
    fetch('heartbeat.php', {
        method: 'POST',
        credentials: 'same-origin'
    }).then(function() {
        // Reset timers
        resetInactivityTimer();
    }).catch(function(err) {
        console.log('Heartbeat failed:', err);
        resetInactivityTimer();
    });
}

// Function to hide warning modal
function hideWarningModal() {
    const modal = document.getElementById('sessionWarningModal');
    if (modal) {
        modal.remove();
    }
}

// Track user activity
const events = ['mousedown', 'mousemove', 'keypress', 'scroll', 'touchstart', 'click', 'keydown'];

events.forEach(function(event) {
    document.addEventListener(event, resetInactivityTimer, false);
});

// Initialize timer on page load
resetInactivityTimer();

// Also send heartbeat every 5 minutes to keep session alive while active
setInterval(function() {
    if (!warningShown) {
        sendHeartbeat();
    }
}, 5 * 60 * 1000); // Every 5 minutes
</script>
</body>
</html>