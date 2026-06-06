<?php
// Get current page to highlight active menu
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!-- ===== REDESIGNED NAVBAR - MATCHING COMMAND DASHBOARD ===== -->
<nav class="sidebar-nav" style="background: transparent;">
    <!-- This is now part of the sidebar, not a top navbar -->
</nav>

<!-- For the top header area (if needed) -->
<div class="top-header" style="margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center;">
    <div class="header-title">
        <h1 style="font-size: 24px; font-weight: 600; color: #FFFFFF; margin: 0;">
            <?php 
            if (isset($_SESSION['role'])) {
                if ($_SESSION['role'] == 'admin') {
                    echo 'COMMAND DASHBOARD';
                } else {
                    echo $_SESSION['clinic_name'] . ' DASHBOARD';
                }
            }
            ?>
        </h1>
        <p style="font-size: 14px; color: #64748B; margin: 4px 0 0 0;">
            <?php echo date('l, F d, Y - h:i A'); ?>
        </p>
    </div>
    
    <div class="header-stats" style="display: flex; gap: 32px;">
        <?php if (isset($_SESSION['user_id'])): ?>
            <div class="stat-item" style="text-align: right;">
                <div class="stat-label" style="font-size: 12px; color: #64748B; text-transform: uppercase;">
                    <i class="fas fa-user-circle" style="color: #2DD4BF;"></i> 
                    <?php echo $_SESSION['full_name'] ?? $_SESSION['username']; ?>
                </div>
                <div class="stat-value" style="font-size: 14px; color: #FFFFFF;">
                    <span class="badge" style="background: #2DD4BF; color: #0A0F1E; padding: 4px 8px; border-radius: 4px;">
                        <?php echo $_SESSION['role']; ?>
                    </span>
                    <a href="../logout.php" style="color: #EF4444; margin-left: 12px; text-decoration: none;">
                        <i class="fas fa-sign-out-alt"></i>
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Sidebar Navigation (this is the actual menu) -->
<aside class="sidebar" style="background: linear-gradient(180deg, #0A0F1E 0%, #141B2B 100%); border-right: 1px solid #2D3748; width: 280px; height: 100vh; position: fixed; left: 0; top: 0; padding: 24px 16px;">
    
    <!-- Logo Area with Actual PNG Logo -->
    <div style="text-align: center; padding-bottom: 24px; border-bottom: 1px solid #2D3748; margin-bottom: 24px;">
        <!-- PNG Logo -->
        <img src="<?php echo $base_url ?? '../'; ?>assets/images/logo.png" 
             alt="Camp Evangelista Hospital Logo" 
             style="width: 100px; height: 100px; object-fit: contain; margin-bottom: 12px; border-radius: 12px; background: rgba(255,255,255,0.1); padding: 8px;">
        
        <h2 style="font-size: 20px; font-weight: 600; color: #FFFFFF; margin: 0 0 4px 0;">CAMP EVANGELISTA</h2>
        <p style="font-size: 12px; color: #64748B; text-transform: uppercase; letter-spacing: 1px; margin: 0;">Station Hospital</p>
        
        <?php if (isset($_SESSION['clinic_name']) && $_SESSION['role'] != 'admin'): ?>
            <div style="margin-top: 12px;">
                <span style="background: #2DD4BF; color: #0A0F1E; padding: 4px 12px; border-radius: 4px; font-size: 12px; font-weight: 600;">
                    <?php echo $_SESSION['clinic_name']; ?>
                </span>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Navigation Menu -->
    <ul style="list-style: none; padding: 0; margin: 0;">
        <?php if (isset($_SESSION['role'])): ?>
            <?php if ($_SESSION['role'] == 'admin'): ?>
                <!-- Admin Navigation -->
                <li style="margin-bottom: 4px;">
                    <a href="../admin/dashboard.php" 
                       style="display: flex; align-items: center; padding: 12px 16px; color: <?php echo $current_page == 'dashboard.php' ? '#2DD4BF' : '#94A3B8'; ?>; text-decoration: none; border-radius: 8px; background: <?php echo $current_page == 'dashboard.php' ? '#1E2639' : 'transparent'; ?>; border-left: <?php echo $current_page == 'dashboard.php' ? '3px solid #2DD4BF' : 'none'; ?>; transition: all 0.2s;">
                        <i class="fas fa-tachometer-alt" style="width: 24px; margin-right: 12px; color: <?php echo $current_page == 'dashboard.php' ? '#2DD4BF' : '#64748B'; ?>;"></i>
                        Command Dashboard
                    </a>
                </li>
                <li style="margin-bottom: 4px;">
                    <a href="../admin/patients.php"
                       style="display: flex; align-items: center; padding: 12px 16px; color: <?php echo $current_page == 'patients.php' ? '#2DD4BF' : '#94A3B8'; ?>; text-decoration: none; border-radius: 8px; background: <?php echo $current_page == 'patients.php' ? '#1E2639' : 'transparent'; ?>; border-left: <?php echo $current_page == 'patients.php' ? '3px solid #2DD4BF' : 'none'; ?>;">
                        <i class="fas fa-users" style="width: 24px; margin-right: 12px; color: <?php echo $current_page == 'patients.php' ? '#2DD4BF' : '#64748B'; ?>;"></i>
                        Patient Results
                    </a>
                </li>
                <li style="margin-bottom: 4px;">
                    <a href="../admin/queue-monitor.php"
                       style="display: flex; align-items: center; padding: 12px 16px; color: <?php echo $current_page == 'queue-monitor.php' ? '#2DD4BF' : '#94A3B8'; ?>; text-decoration: none; border-radius: 8px; background: <?php echo $current_page == 'queue-monitor.php' ? '#1E2639' : 'transparent'; ?>; border-left: <?php echo $current_page == 'queue-monitor.php' ? '3px solid #2DD4BF' : 'none'; ?>;">
                        <i class="fas fa-list-ol" style="width: 24px; margin-right: 12px; color: <?php echo $current_page == 'queue-monitor.php' ? '#2DD4BF' : '#64748B'; ?>;"></i>
                        Queue Monitor
                    </a>
                </li>
                <li style="margin-bottom: 4px;">
                    <a href="../admin/reports.php"
                       style="display: flex; align-items: center; padding: 12px 16px; color: <?php echo $current_page == 'reports.php' ? '#2DD4BF' : '#94A3B8'; ?>; text-decoration: none; border-radius: 8px; background: <?php echo $current_page == 'reports.php' ? '#1E2639' : 'transparent'; ?>; border-left: <?php echo $current_page == 'reports.php' ? '3px solid #2DD4BF' : 'none'; ?>;">
                        <i class="fas fa-chart-bar" style="width: 24px; margin-right: 12px; color: <?php echo $current_page == 'reports.php' ? '#2DD4BF' : '#64748B'; ?>;"></i>
                        Reports
                    </a>
                </li>
                <li style="margin-bottom: 4px;">
                    <a href="../admin/clinic-congestion.php"
                       style="display: flex; align-items: center; padding: 12px 16px; color: <?php echo $current_page == 'clinic-congestion.php' ? '#2DD4BF' : '#94A3B8'; ?>; text-decoration: none; border-radius: 8px; background: <?php echo $current_page == 'clinic-congestion.php' ? '#1E2639' : 'transparent'; ?>; border-left: <?php echo $current_page == 'clinic-congestion.php' ? '3px solid #2DD4BF' : 'none'; ?>;">
                        <i class="fas fa-clock" style="width: 24px; margin-right: 12px; color: <?php echo $current_page == 'clinic-congestion.php' ? '#2DD4BF' : '#64748B'; ?>;"></i>
                        Clinic Status
                    </a>
                </li>
                
            <?php elseif ($_SESSION['role'] == 'doctor' || $_SESSION['role'] == 'nurse' || $_SESSION['role'] == 'technician' || $_SESSION['role'] == 'staff'): ?>
                <!-- Staff Navigation -->
                <li style="margin-bottom: 4px;">
                    <a href="../staff/clinic-dashboard.php?clinic_id=<?php echo $_SESSION['clinic_id']; ?>"
                       style="display: flex; align-items: center; padding: 12px 16px; color: <?php echo $current_page == 'clinic-dashboard.php' ? '#2DD4BF' : '#94A3B8'; ?>; text-decoration: none; border-radius: 8px; background: <?php echo $current_page == 'clinic-dashboard.php' ? '#1E2639' : 'transparent'; ?>; border-left: <?php echo $current_page == 'clinic-dashboard.php' ? '3px solid #2DD4BF' : 'none'; ?>;">
                        <i class="fas fa-clinic-medical" style="width: 24px; margin-right: 12px; color: <?php echo $current_page == 'clinic-dashboard.php' ? '#2DD4BF' : '#64748B'; ?>;"></i>
                        My Clinic
                    </a>
                </li>
                <li style="margin-bottom: 4px;">
                    <a href="../staff/registration.php"
                       style="display: flex; align-items: center; padding: 12px 16px; color: <?php echo $current_page == 'registration.php' ? '#2DD4BF' : '#94A3B8'; ?>; text-decoration: none; border-radius: 8px; background: <?php echo $current_page == 'registration.php' ? '#1E2639' : 'transparent'; ?>; border-left: <?php echo $current_page == 'registration.php' ? '3px solid #2DD4BF' : 'none'; ?>;">
                        <i class="fas fa-user-plus" style="width: 24px; margin-right: 12px; color: <?php echo $current_page == 'registration.php' ? '#2DD4BF' : '#64748B'; ?>;"></i>
                        Registration
                    </a>
                </li>
            <?php endif; ?>
        <?php endif; ?>
    </ul>
    
    <!-- Bottom Section (always visible) -->
    <div style="position: absolute; bottom: 24px; left: 16px; right: 16px;">
        <div style="border-top: 1px solid #2D3748; padding-top: 16px;">
            <div style="display: flex; align-items: center; gap: 8px; color: #64748B; font-size: 12px;">
                <img src="<?php echo $base_url ?? '../'; ?>assets/images/logo.png" 
                     alt="Logo" 
                     style="width: 20px; height: 20px; object-fit: contain; border-radius: 4px;">
                <span>4ID Camp Evangelista</span>
            </div>
            <div style="margin-top: 8px; font-size: 11px; color: #4A5568;">
                v1.0.0
            </div>
        </div>
    </div>
</aside>

<!-- Adjust main content margin to account for fixed sidebar -->
<style>
    .main-content {
        margin-left: 280px;
        padding: 24px;
    }
    
    @media (max-width: 768px) {
        .sidebar {
            display: none;
        }
        .main-content {
            margin-left: 0;
        }
    }
</style>

<!-- Font Awesome -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">