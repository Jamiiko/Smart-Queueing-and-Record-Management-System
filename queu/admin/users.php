<?php
// admin/users.php - User Management (Enhanced)
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

// ============================================
// FIRST: Add missing columns if not exists
// ============================================
try {
    $db->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS `email` varchar(100) DEFAULT NULL");
    $db->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS `is_active` tinyint(1) DEFAULT 1");
} catch (PDOException $e) {
    // Columns might already exist, ignore error
}

// ============================================
// HANDLE USER ACTIONS
// ============================================

// Handle user deletion
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    if ($id != 1) { // Don't delete main admin
        $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $message = "User deleted successfully!";
    }
    header('Location: users.php');
    exit();
}

// Handle user activation
if (isset($_GET['activate'])) {
    $id = (int)$_GET['activate'];
    $stmt = $db->prepare("UPDATE users SET is_active = 1 WHERE id = ?");
    $stmt->execute([$id]);
    $message = "User activated successfully!";
    header('Location: users.php');
    exit();
}

// Handle user deactivation
if (isset($_GET['deactivate'])) {
    $id = (int)$_GET['deactivate'];
    if ($id != 1) { // Can't deactivate main admin
        $stmt = $db->prepare("UPDATE users SET is_active = 0 WHERE id = ?");
        $stmt->execute([$id]);
        $message = "User deactivated successfully!";
    }
    header('Location: users.php');
    exit();
}

// Handle user addition
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_user'])) {
    $username = trim($_POST['username']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email'] ?? '');
    $role = $_POST['role'];
    $clinic_id = !empty($_POST['clinic_id']) ? $_POST['clinic_id'] : null;
    $is_active = isset($_POST['is_active']) ? 1 : 1; // Default active
    
    $query = "INSERT INTO users (username, password, full_name, email, role, clinic_id, is_active) 
              VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt = $db->prepare($query);
    $stmt->execute([$username, $password, $full_name, $email, $role, $clinic_id, $is_active]);
    
    $message = "User added successfully!";
    header('Location: users.php');
    exit();
}

// Handle user edit
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_user'])) {
    $id = (int)$_POST['user_id'];
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email'] ?? '');
    $role = $_POST['role'];
    $clinic_id = !empty($_POST['clinic_id']) ? $_POST['clinic_id'] : null;
    
    $query = "UPDATE users SET full_name = ?, email = ?, role = ?, clinic_id = ? WHERE id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$full_name, $email, $role, $clinic_id, $id]);
    
    $message = "User updated successfully!";
    header('Location: users.php');
    exit();
}

// Handle password reset
if (isset($_GET['reset_password'])) {
    $id = (int)$_GET['reset_password'];
    if ($id != 1) { // Can't reset main admin password here
        $new_password = password_hash('clinic123', PASSWORD_DEFAULT);
        $stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->execute([$new_password, $id]);
        $message = "Password reset to 'clinic123' for user ID: $id";
    }
    header('Location: users.php');
    exit();
}

// ============================================
// GET SEARCH/FILTER PARAMETERS
// ============================================
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$role_filter = isset($_GET['role_filter']) ? $_GET['role_filter'] : '';
$status_filter = isset($_GET['status_filter']) ? $_GET['status_filter'] : '';

// Build query with filters
$query = "SELECT u.*, c.name as clinic_name 
          FROM users u
          LEFT JOIN clinics c ON u.clinic_id = c.id
          WHERE 1=1";
$params = [];

if (!empty($search)) {
    $query .= " AND (u.username LIKE ? OR u.full_name LIKE ? OR u.email LIKE ?)";
    $search_term = "%$search%";
    $params = array_merge($params, [$search_term, $search_term, $search_term]);
}

if (!empty($role_filter)) {
    $query .= " AND u.role = ?";
    $params[] = $role_filter;
}

if (!empty($status_filter)) {
    $is_active = ($status_filter == 'active') ? 1 : 0;
    $query .= " AND u.is_active = ?";
    $params[] = $is_active;
}

