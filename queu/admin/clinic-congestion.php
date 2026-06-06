<?php
// admin/clinic-congestion.php - Clinic Congestion Monitor
// Camp Evangelista Station Hospital

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/QueueManager.php';

session_start();

// ============================================
// AUTHENTICATION CHECK
// ============================================
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: ../index.php');
    exit();
}

// ============================================
// DATABASE CONNECTION (Create $db FIRST)
// ============================================
$database = new Database();
$db = $database->getConnection();
$queueManager = new QueueManager($db);

// ============================================
// SESSION TIMEOUT (Now $db exists!)
// ============================================
require_once dirname(__DIR__) . '/includes/SessionManager.php';
$sessionManager = new SessionManager($db);
if (!$sessionManager->checkTimeout()) {
    exit(); // Already redirected
}
$sessionManager->logActivity('Viewed clinic congestion page');

$clinic_stats = $queueManager->getAllClinicsQueueStats();
$least_congested = $queueManager->findLeastCongestedClinic();

// Also get detailed clinic stats with in_progress counts
$detailed_query = "SELECT 
    c.id,
    c.name,
    c.capacity_per_hour,
    COUNT(CASE WHEN q.status IN ('waiting', 'called') AND DATE(q.registered_at) = CURDATE() THEN 1 END) as waiting_count,
    COUNT(CASE WHEN q.status = 'in-progress' AND DATE(q.registered_at) = CURDATE() THEN 1 END) as in_progress_count,
    COUNT(CASE WHEN q.status = 'completed' AND DATE(q.registered_at) = CURDATE() THEN 1 END) as completed_count
FROM clinics c
LEFT JOIN queue_entries q ON c.id = q.clinic_id
WHERE c.is_active = 1
GROUP BY c.id
ORDER BY waiting_count ASC";

$detailed_clinics = $db->query($detailed_query)->fetchAll(PDO::FETCH_ASSOC);

// Create a lookup array for detailed stats
$detailed_stats = [];
foreach ($detailed_clinics as $dc) {
    $detailed_stats[$dc['id']] = $dc;
}

// Enhance least_congested with missing data
$enhanced_clinics = [];
foreach ($least_congested as $clinic) {
    $clinic_id = $clinic['id'];
    $enhanced_clinic = $clinic;
    $enhanced_clinic['in_progress'] = $detailed_stats[$clinic_id]['in_progress_count'] ?? 0;
    $enhanced_clinic['waiting_count'] = $detailed_stats[$clinic_id]['waiting_count'] ?? $clinic['current_load'];
    $enhanced_clinic['completed'] = $detailed_stats[$clinic_id]['completed_count'] ?? 0;
    $enhanced_clinics[] = $enhanced_clinic;
}

// Calculate summary statistics
$total_waiting = 0;
$total_capacity = 0;
$avg_congestion = 0;

