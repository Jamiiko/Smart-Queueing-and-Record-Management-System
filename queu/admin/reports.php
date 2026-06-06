<?php
// admin/reports.php - Reports & Analytics
// Camp Evangelista Station Hospital

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/SessionManager.php';

session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: ../index.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Get date range from request
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-d', strtotime('-30 days'));
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');
$report_type = isset($_GET['report_type']) ? $_GET['report_type'] : 'daily';

// Get summary statistics
$query = "SELECT COUNT(DISTINCT patient_id) as total_patients,
                 COUNT(*) as total_visits,
                 AVG(TIMESTAMPDIFF(MINUTE, registered_at, completed_at)) as avg_wait_time,
                 SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed
          FROM queue_entries 
          WHERE DATE(registered_at) BETWEEN ? AND ?";
$stmt = $db->prepare($query);
$stmt->execute([$date_from, $date_to]);
$summary = $stmt->fetch(PDO::FETCH_ASSOC);

// Get priority distribution
$priority_query = "SELECT priority_level, COUNT(*) as count
                   FROM queue_entries
                   WHERE DATE(registered_at) BETWEEN ? AND ?
                   GROUP BY priority_level";
$stmt = $db->prepare($priority_query);
$stmt->execute([$date_from, $date_to]);
$priority_dist = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get clinic performance
$clinic_query = "SELECT 
                    c.name,
                    COUNT(q.id) as total_patients,
                    SUM(CASE WHEN q.status = 'completed' THEN 1 ELSE 0 END) as completed,
                    AVG(TIMESTAMPDIFF(MINUTE, q.registered_at, q.completed_at)) as avg_time,
                    COUNT(DISTINCT q.patient_id) as unique_patients
                 FROM clinics c
                 LEFT JOIN queue_entries q ON c.id = q.clinic_id 
                    AND DATE(q.registered_at) BETWEEN ? AND ?
                 WHERE c.is_active = 1
                 GROUP BY c.id
                 ORDER BY total_patients DESC";
$stmt = $db->prepare($clinic_query);
$stmt->execute([$date_from, $date_to]);
$clinic_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get daily trends
if ($report_type == 'daily') {
    $trend_query = "SELECT 
                        DATE(registered_at) as date,
                        COUNT(*) as total,
                        SUM(CASE WHEN priority_level = 'PR1' THEN 1 ELSE 0 END) as pr1,
                        SUM(CASE WHEN priority_level = 'PR2' THEN 1 ELSE 0 END) as pr2,
                        SUM(CASE WHEN priority_level = 'PR3' THEN 1 ELSE 0 END) as pr3,
                        AVG(TIMESTAMPDIFF(MINUTE, registered_at, completed_at)) as avg_wait
                    FROM queue_entries
                    WHERE DATE(registered_at) BETWEEN ? AND ?
                    GROUP BY DATE(registered_at)
                    ORDER BY date DESC";
} else {
    $trend_query = "SELECT 
                        DATE_FORMAT(registered_at, '%Y-%m') as month,
                        COUNT(*) as total,
                        SUM(CASE WHEN priority_level = 'PR1' THEN 1 ELSE 0 END) as pr1,
                        SUM(CASE WHEN priority_level = 'PR2' THEN 1 ELSE 0 END) as pr2,
                        SUM(CASE WHEN priority_level = 'PR3' THEN 1 ELSE 0 END) as pr3,
                        AVG(TIMESTAMPDIFF(MINUTE, registered_at, completed_at)) as avg_wait
                    FROM queue_entries
                    WHERE DATE(registered_at) BETWEEN ? AND ?
                    GROUP BY DATE_FORMAT(registered_at, '%Y-%m')
                    ORDER BY month DESC";
}

$stmt = $db->prepare($trend_query);
$stmt->execute([$date_from, $date_to]);
$trends = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Prepare data for charts
$priority_labels = [];
$priority_data = [];
foreach ($priority_dist as $p) {
    $priority_labels[] = $p['priority_level'];
    $priority_data[] = $p['count'];
}

