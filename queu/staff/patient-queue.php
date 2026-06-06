<?php
// staff/patient-queue.php - All Clinics Queue Overview
// Camp Evangelista Station Hospital

require_once dirname(__DIR__) . '/config/database.php';

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

// ============================================
// SESSION TIMEOUT CHECK (After $db exists)
// ============================================
require_once dirname(__DIR__) . '/includes/SessionManager.php';
$sessionManager = new SessionManager($db);
if (!$sessionManager->checkTimeout()) {
    exit(); // Already redirected to login
}
$sessionManager->logActivity('Viewed patient queue page');

// Get all clinics with queue counts
$query = "SELECT 
            c.*,
            COUNT(CASE WHEN q.status IN ('waiting', 'called', 'in-progress') 
                  AND DATE(q.registered_at) = CURDATE() THEN 1 END) as current_patients,
            COUNT(CASE WHEN q.status = 'waiting' 
                  AND DATE(q.registered_at) = CURDATE() THEN 1 END) as waiting_count,
            COUNT(CASE WHEN q.status = 'in-progress' 
                  AND DATE(q.registered_at) = CURDATE() THEN 1 END) as in_progress_count,
            COUNT(CASE WHEN q.status = 'completed' 
                  AND DATE(q.registered_at) = CURDATE() THEN 1 END) as completed_today,
            AVG(CASE WHEN q.completed_at IS NOT NULL AND DATE(q.registered_at) = CURDATE() 
                THEN TIMESTAMPDIFF(MINUTE, q.registered_at, q.completed_at) END) as avg_wait_time
          FROM clinics c
          LEFT JOIN queue_entries q ON c.id = q.clinic_id
          WHERE c.is_active = 1
          GROUP BY c.id
          ORDER BY c.name";

$clinics = $db->query($query)->fetchAll(PDO::FETCH_ASSOC);