foreach ($enhanced_clinics as $clinic) {
    $total_waiting += $clinic['current_load'];
    $total_capacity += $clinic['capacity_per_hour'];
}
$avg_congestion = $total_capacity > 0 ? round(($total_waiting / $total_capacity) * 100) : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clinic Congestion Monitor | 4ID Station Hospital | Camp Evangelista</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
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
            background-color: var(--light-gray);
            color: var(--charcoal);
            line-height: 1.5;
        }

        /* ============================================
           Sidebar Navigation
           ============================================ */
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
            background: var(--pale-blue);
            color: var(--soft-blue);
        }

        .nav-link.active {
            background: var(--soft-blue);
            color: white;
        }

        .nav-link.active i {
            color: white;
        }

        /* ============================================
           Main Content
           ============================================ */
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
            background: var(--pale-blue);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--soft-blue);
            font-weight: 600;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 32px;
        }

        .stat-card {
            background: var(--white);
            border-radius: 20px;
            padding: 20px;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border-light);
            transition: all 0.2s ease;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            background: var(--pale-blue);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--soft-blue);
            font-size: 1.5rem;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--dark-gray);
            margin-bottom: 4px;
        }

        .stat-label {
            color: var(--charcoal);
            font-size: 0.8rem;
            opacity: 0.7;
            font-weight: 500;
        }

        /* Recommendation Card */
        .recommendation-card {
            background: linear-gradient(135deg, var(--pale-blue) 0%, var(--white) 100%);
            border-radius: 20px;
            padding: 24px;
            margin-bottom: 32px;
            border: 1px solid var(--border-light);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }

        .recommendation-content {
            display: flex;
            align-items: center;
            gap: 20px;
            flex-wrap: wrap;
        }

        .recommendation-icon {
            width: 60px;
            height: 60px;
            background: var(--soft-green);
            border-radius: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--dark-gray);
            font-size: 1.8rem;
        }

        .recommendation-text h3 {
            color: var(--dark-gray);
            font-size: 1.1rem;
            margin-bottom: 4px;
        }

        .recommendation-text p {
            color: var(--charcoal);
            font-size: 0.85rem;
        }

        .recommendation-badge {
            background: var(--teal);
            color: white;
            padding: 8px 20px;
            border-radius: 40px;
            font-weight: 600;
            font-size: 1.2rem;
        }

        /* Table Panel */
        .panel {
            background: var(--white);
            border-radius: 20px;
            border: 1px solid var(--border-light);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
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

        .panel-body {
            padding: 0;
        }

        /* Table Styles */
        .table-container {
            overflow-x: auto;
        }

        .congestion-table {
            width: 100%;
            border-collapse: collapse;
        }

        .congestion-table th {
            text-align: left;
            padding: 16px 20px;
            background: var(--light-gray);
            font-weight: 600;
            color: var(--dark-gray);
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1px solid var(--border-light);
        }

        .congestion-table td {
            padding: 16px 20px;
            border-bottom: 1px solid var(--border-light);
            color: var(--charcoal);
            font-size: 0.85rem;
            vertical-align: middle;
        }

        .congestion-table tr:hover td {
            background: var(--pale-blue);
        }

        .congestion-table tr:last-child td {
            border-bottom: none;
        }

        /* Rank Badge */
        .rank-badge {
            width: 36px;
            height: 36px;
            background: var(--soft-blue);
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            color: white;
            font-size: 0.9rem;
        }

        .rank-badge.gold {
            background: #F5A623;
        }

        .rank-badge.silver {
            background: #9E9E9E;
        }

        .rank-badge.bronze {
            background: #CD7F32;
        }

        /* Congestion Level Badge */
        .congestion-level {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
        }

        .level-low {
            background: var(--soft-green);
            color: var(--dark-gray);
        }

        .level-medium {
            background: var(--warm-yellow);
            color: var(--dark-gray);
        }

        .level-high {
            background: var(--light-coral);
            color: white;
        }

        /* Progress Bar */
        .progress-container {
            width: 100%;
            min-width: 120px;
        }

        .progress-bar-bg {
            background: var(--light-gray);
            height: 8px;
            border-radius: 4px;
            overflow: hidden;
            margin-bottom: 4px;
        }

        .progress-fill {
            height: 100%;
            border-radius: 4px;
            transition: width 0.3s;
        }

        .progress-fill.low {
            background: var(--soft-green);
        }

        .progress-fill.medium {
            background: var(--warm-yellow);
        }

        .progress-fill.high {
            background: var(--light-coral);
        }

        .progress-percentage {
            font-size: 0.7rem;
            color: var(--charcoal);
            opacity: 0.7;
        }

        /* Action Button */
        .btn-view {
            background: var(--teal);
            color: white;
            border: none;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s;
        }

        .btn-view:hover {
            background: var(--teal-dark);
            transform: translateY(-1px);
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s;
            }
            .main-content {
                margin-left: 0;
                padding: 20px;
            }
            .stats-grid {
                grid-template-columns: 1fr;
            }
            .recommendation-card {
                flex-direction: column;
                text-align: center;
            }
            .recommendation-content {
                justify-content: center;
            }
            .congestion-table th,
            .congestion-table td {
                padding: 12px;
            }
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
            <a href="dashboard.php" class="nav-link">
                <i class="fas fa-tachometer-alt"></i>
                <span>Dashboard</span>
            </a>
        </li>
        <li class="nav-item">
            <a href="patients.php" class="nav-link">
                <i class="fas fa-users"></i>
                <span>Patients</span>
            </a>
        </li>
        <li class="nav-item">
            <a href="queue-monitor.php" class="nav-link">
                <i class="fas fa-chart-line"></i>
                <span>Queue Monitor</span>
            </a>
        </li>
        <li class="nav-item">
            <a href="clinic-congestion.php" class="nav-link active">
                <i class="fas fa-chart-simple"></i>
                <span>Clinic Congestion</span>
            </a>
        </li>
        <li class="nav-item">
            <a href="reports.php" class="nav-link">
                <i class="fas fa-chart-bar"></i>
                <span>Reports</span>
            </a>
        </li>
        <li class="nav-item">
            <a href="users.php" class="nav-link">
                <i class="fas fa-users-cog"></i>
                <span>User Management</span>
            </a>
        </li>
        <li class="nav-item">
            <a href="login-monitor.php" class="nav-link">
                <i class="fas fa-history"></i>
                <span>Login Monitor</span>
            </a>
        </li>
        <li class="nav-item" style="margin-top: 20px; border-top: 1px solid var(--border-light); padding-top: 16px;">
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
            <h1>Clinic Congestion Monitor</h1>
            <p>Real-time clinic loads for smart patient routing</p>
        </div>
        <div class="user-info">
            <div class="date-time">
                <div class="date" id="currentDate"></div>
                <div class="time" id="currentTime"></div>
            </div>
            <div class="user-avatar">
                <i class="fas fa-user-md"></i>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-header">
                <div class="stat-icon">
                    <i class="fas fa-clock"></i>
                </div>
            </div>
            <div class="stat-value"><?php echo number_format($total_waiting); ?></div>
            <div class="stat-label">Total Patients Waiting</div>
        </div>
        <div class="stat-card">
            <div class="stat-header">
                <div class="stat-icon">
                    <i class="fas fa-building"></i>
                </div>
            </div>
            <div class="stat-value"><?php echo number_format(count($enhanced_clinics)); ?></div>
            <div class="stat-label">Active Clinics</div>
        </div>
        <div class="stat-card">
            <div class="stat-header">
                <div class="stat-icon">
                    <i class="fas fa-chart-line"></i>
                </div>
            </div>
            <div class="stat-value"><?php echo $avg_congestion; ?>%</div>
            <div class="stat-label">Average Congestion</div>
        </div>
    </div>

    <!-- Smart Routing Recommendation -->
    <?php if (!empty($enhanced_clinics) && isset($enhanced_clinics[0])): ?>
    <div class="recommendation-card">
        <div class="recommendation-content">
            <div class="recommendation-icon">
                <i class="fas fa-route"></i>
            </div>
            <div class="recommendation-text">
                <h3>Smart Routing Recommendation</h3>
                <p>For optimal patient flow, direct new patients to the least congested clinic</p>
            </div>
        </div>
        <div class="recommendation-badge">
            <i class="fas fa-arrow-right"></i> <?php echo htmlspecialchars($enhanced_clinics[0]['name']); ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Clinics Ranking Table -->
    <div class="panel">
        <div class="panel-header">
            <h3><i class="fas fa-ranking-star"></i> Clinics Ranked by Load (Least Congested First)</h3>
            <span><i class="fas fa-chart-simple"></i> Sorted by current patient load</span>
        </div>
        <div class="panel-body">
            <div class="table-container">
                <table class="congestion-table">
                    <thead>
                        <tr>
                            <th>Rank</th>
                            <th>Clinic</th>
                            <th>Waiting</th>
                            <th>In Progress</th>
                            <th>Capacity</th>
                            <th>Congestion Level</th>
                            <th>Action</th>
                        </thead>
                    <tbody>
                        <?php foreach ($enhanced_clinics as $index => $clinic): 
                            $total_load = isset($clinic['current_load']) ? $clinic['current_load'] : (isset($clinic['waiting_count']) ? $clinic['waiting_count'] : 0);
                            $capacity = $clinic['capacity_per_hour'] ?? 10;
                            $in_progress = $clinic['in_progress'] ?? 0;
                            $waiting_count = isset($clinic['waiting_count']) ? $clinic['waiting_count'] : $total_load;
                            $percentage = min(100, ($total_load / max($capacity, 1)) * 100);
                            
                            if ($percentage < 30) {
                                $level = 'Low';
                                $level_class = 'low';
                            } elseif ($percentage < 60) {
                                $level = 'Medium';
                                $level_class = 'medium';
                            } else {
                                $level = 'High';
                                $level_class = 'high';
                            }
                            
                            // Special rank styling for top 3
                            $rank_class = '';
                            if ($index == 0) $rank_class = 'gold';
                            elseif ($index == 1) $rank_class = 'silver';
                            elseif ($index == 2) $rank_class = 'bronze';
                        ?>
                        <tr>
                            <td>
                                <span class="rank-badge <?php echo $rank_class; ?>">
                                    <?php echo $index + 1; ?>
                                </span>
                             </td>
                            <td>
                                <strong><?php echo htmlspecialchars($clinic['name']); ?></strong>
                                <?php if ($index == 0): ?>
                                    <span style="color: var(--teal); font-size: 0.7rem; margin-left: 8px;">
                                        <i class="fas fa-star"></i> Recommended
                                    </span>
                                <?php endif; ?>
                             </td>
                            <td>
                                <span style="font-weight: 600; color: var(--dark-gray);">
                                    <?php echo $waiting_count; ?>
                                </span>
                                <span style="color: var(--charcoal); opacity: 0.6;"> waiting</span>
                             </td>
                            <td><?php echo $in_progress; ?></td>
                            <td><?php echo $total_load; ?>/<?php echo $capacity; ?></td>
                            <td>
                                <span class="congestion-level level-<?php echo $level_class; ?>">
                                    <?php echo $level; ?>
                                </span>
                                <div class="progress-container">
                                    <div class="progress-bar-bg">
                                        <div class="progress-fill <?php echo $level_class; ?>" style="width: <?php echo $percentage; ?>%"></div>
                                    </div>
                                    <div class="progress-percentage"><?php echo round($percentage); ?>% capacity</div>
                                </div>
                             </td>
                             <td>
                                <a href="../staff/clinic-dashboard.php?clinic_id=<?php echo $clinic['id']; ?>" class="btn-view">
                                    <i class="fas fa-eye"></i> View Queue
                                </a>
                             </td>
                         </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Congestion Legend -->
    <div style="margin-top: 24px; display: flex; gap: 24px; flex-wrap: wrap; justify-content: center;">
        <div style="display: flex; align-items: center; gap: 8px;">
            <div style="width: 16px; height: 16px; background: var(--soft-green); border-radius: 4px;"></div>
            <span style="font-size: 0.75rem; color: var(--charcoal);">Low Congestion (0-30%)</span>
        </div>
        <div style="display: flex; align-items: center; gap: 8px;">
            <div style="width: 16px; height: 16px; background: var(--warm-yellow); border-radius: 4px;"></div>
            <span style="font-size: 0.75rem; color: var(--charcoal);">Medium Congestion (31-60%)</span>
        </div>
        <div style="display: flex; align-items: center; gap: 8px;">
            <div style="width: 16px; height: 16px; background: var(--light-coral); border-radius: 4px;"></div>
            <span style="font-size: 0.75rem; color: var(--charcoal);">High Congestion (61-100%)</span>
        </div>
        <div style="display: flex; align-items: center; gap: 8px;">
            <i class="fas fa-star" style="color: var(--teal); font-size: 0.8rem;"></i>
            <span style="font-size: 0.75rem; color: var(--charcoal);">Recommended Clinic</span>
        </div>
    </div>
</main>

<script>
    // Date and Time Display
    function updateDateTime() {
        const now = new Date();
        const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
        document.getElementById('currentDate').textContent = now.toLocaleDateString('en-US', options);
        document.getElementById('currentTime').textContent = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
    }
    updateDateTime();
    setInterval(updateDateTime, 1000);
    
    // ============================================
    // AUTO-LOGOUT AFTER INACTIVITY
    // ============================================

    // Timeout in milliseconds (30 minutes = 30 * 60 * 1000)
    const INACTIVITY_TIMEOUT = 30 * 60 * 1000;
    let inactivityTimer;
    let warningTimer;
    let warningShown = false;

    function resetInactivityTimer() {
        if (inactivityTimer) clearTimeout(inactivityTimer);
        if (warningTimer) clearTimeout(warningTimer);
        warningShown = false;
        hideWarningModal();
        
        inactivityTimer = setTimeout(logoutUser, INACTIVITY_TIMEOUT);
        warningTimer = setTimeout(showWarningModal, INACTIVITY_TIMEOUT - (2 * 60 * 1000));
        sendHeartbeat();
    }

    function sendHeartbeat() {
        fetch('heartbeat.php', {
            method: 'POST',
            credentials: 'same-origin'
        }).catch(err => console.log('Heartbeat failed:', err));
    }

    function logoutUser() {
        window.location.href = '../logout.php';
    }

    function showWarningModal() {
        if (warningShown) return;
        warningShown = true;
        
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
        
        let secondsLeft = 120;
        const countdownElement = document.getElementById('countdownText');
        const countdownInterval = setInterval(function() {
            secondsLeft--;
            const minutes = Math.floor(secondsLeft / 60);
            const seconds = secondsLeft % 60;
            if (countdownElement) {
                countdownElement.textContent = `${minutes}:${seconds.toString().padStart(2, '0')}`;
            }
            if (secondsLeft <= 0) clearInterval(countdownInterval);
        }, 1000);
    }

    function keepSessionAlive() {
        hideWarningModal();
        fetch('heartbeat.php', {
            method: 'POST',
            credentials: 'same-origin'
        }).then(function() {
            resetInactivityTimer();
        }).catch(function(err) {
            resetInactivityTimer();
        });
    }

    function hideWarningModal() {
        const modal = document.getElementById('sessionWarningModal');
        if (modal) modal.remove();
    }

    // Track user activity
    const events = ['mousedown', 'mousemove', 'keypress', 'scroll', 'touchstart', 'click', 'keydown'];
    events.forEach(function(event) {
        document.addEventListener(event, resetInactivityTimer, false);
    });

    resetInactivityTimer();

    setInterval(function() {
        if (!warningShown) {
            sendHeartbeat();
        }
    }, 5 * 60 * 1000);
</script>

</body>
</html>