$trend_labels = [];
$trend_data = [];
foreach ($trends as $t) {
    $trend_labels[] = $report_type == 'daily' ? date('M d', strtotime($t['date'])) : $t['month'];
    $trend_data[] = $t['total'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports & Analytics | 4ID Station Hospital | Camp Evangelista</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
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
            grid-template-columns: repeat(4, 1fr);
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
            text-align: center;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .stat-icon {
            width: 56px;
            height: 56px;
            background: var(--pale-blue);
            border-radius: 28px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--soft-blue);
            font-size: 1.5rem;
            margin: 0 auto 12px;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--dark-gray);
            margin-bottom: 4px;
        }

        .stat-label {
            color: var(--charcoal);
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Filter Card */
        .filter-card {
            background: var(--white);
            border-radius: 20px;
            border: 1px solid var(--border-light);
            padding: 24px;
            margin-bottom: 32px;
            box-shadow: var(--shadow-sm);
        }

        .filter-form {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            align-items: flex-end;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .filter-group label {
            font-size: 0.7rem;
            font-weight: 600;
            color: var(--charcoal);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .filter-group input,
        .filter-group select {
            padding: 10px 14px;
            border: 1px solid var(--border-light);
            border-radius: 12px;
            font-family: inherit;
            font-size: 0.85rem;
            background: var(--white);
        }

        .filter-group input:focus,
        .filter-group select:focus {
            outline: none;
            border-color: var(--soft-blue);
        }

        .btn-generate {
            background: var(--teal);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 12px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.2s;
        }

        .btn-generate:hover {
            background: var(--teal-dark);
            transform: translateY(-1px);
        }

        .btn-print {
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
        }

        .btn-print:hover {
            background: var(--border-light);
        }

        /* Charts Grid */
        .charts-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 24px;
            margin-bottom: 32px;
        }

        .chart-card {
            background: var(--white);
            border-radius: 20px;
            border: 1px solid var(--border-light);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
        }

        .chart-header {
            padding: 18px 24px;
            border-bottom: 1px solid var(--border-light);
            background: var(--white);
        }

        .chart-header h3 {
            color: var(--dark-gray);
            font-size: 1rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .chart-header h3 i {
            color: var(--soft-blue);
        }

        .chart-body {
            padding: 20px;
        }

        canvas {
            max-height: 300px;
            width: 100%;
        }

        /* Clinic Performance Table */
        .performance-card {
            background: var(--white);
            border-radius: 20px;
            border: 1px solid var(--border-light);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
            margin-bottom: 32px;
        }

        .performance-header {
            padding: 18px 24px;
            border-bottom: 1px solid var(--border-light);
            background: var(--white);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
        }

        .performance-header h3 {
            color: var(--dark-gray);
            font-size: 1rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .performance-header h3 i {
            color: var(--soft-blue);
        }

        .table-container {
            overflow-x: auto;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
        }

        .data-table th {
            text-align: left;
            padding: 14px 16px;
            background: var(--light-gray);
            font-weight: 600;
            color: var(--dark-gray);
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1px solid var(--border-light);
        }

        .data-table td {
            padding: 12px 16px;
            border-bottom: 1px solid var(--border-light);
            color: var(--charcoal);
            font-size: 0.85rem;
        }

        .data-table tr:hover td {
            background: var(--pale-blue);
        }

        .trend-table {
            width: 100%;
            border-collapse: collapse;
        }

        .trend-table th {
            text-align: left;
            padding: 12px 16px;
            background: var(--light-gray);
            font-weight: 600;
            color: var(--dark-gray);
            font-size: 0.7rem;
            text-transform: uppercase;
        }

        .trend-table td {
            padding: 10px 16px;
            border-bottom: 1px solid var(--border-light);
            font-size: 0.8rem;
        }

        .badge-success {
            background: var(--soft-green);
            color: var(--dark-gray);
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.7rem;
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            .charts-grid {
                grid-template-columns: 1fr;
            }
            .filter-form {
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
            .filter-form {
                grid-template-columns: 1fr;
            }
            .top-bar {
                flex-direction: column;
                align-items: flex-start;
                gap: 16px;
            }
        }

        @media print {
            .sidebar, .top-bar, .filter-card, .btn-print, .nav-menu {
                display: none;
            }
            .main-content {
                margin-left: 0;
                padding: 0;
            }
            .stat-card, .chart-card, .performance-card {
                break-inside: avoid;
                page-break-inside: avoid;
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
            <a href="clinic-congestion.php" class="nav-link">
                <i class="fas fa-chart-simple"></i>
                <span>Clinic Congestion</span>
            </a>
        </li>
        <li class="nav-item">
            <a href="reports.php" class="nav-link active">
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
            <h1>Reports & Analytics</h1>
            <p>Comprehensive analytics and performance insights</p>
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

    <!-- Filter Card -->
    <div class="filter-card">
        <form method="GET" class="filter-form">
            <div class="filter-group">
                <label><i class="fas fa-calendar-alt"></i> Date From</label>
                <input type="date" name="date_from" value="<?php echo $date_from; ?>">
            </div>
            <div class="filter-group">
                <label><i class="fas fa-calendar-alt"></i> Date To</label>
                <input type="date" name="date_to" value="<?php echo $date_to; ?>">
            </div>
            <div class="filter-group">
                <label><i class="fas fa-chart-line"></i> Report Type</label>
                <select name="report_type">
                    <option value="daily" <?php echo $report_type == 'daily' ? 'selected' : ''; ?>>Daily</option>
                    <option value="monthly" <?php echo $report_type == 'monthly' ? 'selected' : ''; ?>>Monthly</option>
                </select>
            </div>
            <div class="filter-group">
                <button type="submit" class="btn-generate">
                    <i class="fas fa-chart-simple"></i> Generate Report
                </button>
            </div>
        </form>
    </div>

    <!-- Statistics Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-users"></i>
            </div>
            <div class="stat-value"><?php echo number_format($summary['total_patients'] ?? 0); ?></div>
            <div class="stat-label">Unique Patients</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-calendar-check"></i>
            </div>
            <div class="stat-value"><?php echo number_format($summary['total_visits'] ?? 0); ?></div>
            <div class="stat-label">Total Visits</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-clock"></i>
            </div>
            <div class="stat-value"><?php echo round($summary['avg_wait_time'] ?? 0); ?> min</div>
            <div class="stat-label">Average Wait Time</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-value"><?php echo number_format($summary['completed'] ?? 0); ?></div>
            <div class="stat-label">Completed Visits</div>
        </div>
    </div>

    <!-- Charts Grid -->
    <div class="charts-grid">
        <!-- Priority Distribution Chart -->
        <div class="chart-card">
            <div class="chart-header">
                <h3><i class="fas fa-chart-pie"></i> Priority Distribution</h3>
            </div>
            <div class="chart-body">
                <canvas id="priorityChart"></canvas>
            </div>
        </div>

        <!-- Trend Chart -->
        <div class="chart-card">
            <div class="chart-header">
                <h3><i class="fas fa-chart-line"></i> Patient Volume Trend</h3>
            </div>
            <div class="chart-body">
                <canvas id="trendChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Clinic Performance Table -->
    <div class="performance-card">
        <div class="performance-header">
            <h3><i class="fas fa-hospital-user"></i> Clinic Performance</h3>
            <button onclick="window.print()" class="btn-print">
                <i class="fas fa-print"></i> Print Report
            </button>
        </div>
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Clinic</th>
                        <th>Total Patients</th>
                        <th>Unique Patients</th>
                        <th>Completed</th>
                        <th>Completion Rate</th>
                        <th>Avg Time (min)</th>
                    </thead>
                <tbody>
                    <?php foreach ($clinic_stats as $clinic): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($clinic['name']); ?></strong></td>
                        <td><?php echo $clinic['total_patients'] ?? 0; ?></td>
                        <td><?php echo $clinic['unique_patients'] ?? 0; ?></td>
                        <td><?php echo $clinic['completed'] ?? 0; ?></td>
                        <td>
                            <?php 
                            $rate = ($clinic['total_patients'] > 0) ? round(($clinic['completed'] / $clinic['total_patients']) * 100) : 0;
                            ?>
                            <span class="badge-success"><?php echo $rate; ?>%</span>
                        </td>
                        <td><?php echo round($clinic['avg_time'] ?? 0); ?> min</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Trend Data Table -->
    <div class="performance-card">
        <div class="performance-header">
            <h3><i class="fas fa-table-list"></i> <?php echo $report_type == 'daily' ? 'Daily' : 'Monthly'; ?> Breakdown</h3>
        </div>
        <div class="table-container">
            <table class="trend-table">
                <thead>
                    <tr>
                        <th><?php echo $report_type == 'daily' ? 'Date' : 'Month'; ?></th>
                        <th>Total Visits</th>
                        <th>PR1 (Military)</th>
                        <th>PR2 (Priority)</th>
                        <th>PR3 (Regular)</th>
                        <th>Avg Wait (min)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($trends as $trend): ?>
                    <tr>
                        <td><strong><?php echo $report_type == 'daily' ? date('M d, Y', strtotime($trend['date'])) : $trend['month']; ?></strong></td>
                        <td><?php echo $trend['total']; ?></td>
                        <td><?php echo $trend['pr1']; ?></td>
                        <td><?php echo $trend['pr2']; ?></td>
                        <td><?php echo $trend['pr3']; ?></td>
                        <td><?php echo round($trend['avg_wait'] ?? 0); ?> min</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
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

    // Priority Distribution Chart
    const priorityCtx = document.getElementById('priorityChart').getContext('2d');
    new Chart(priorityCtx, {
        type: 'doughnut',
        data: {
            labels: <?php echo json_encode($priority_labels); ?>,
            datasets: [{
                data: <?php echo json_encode($priority_data); ?>,
                backgroundColor: ['#FF6F61', '#FFB84D', '#A4D1B1'],
                borderWidth: 0,
                hoverOffset: 10
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        font: { family: 'Inter', size: 11 },
                        usePointStyle: true,
                        boxWidth: 10
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const label = context.label || '';
                            const value = context.raw || 0;
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = total > 0 ? Math.round((value / total) * 100) : 0;
                            return `${label}: ${value} (${percentage}%)`;
                        }
                    }
                }
            }
        }
    });

    // Trend Chart
    const trendCtx = document.getElementById('trendChart').getContext('2d');
    new Chart(trendCtx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($trend_labels); ?>,
            datasets: [{
                label: 'Patient Volume',
                data: <?php echo json_encode($trend_data); ?>,
                borderColor: '#4A90E2',
                backgroundColor: 'rgba(74, 144, 226, 0.1)',
                tension: 0.3,
                fill: true,
                pointBackgroundColor: '#4A90E2',
                pointBorderColor: '#fff',
                pointRadius: 4,
                pointHoverRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return `Patients: ${context.raw}`;
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        color: '#E5E9F0'
                    },
                    ticks: {
                        stepSize: 1,
                        font: { size: 10 }
                    }
                },
                x: {
                    grid: {
                        display: false
                    },
                    ticks: {
                        font: { size: 10 },
                        maxRotation: 45,
                        minRotation: 45
                    }
                }
            }
        }
    });
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