// Calculate totals
$total_waiting = array_sum(array_column($clinics, 'waiting_count'));
$total_active = array_sum(array_column($clinics, 'in_progress_count'));
$total_completed = array_sum(array_column($clinics, 'completed_today'));
$total_patients = $total_waiting + $total_active + $total_completed;
$avg_wait = round(array_sum(array_column($clinics, 'avg_wait_time')) / max(count($clinics), 1));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clinic Queues | Staff | Camp Evangelista Hospital</title>
    
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
            position: relative;
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
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 32px;
        }

        .stat-card {
            background: var(--white);
            border-radius: 20px;
            padding: 20px 16px;
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
            font-size: 1.8rem;
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

        /* Clinic Grid */
        .clinic-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
            gap: 24px;
            margin-bottom: 32px;
        }

        .clinic-card {
            background: var(--white);
            border-radius: 20px;
            border: 1px solid var(--border-light);
            overflow: hidden;
            transition: all 0.2s ease;
            box-shadow: var(--shadow-sm);
            cursor: pointer;
            text-decoration: none;
            display: block;
            color: inherit;
        }

        .clinic-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
            border-color: var(--soft-blue-light);
        }

        .clinic-header {
            background: var(--light-gray);
            padding: 20px 20px;
            border-bottom: 1px solid var(--border-light);
            position: relative;
        }

        .clinic-name {
            font-weight: 700;
            color: var(--dark-gray);
            font-size: 1.1rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .clinic-icon {
            position: absolute;
            right: 20px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 2.5rem;
            color: var(--soft-blue-light);
            opacity: 0.6;
        }

        .clinic-description {
            font-size: 0.75rem;
            color: var(--charcoal);
            margin-top: 8px;
            opacity: 0.7;
        }

        .clinic-stats {
            display: flex;
            justify-content: space-between;
            padding: 20px;
            text-align: center;
        }

        .stat-item {
            flex: 1;
            text-align: center;
        }

        .stat-number {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--dark-gray);
        }

        .stat-number.small {
            font-size: 1.2rem;
        }

        .stat-number.warning {
            color: var(--warm-yellow);
        }

        .stat-number.danger {
            color: var(--light-coral);
        }

        .stat-number.success {
            color: var(--soft-green);
        }

        .stat-number.primary {
            color: var(--soft-blue);
        }

        .stat-label-small {
            font-size: 0.65rem;
            color: var(--charcoal);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .progress-section {
            padding: 0 20px 16px 20px;
        }

        .progress-bar-bg {
            background: var(--light-gray);
            height: 6px;
            border-radius: 3px;
            overflow: hidden;
            margin-top: 8px;
        }

        .progress-fill {
            height: 100%;
            border-radius: 3px;
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

        .clinic-footer {
            background: var(--light-gray);
            padding: 12px 20px;
            border-top: 1px solid var(--border-light);
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.7rem;
        }

        .view-link {
            color: var(--soft-blue);
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        /* Action Button */
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
            text-decoration: none;
        }

        .btn-primary:hover {
            background: var(--teal-dark);
            transform: translateY(-1px);
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px;
            background: var(--white);
            border-radius: 20px;
            border: 1px solid var(--border-light);
            color: var(--charcoal);
            opacity: 0.6;
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 16px;
            color: var(--soft-blue);
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
            .clinic-grid {
                grid-template-columns: 1fr;
            }
            .top-bar {
                flex-direction: column;
                align-items: flex-start;
                gap: 16px;
            }
            .clinic-stats {
                flex-wrap: wrap;
                gap: 12px;
            }
            .stat-item {
                min-width: 80px;
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
            <a href="clinic-dashboard.php?clinic_id=<?php echo $_SESSION['clinic_id'] ?? 1; ?>" class="nav-link">
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
            <a href="patient-queue.php" class="nav-link active">
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
            <h1>Clinic Queues Overview</h1>
            <p>View and manage all clinic queues from one dashboard</p>
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

    <!-- Hospital Summary Stats -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-users"></i></div>
            <div class="stat-value"><?php echo $total_patients; ?></div>
            <div class="stat-label">Total Patients Today</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-clock"></i></div>
            <div class="stat-value"><?php echo $total_waiting; ?></div>
            <div class="stat-label">Currently Waiting</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-play-circle"></i></div>
            <div class="stat-value"><?php echo $total_active; ?></div>
            <div class="stat-label">In Progress</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
            <div class="stat-value"><?php echo $total_completed; ?></div>
            <div class="stat-label">Completed</div>
        </div>
    </div>

    <!-- Clinic Cards Grid -->
    <?php if (empty($clinics)): ?>
        <div class="empty-state">
            <i class="fas fa-clinic-medical"></i>
            <p>No active clinics found</p>
            <small>Please check clinic configuration</small>
        </div>
    <?php else: ?>
        <div class="clinic-grid">
            <?php foreach ($clinics as $clinic): 
                $waiting = $clinic['waiting_count'] ?? 0;
                $in_progress = $clinic['in_progress_count'] ?? 0;
                $completed = $clinic['completed_today'] ?? 0;
                $capacity = $clinic['capacity_per_hour'] ?? 10;
                $current_load = $waiting + $in_progress;
                $load_percentage = min(100, ($current_load / max($capacity, 1)) * 100);
                
                if ($load_percentage < 30) $load_class = 'low';
                elseif ($load_percentage < 60) $load_class = 'medium';
                else $load_class = 'high';
                
                $avg_wait = round($clinic['avg_wait_time'] ?? 0);
            ?>
            <a href="clinic-dashboard.php?clinic_id=<?php echo $clinic['id']; ?>" class="clinic-card">
                <div class="clinic-header">
                    <div class="clinic-name"><?php echo htmlspecialchars($clinic['name']); ?></div>
                    <div class="clinic-icon">
                        <i class="fas fa-hospital-user"></i>
                    </div>
                    <div class="clinic-description"><?php echo htmlspecialchars($clinic['description'] ?? 'Medical services'); ?></div>
                </div>
                
                <div class="clinic-stats">
                    <div class="stat-item">
                        <div class="stat-number <?php echo $waiting > 10 ? 'danger' : ($waiting > 5 ? 'warning' : ''); ?>">
                            <?php echo $waiting; ?>
                        </div>
                        <div class="stat-label-small">Waiting</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number primary"><?php echo $in_progress; ?></div>
                        <div class="stat-label-small">In Progress</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number success"><?php echo $completed; ?></div>
                        <div class="stat-label-small">Completed</div>
                    </div>
                </div>
                
                <div class="progress-section">
                    <div style="display: flex; justify-content: space-between; font-size: 0.7rem;">
                        <span>Capacity</span>
                        <span><?php echo round($load_percentage); ?>%</span>
                    </div>
                    <div class="progress-bar-bg">
                        <div class="progress-fill <?php echo $load_class; ?>" style="width: <?php echo $load_percentage; ?>%"></div>
                    </div>
                </div>
                
                <div class="clinic-footer">
                    <div>
                        <i class="fas fa-hourglass-half"></i> Est. wait: <?php echo $waiting * 8; ?> min
                    </div>
                    <div>
                        <i class="fas fa-chart-line"></i> Avg: <?php echo $avg_wait; ?> min
                    </div>
                    <div class="view-link">
                        View Queue <i class="fas fa-arrow-right"></i>
                    </div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Quick Stats Card -->
    <div style="background: var(--white); border-radius: 20px; border: 1px solid var(--border-light); overflow: hidden; margin-top: 16px;">
        <div style="padding: 18px 24px; border-bottom: 1px solid var(--border-light); background: var(--white);">
            <h3 style="color: var(--dark-gray); font-size: 1rem; font-weight: 600; display: flex; align-items: center; gap: 8px;">
                <i class="fas fa-chart-bar" style="color: var(--soft-blue);"></i>
                Hospital Summary
            </h3>
        </div>
        <div style="padding: 24px;">
            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 24px; text-align: center;">
                <div>
                    <div style="font-size: 2rem; font-weight: 700; color: var(--dark-gray);"><?php echo $total_patients; ?></div>
                    <div style="font-size: 0.7rem; color: var(--charcoal); text-transform: uppercase;">Total Patients Today</div>
                </div>
                <div>
                    <div style="font-size: 2rem; font-weight: 700; color: var(--warm-yellow);"><?php echo $total_waiting; ?></div>
                    <div style="font-size: 0.7rem; color: var(--charcoal); text-transform: uppercase;">Currently Waiting</div>
                </div>
                <div>
                    <div style="font-size: 2rem; font-weight: 700; color: var(--soft-green);"><?php echo $total_completed; ?></div>
                    <div style="font-size: 0.7rem; color: var(--charcoal); text-transform: uppercase;">Completed</div>
                </div>
            </div>
            <div style="margin-top: 20px; text-align: center;">
                <div class="progress-bar-bg" style="height: 8px; margin-bottom: 8px;">
                    <div class="progress-fill" style="width: <?php echo $total_patients > 0 ? ($total_completed / $total_patients) * 100 : 0; ?>%; background: var(--teal);"></div>
                </div>
                <div style="font-size: 0.7rem; color: var(--charcoal);">Completion Rate: <?php echo $total_patients > 0 ? round(($total_completed / $total_patients) * 100) : 0; ?>%</div>
            </div>
        </div>
    </div>
</main>

<script>
    // Date and Time Display
    function updateDateTime() {
        const now = new Date();
        document.getElementById('currentDate').textContent = now.toLocaleDateString('en-US', { 
            weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' 
        });
        document.getElementById('currentTime').textContent = now.toLocaleTimeString('en-US', { 
            hour: '2-digit', minute: '2-digit' 
        });
    }
    updateDateTime();
    setInterval(updateDateTime, 1000);

    // Dropdown Menu
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
</script>
</body>
</html>