$query .= " ORDER BY u.id";
$stmt = $db->prepare($query);
$stmt->execute($params);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all clinics for dropdowns
$clinics = $db->query("SELECT * FROM clinics WHERE is_active = 1 ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$total_users = count($users);
$total_active = 0;
$total_inactive = 0;
$admin_count = 0;
$doctor_count = 0;
$nurse_count = 0;
$technician_count = 0;
$staff_count = 0;

foreach ($users as $user) {
    if ($user['is_active']) $total_active++;
    else $total_inactive++;
    
    switch ($user['role']) {
        case 'admin': $admin_count++; break;
        case 'doctor': $doctor_count++; break;
        case 'nurse': $nurse_count++; break;
        case 'technician': $technician_count++; break;
        case 'staff': $staff_count++; break;
    }
}

// Get user for edit modal (if edit parameter is set)
$edit_user = null;
if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$id]);
    $edit_user = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management | 4ID Station Hospital | Camp Evangelista</title>
    
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
        body { font-family: 'Inter', sans-serif; background-color: var(--light-gray); color: var(--charcoal); line-height: 1.5; }

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
        .nav-link { display: flex; align-items: center; gap: 12px; padding: 12px 16px; border-radius: 12px; color: var(--charcoal); text-decoration: none; font-weight: 500; transition: all 0.2s ease; }
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

        /* Stats Grid */
        .stats-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 12px; margin-bottom: 32px; }
        .stat-card { background: var(--white); border-radius: 16px; padding: 16px 12px; box-shadow: var(--shadow-sm); border: 1px solid var(--border-light); text-align: center; }
        .stat-card:hover { transform: translateY(-2px); box-shadow: var(--shadow-md); }
        .stat-icon { width: 40px; height: 40px; background: var(--soft-blue-light); border-radius: 12px; display: flex; align-items: center; justify-content: center; color: var(--soft-blue); font-size: 1.2rem; margin: 0 auto 10px; }
        .stat-value { font-size: 1.5rem; font-weight: 700; color: var(--dark-gray); }
        .stat-label { color: var(--charcoal); font-size: 0.65rem; text-transform: uppercase; }

        /* Panels */
        .panel { background: var(--white); border-radius: 20px; border: 1px solid var(--border-light); overflow: hidden; box-shadow: var(--shadow-sm); margin-bottom: 24px; }
        .panel-header { padding: 18px 24px; border-bottom: 1px solid var(--border-light); background: var(--white); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; }
        .panel-header h3 { color: var(--dark-gray); font-size: 1rem; font-weight: 600; display: flex; align-items: center; gap: 8px; }
        .panel-header h3 i { color: var(--soft-blue); }
        .panel-body { padding: 24px; }

        /* Forms */
        .user-form { display: grid; grid-template-columns: repeat(6, 1fr); gap: 12px; align-items: flex-end; }
        .form-group { display: flex; flex-direction: column; gap: 6px; }
        .form-group label { font-size: 0.7rem; font-weight: 600; color: var(--charcoal); text-transform: uppercase; }
        .form-group input, .form-group select { padding: 10px 14px; border: 1px solid var(--border-light); border-radius: 12px; font-family: inherit; font-size: 0.85rem; background: var(--white); }
        .form-group input:focus, .form-group select:focus { outline: none; border-color: var(--soft-blue); }
        .btn-add { background: var(--teal); color: white; border: none; padding: 10px 20px; border-radius: 12px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; gap: 8px; height: 42px; }
        .btn-add:hover { background: var(--teal-dark); transform: translateY(-1px); }

        /* Filters */
        .filter-bar { display: flex; gap: 12px; align-items: center; flex-wrap: wrap; }
        .search-box { display: flex; align-items: center; background: var(--light-gray); border-radius: 40px; padding: 8px 16px; gap: 8px; border: 1px solid var(--border-light); }
        .search-box input { border: none; background: transparent; outline: none; font-size: 0.85rem; width: 200px; }
        .filter-select { padding: 8px 16px; border: 1px solid var(--border-light); border-radius: 40px; font-size: 0.85rem; background: var(--light-gray); }
        .btn-filter { background: var(--teal); color: white; border: none; padding: 8px 20px; border-radius: 40px; cursor: pointer; }
        .btn-clear { background: var(--light-gray); color: var(--charcoal); border: 1px solid var(--border-light); padding: 8px 20px; border-radius: 40px; cursor: pointer; text-decoration: none; }

        /* Table */
        .table-container { overflow-x: auto; }
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th { text-align: left; padding: 14px 12px; background: var(--light-gray); font-weight: 600; color: var(--dark-gray); font-size: 0.7rem; text-transform: uppercase; border-bottom: 1px solid var(--border-light); }
        .data-table td { padding: 14px 12px; border-bottom: 1px solid var(--border-light); color: var(--charcoal); font-size: 0.85rem; vertical-align: middle; }
        .data-table tr:hover td { background: var(--soft-blue-light); }

        /* Badges */
        .role-badge { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 0.7rem; font-weight: 600; }
        .role-admin { background: var(--light-coral); color: white; }
        .role-doctor { background: var(--soft-blue); color: white; }
        .role-nurse { background: var(--soft-green); color: var(--dark-gray); }
        .role-technician { background: var(--warm-yellow); color: var(--dark-gray); }
        .role-staff { background: var(--soft-blue-light); color: var(--soft-blue); }
        
        .status-badge { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 0.7rem; font-weight: 600; }
        .status-active { background: var(--soft-green); color: var(--dark-gray); }
        .status-inactive { background: var(--light-gray); color: var(--charcoal); }
        
        .clinic-badge { background: var(--light-gray); padding: 4px 10px; border-radius: 20px; font-size: 0.7rem; }

        /* Buttons */
        .action-buttons { display: flex; gap: 6px; flex-wrap: wrap; }
        .btn-icon { padding: 6px 10px; border-radius: 8px; font-size: 0.7rem; text-decoration: none; display: inline-flex; align-items: center; gap: 4px; cursor: pointer; border: none; }
        .btn-edit { background: var(--warm-yellow); color: var(--dark-gray); }
        .btn-delete { background: var(--light-coral); color: white; }
        .btn-activate { background: var(--soft-green); color: var(--dark-gray); }
        .btn-deactivate { background: var(--warm-yellow); color: var(--dark-gray); }
        .btn-reset { background: var(--soft-blue-light); color: var(--soft-blue); }

        /* Modal */
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 2000; align-items: center; justify-content: center; }
        .modal-content { background: white; border-radius: 24px; width: 90%; max-width: 500px; max-height: 90vh; overflow-y: auto; }
        .modal-header { padding: 20px 24px; border-bottom: 1px solid var(--border-light); display: flex; justify-content: space-between; align-items: center; }
        .modal-body { padding: 24px; }
        .modal-actions { display: flex; justify-content: flex-end; gap: 12px; margin-top: 24px; }
        .btn-save { background: var(--teal); color: white; border: none; padding: 10px 20px; border-radius: 12px; cursor: pointer; }
        .btn-cancel { background: var(--light-gray); border: 1px solid var(--border-light); padding: 10px 20px; border-radius: 12px; cursor: pointer; }
        .close { font-size: 24px; cursor: pointer; color: var(--charcoal); }

        .alert { padding: 14px 20px; border-radius: 16px; margin-bottom: 24px; display: flex; align-items: center; gap: 12px; background: var(--soft-green); color: var(--dark-gray); border-left: 3px solid var(--teal); }
        .empty-state { text-align: center; padding: 48px; color: var(--charcoal); opacity: 0.6; }
        .empty-state i { font-size: 3rem; margin-bottom: 16px; color: var(--soft-blue); }

        @media (max-width: 1024px) { .stats-grid { grid-template-columns: repeat(4, 1fr); } .user-form { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 768px) { .sidebar { transform: translateX(-100%); } .main-content { margin-left: 0; padding: 20px; } .stats-grid { grid-template-columns: repeat(2, 1fr); } .user-form { grid-template-columns: 1fr; } .top-bar { flex-direction: column; align-items: flex-start; gap: 16px; } }
    </style>
</head>
<body>

<!-- Sidebar -->
<aside class="sidebar">
    <div class="sidebar-logo"><h2>4ID Station Hospital</h2><p>Camp Evangelista</p></div>
    <ul class="nav-menu">
        <li class="nav-item"><a href="dashboard.php" class="nav-link"><i class="fas fa-tachometer-alt"></i><span>Dashboard</span></a></li>
        <li class="nav-item"><a href="patients.php" class="nav-link"><i class="fas fa-users"></i><span>Patients</span></a></li>
        <li class="nav-item"><a href="queue-monitor.php" class="nav-link"><i class="fas fa-chart-line"></i><span>Queue Monitor</span></a></li>
        <li class="nav-item"><a href="clinic-congestion.php" class="nav-link"><i class="fas fa-chart-simple"></i><span>Clinic Congestion</span></a></li>
        <li class="nav-item"><a href="reports.php" class="nav-link"><i class="fas fa-chart-bar"></i><span>Reports</span></a></li>
        <li class="nav-item"><a href="users.php" class="nav-link active"><i class="fas fa-users-cog"></i><span>User Management</span></a></li>
        <li class="nav-item"><a href="login-monitor.php" class="nav-link"><i class="fas fa-history"></i><span>Login Monitor</span></a></li>
        <li class="nav-item" style="margin-top: 20px; border-top: 1px solid var(--border-light); padding-top: 16px;">
            <a href="../logout.php" class="nav-link" style="color: var(--light-coral);" onclick="return confirm('Logout?')"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a>
        </li>
    </ul>
</aside>

<!-- Main Content -->
<main class="main-content">
    <div class="top-bar">
        <div class="page-title"><h1>User Management</h1><p>Manage system users, roles, and account status</p></div>
        <div class="user-info">
            <div class="date-time"><div class="date" id="currentDate"></div><div class="time" id="currentTime"></div></div>
            <div class="user-avatar"><i class="fas fa-user-shield"></i></div>
        </div>
    </div>

    <!-- Statistics -->
    <div class="stats-grid">
        <div class="stat-card"><div class="stat-icon"><i class="fas fa-users"></i></div><div class="stat-value"><?php echo $total_users; ?></div><div class="stat-label">Total</div></div>
        <div class="stat-card"><div class="stat-icon"><i class="fas fa-check-circle"></i></div><div class="stat-value"><?php echo $total_active; ?></div><div class="stat-label">Active</div></div>
        <div class="stat-card"><div class="stat-icon"><i class="fas fa-ban"></i></div><div class="stat-value"><?php echo $total_inactive; ?></div><div class="stat-label">Inactive</div></div>
        <div class="stat-card"><div class="stat-icon"><i class="fas fa-crown"></i></div><div class="stat-value"><?php echo $admin_count; ?></div><div class="stat-label">Admin</div></div>
        <div class="stat-card"><div class="stat-icon"><i class="fas fa-stethoscope"></i></div><div class="stat-value"><?php echo $doctor_count; ?></div><div class="stat-label">Doctor</div></div>
        <div class="stat-card"><div class="stat-icon"><i class="fas fa-heartbeat"></i></div><div class="stat-value"><?php echo $nurse_count; ?></div><div class="stat-label">Nurse</div></div>
        <div class="stat-card"><div class="stat-icon"><i class="fas fa-flask"></i></div><div class="stat-value"><?php echo $technician_count + $staff_count; ?></div><div class="stat-label">Tech/Staff</div></div>
    </div>

    <?php if (isset($message)): ?>
        <div class="alert"><i class="fas fa-check-circle"></i> <?php echo $message; ?></div>
    <?php endif; ?>

    <!-- Add User Panel -->
    <div class="panel">
        <div class="panel-header"><h3><i class="fas fa-user-plus"></i> Add New User</h3></div>
        <div class="panel-body">
            <form method="POST" class="user-form">
                <div class="form-group"><label>Username</label><input type="text" name="username" placeholder="Username" required></div>
                <div class="form-group"><label>Password</label><input type="password" name="password" placeholder="Password" required></div>
                <div class="form-group"><label>Full Name</label><input type="text" name="full_name" placeholder="Full name" required></div>
                <div class="form-group"><label>Email</label><input type="email" name="email" placeholder="Email (optional)"></div>
                <div class="form-group"><label>Role</label>
                    <select name="role" required>
                        <option value="">Select Role</option>
                        <option value="admin">Administrator</option>
                        <option value="doctor">Doctor</option>
                        <option value="nurse">Nurse</option>
                        <option value="technician">Technician</option>
                        <option value="staff">Staff</option>
                    </select>
                </div>
                <div class="form-group"><label>Clinic</label>
                    <select name="clinic_id">
                        <option value="">All Clinics</option>
                        <?php foreach ($clinics as $clinic): ?>
                            <option value="<?php echo $clinic['id']; ?>"><?php echo htmlspecialchars($clinic['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div><button type="submit" name="add_user" class="btn-add"><i class="fas fa-plus"></i> Add User</button></div>
            </form>
        </div>
    </div>

    <!-- Filter Bar -->
    <div class="panel">
        <div class="panel-header">
            <h3><i class="fas fa-filter"></i> Filter Users</h3>
            <div class="filter-bar">
                <div class="search-box"><i class="fas fa-search"></i><input type="text" id="searchInput" placeholder="Search name, username, email..."></div>
                <select id="roleFilter" class="filter-select"><option value="">All Roles</option><option value="admin">Admin</option><option value="doctor">Doctor</option><option value="nurse">Nurse</option><option value="technician">Technician</option><option value="staff">Staff</option></select>
                <select id="statusFilter" class="filter-select"><option value="">All Status</option><option value="active">Active</option><option value="inactive">Inactive</option></select>
                <button id="applyFilters" class="btn-filter"><i class="fas fa-search"></i> Apply</button>
                <a href="users.php" class="btn-clear"><i class="fas fa-times"></i> Clear</a>
            </div>
        </div>
    </div>

    <!-- Users Table -->
    <div class="panel">
        <div class="panel-header"><h3><i class="fas fa-list-ul"></i> System Users</h3></div>
        <div class="table-container">
            <?php if (empty($users)): ?>
                <div class="empty-state"><i class="fas fa-users-slash"></i><p>No users found</p><small>Click "Add User" to create a new system user</small></div>
            <?php else: ?>
                <table class="data-table" id="usersTable">
                    <thead>
                        <tr><th>ID</th><th>Username</th><th>Full Name</th><th>Email</th><th>Role</th><th>Clinic</th><th>Status</th><th>Actions</th></tr>
                    </thead>
                    <tbody id="usersList">
                        <?php foreach ($users as $user): ?>
                        <tr data-id="<?php echo $user['id']; ?>" data-username="<?php echo strtolower($user['username']); ?>" data-name="<?php echo strtolower($user['full_name']); ?>" data-email="<?php echo strtolower($user['email'] ?? ''); ?>" data-role="<?php echo $user['role']; ?>" data-status="<?php echo $user['is_active'] ? 'active' : 'inactive'; ?>">
                            <td><?php echo $user['id']; ?></td>
                            <td><strong><?php echo htmlspecialchars($user['username']); ?></strong></td>
                            <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                            <td><?php echo htmlspecialchars($user['email'] ?? '—'); ?></td>
                            <td><span class="role-badge role-<?php echo $user['role']; ?>"><?php echo ucfirst($user['role']); ?></span></td>
                            <td><?php if ($user['clinic_name']): ?><span class="clinic-badge"><i class="fas fa-clinic-medical"></i> <?php echo htmlspecialchars($user['clinic_name']); ?></span><?php else: ?><span class="clinic-badge">All Clinics</span><?php endif; ?></td>
                            <td><?php if ($user['is_active']): ?><span class="status-badge status-active"><i class="fas fa-check-circle"></i> Active</span><?php else: ?><span class="status-badge status-inactive"><i class="fas fa-ban"></i> Inactive</span><?php endif; ?></td>
                            <td class="action-buttons">
                                <?php if ($user['id'] != 1): ?>
                                    <button onclick="openEditModal(<?php echo $user['id']; ?>)" class="btn-icon btn-edit"><i class="fas fa-edit"></i> Edit</button>
                                    <?php if ($user['is_active']): ?>
                                        <a href="?deactivate=<?php echo $user['id']; ?>" class="btn-icon btn-deactivate" onclick="return confirm('Deactivate this user? They will not be able to login.')"><i class="fas fa-pause-circle"></i> Deactivate</a>
                                    <?php else: ?>
                                        <a href="?activate=<?php echo $user['id']; ?>" class="btn-icon btn-activate" onclick="return confirm('Activate this user?')"><i class="fas fa-play-circle"></i> Activate</a>
                                    <?php endif; ?>
                                    <a href="?reset_password=<?php echo $user['id']; ?>" class="btn-icon btn-reset" onclick="return confirm('Reset password to "clinic123" for this user?')"><i class="fas fa-key"></i> Reset Pwd</a>
                                    <a href="?delete=<?php echo $user['id']; ?>" class="btn-icon btn-delete" onclick="return confirm('Delete this user? Cannot be undone.')"><i class="fas fa-trash-alt"></i> Delete</a>
                                <?php else: ?>
                                    <span style="color: #999;"><i class="fas fa-shield-alt"></i> Protected</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</main>

<!-- Edit User Modal -->
<div id="editModal" class="modal">
    <div class="modal-content">
        <div class="modal-header"><h3><i class="fas fa-user-edit"></i> Edit User</h3><span class="close" onclick="closeEditModal()">&times;</span></div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="user_id" id="edit_user_id">
                <input type="hidden" name="edit_user" value="1">
                <div class="form-group"><label>Full Name</label><input type="text" name="full_name" id="edit_full_name" class="form-control" required></div>
                <div class="form-group"><label>Email</label><input type="email" name="email" id="edit_email" class="form-control"></div>
                <div class="form-group"><label>Role</label>
                    <select name="role" id="edit_role" class="form-control" required>
                        <option value="admin">Administrator</option>
                        <option value="doctor">Doctor</option>
                        <option value="nurse">Nurse</option>
                        <option value="technician">Technician</option>
                        <option value="staff">Staff</option>
                    </select>
                </div>
                <div class="form-group"><label>Clinic</label>
                    <select name="clinic_id" id="edit_clinic_id" class="form-control">
                        <option value="">All Clinics</option>
                        <?php foreach ($clinics as $clinic): ?>
                            <option value="<?php echo $clinic['id']; ?>"><?php echo htmlspecialchars($clinic['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="modal-actions"><button type="button" class="btn-cancel" onclick="closeEditModal()">Cancel</button><button type="submit" class="btn-save">Save Changes</button></div>
        </form>
    </div>
</div>

<script>
    function updateDateTime() {
        const now = new Date();
        document.getElementById('currentDate').textContent = now.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
        document.getElementById('currentTime').textContent = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
    }
    updateDateTime();
    setInterval(updateDateTime, 1000);

    // Edit Modal
    function openEditModal(userId) {
        const row = document.querySelector(`tr[data-id="${userId}"]`);
        if (row) {
            document.getElementById('edit_user_id').value = userId;
            document.getElementById('edit_full_name').value = row.cells[2]?.innerText.trim() || '';
            document.getElementById('edit_email').value = row.cells[3]?.innerText.trim() || '';
            const roleCell = row.cells[4]?.innerText.trim().toLowerCase() || 'staff';
            document.getElementById('edit_role').value = roleCell;
            document.getElementById('editModal').style.display = 'flex';
        }
    }
    function closeEditModal() { document.getElementById('editModal').style.display = 'none'; }
    window.onclick = function(e) { if (e.target == document.getElementById('editModal')) closeEditModal(); }

    // Search and Filter
    function filterTable() {
        const searchTerm = document.getElementById('searchInput')?.value.toLowerCase() || '';
        const roleFilter = document.getElementById('roleFilter')?.value || '';
        const statusFilter = document.getElementById('statusFilter')?.value || '';
        const rows = document.querySelectorAll('#usersList tr');
        rows.forEach(row => {
            const username = row.dataset.username || '';
            const name = row.dataset.name || '';
            const email = row.dataset.email || '';
            const role = row.dataset.role || '';
            const status = row.dataset.status || '';
            let show = true;
            if (searchTerm && !username.includes(searchTerm) && !name.includes(searchTerm) && !email.includes(searchTerm)) show = false;
            if (roleFilter && role !== roleFilter) show = false;
            if (statusFilter && status !== statusFilter) show = false;
            row.style.display = show ? '' : 'none';
        });
    }
    document.getElementById('applyFilters')?.addEventListener('click', filterTable);
    document.getElementById('searchInput')?.addEventListener('keyup', filterTable);
    document.getElementById('roleFilter')?.addEventListener('change', filterTable);
    document.getElementById('statusFilter')?.addEventListener('change', filterTable);

    // Auto-hide alert
    setTimeout(() => { document.querySelectorAll('.alert').forEach(a => a.style.opacity = '0'); setTimeout(() => document.querySelectorAll('.alert').forEach(a => a.remove()), 500); }, 5000);
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