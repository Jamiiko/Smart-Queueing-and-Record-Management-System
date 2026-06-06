<?php
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/QueueManager.php';

$database = new Database();
$db = $database->getConnection();
$queueManager = new QueueManager($db);

// Get current batch info
$batch_info = $queueManager->getCurrentBatch();
$batch_progress = ($batch_info['current_count'] / 20) * 100;

// Get all clinics
$clinic_query = "SELECT * FROM clinics WHERE is_active = 1 ORDER BY id";
$clinics = $db->query($clinic_query)->fetchAll(PDO::FETCH_ASSOC);

// For each clinic, get queue information
foreach ($clinics as &$clinic) {
    $clinic_id = $clinic['id'];
    
    // Get current patient (in-progress)
    $current_query = "SELECT q.queue_number, p.first_name, p.last_name 
                      FROM queue_entries q
                      JOIN patients p ON q.patient_id = p.id
                      WHERE q.clinic_id = ? 
                      AND q.status = 'in-progress'
                      AND DATE(q.registered_at) = CURDATE()
                      LIMIT 1";
    $stmt = $db->prepare($current_query);
    $stmt->execute([$clinic_id]);
    $current = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $clinic['current_patient'] = $current['queue_number'] ?? null;
    $clinic['current_patient_name'] = $current ? ($current['first_name'] . ' ' . $current['last_name']) : null;
    
    // Get waiting count
    $waiting_query = "SELECT COUNT(*) as count 
                      FROM queue_entries 
                      WHERE clinic_id = ? 
                      AND status = 'waiting'
                      AND DATE(registered_at) = CURDATE()";
    $stmt = $db->prepare($waiting_query);
    $stmt->execute([$clinic_id]);
    $clinic['waiting_count'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Get in-progress count
    $progress_query = "SELECT COUNT(*) as count 
                       FROM queue_entries 
                       WHERE clinic_id = ? 
                       AND status = 'in-progress'
                       AND DATE(registered_at) = CURDATE()";
    $stmt = $db->prepare($progress_query);
    $stmt->execute([$clinic_id]);
    $clinic['in_progress'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Get next 5 patients
    $next_query = "SELECT q.queue_number 
                   FROM queue_entries q
                   WHERE q.clinic_id = ? 
                   AND q.status = 'waiting'
                   AND DATE(q.registered_at) = CURDATE()
                   ORDER BY 
                     FIELD(q.priority_level, 'PR1', 'PR2', 'PR3'),
                     q.registered_at ASC
                   LIMIT 5";
    $stmt = $db->prepare($next_query);
    $stmt->execute([$clinic_id]);
    $next_patients = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $clinic['next_patients_array'] = $next_patients;
}

// Function to calculate wait time
function calculateWaitTime($position, $avg_service_time = 5) {
    return $position * $avg_service_time;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hospital Queue Display - Camp Evangelista</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: #F0F4F8;  /* Soft blue-gray background */
            color: #1E293B;  /* Dark slate for text */
            min-height: 100vh;
            padding: 20px;
        }

        /* Main container */
        .display-container {
            max-width: 1920px;
            margin: 0 auto;
        }

        /* Header Section */
        .hospital-header {
            background: linear-gradient(135deg, #FFFFFF 0%, #F8FAFC 100%);
            border-radius: 24px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
            border: 1px solid #E2E8F0;
            position: relative;
            overflow: hidden;
        }

        .hospital-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #2563EB, #38BDF8);
        }

        .header-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .hospital-title h1 {
            font-size: 42px;
            font-weight: 800;
            color: #0F172A;
            letter-spacing: -0.5px;
            line-height: 1.2;
        }

        .hospital-title h2 {
            font-size: 24px;
            font-weight: 500;
            color: #2563EB;
            margin-top: 5px;
        }

        .hospital-badge {
            background: rgba(37, 99, 235, 0.1);
            border: 1px solid #2563EB;
            border-radius: 50px;
            padding: 12px 24px;
            font-size: 18px;
            font-weight: 600;
            color: #2563EB;
        }

        /* Batch Info Cards */
        .batch-info-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-top: 20px;
        }

        .batch-card {
            background: #FFFFFF;
            border: 1px solid #E2E8F0;
            border-radius: 20px;
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 15px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.02);
        }

        .batch-icon {
            width: 60px;
            height: 60px;
            background: #F1F5F9;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .batch-icon i {
            font-size: 30px;
            color: #2563EB;
        }

        .batch-info h3 {
            font-size: 14px;
            color: #64748B;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 5px;
        }

        .batch-info .batch-number {
            font-size: 36px;
            font-weight: 700;
            color: #0F172A;
            line-height: 1;
        }

        .batch-progress {
            background: #FFFFFF;
            border: 1px solid #E2E8F0;
            border-radius: 20px;
            padding: 20px;
            grid-column: span 2;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.02);
        }

        .progress-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            color: #475569;
        }

        .progress-bar {
            height: 12px;
            background: #E2E8F0;
            border-radius: 6px;
            overflow: hidden;
            margin-bottom: 10px;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #2563EB, #38BDF8);
            border-radius: 6px;
            width: <?php echo $batch_progress; ?>%;
        }

        .wait-time {
            font-size: 18px;
            color: #2563EB;
            font-weight: 600;
        }

        /* Clinic Grid */
        .clinic-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
            margin-top: 30px;
        }

        .clinic-display-card {
            background: #FFFFFF;
            border: 1px solid #E2E8F0;
            border-radius: 20px;
            overflow: hidden;
            transition: all 0.2s ease;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.02);
        }

        .clinic-display-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 24px -8px rgba(0, 0, 0, 0.15);
            border-color: #94A3B8;
        }

        .clinic-header {
            background: #F8FAFC;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #E2E8F0;
        }

        .clinic-name {
            font-size: 18px;
            font-weight: 700;
            color: #0F172A;
            text-transform: uppercase;
        }

        .clinic-batch {
            background: #2563EB;
            color: #FFFFFF;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 700;
            box-shadow: 0 2px 4px rgba(37, 99, 235, 0.2);
        }

        .clinic-body {
            padding: 20px;
            background: #FFFFFF;
        }

        /* Current Patient Section */
        .current-patient {
            background: #F0F9FF;
            border: 1px solid #BAE6FD;
            border-radius: 15px;
            padding: 15px;
            margin-bottom: 20px;
            position: relative;
            overflow: hidden;
        }

        .current-patient::before {
            content: '● NOW SERVING';
            position: absolute;
            top: 8px;
            right: 12px;
            font-size: 11px;
            font-weight: 600;
            color: #0369A1;
            opacity: 0.7;
        }

        .current-label {
            font-size: 12px;
            color: #475569;
            text-transform: uppercase;
            margin-bottom: 5px;
            font-weight: 600;
        }

        .current-number {
            font-size: 32px;
            font-weight: 800;
            color: #0369A1;
            margin-bottom: 5px;
            letter-spacing: 1px;
        }

        .current-name {
            font-size: 16px;
            color: #1E293B;
            font-weight: 500;
        }

        /* Next Patients List */
        .next-patients {
            margin-top: 15px;
        }

        .next-label {
            font-size: 12px;
            color: #475569;
            text-transform: uppercase;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 5px;
            font-weight: 600;
        }

        .next-label i {
            color: #2563EB;
        }

        .next-list {
            list-style: none;
        }

        .next-item {
            display: flex;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #F1F5F9;
        }

        .next-item:last-child {
            border-bottom: none;
        }

        .next-position {
            width: 28px;
            height: 28px;
            background: #F1F5F9;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            font-weight: 600;
            color: #475569;
            margin-right: 12px;
        }

        .next-number {
            flex: 1;
            font-size: 16px;
            font-weight: 600;
            color: #0F172A;
        }

        /* Clinic Footer */
        .clinic-footer {
            background: #F8FAFC;
            padding: 15px 20px;
            border-top: 1px solid #E2E8F0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .wait-info {
            display: flex;
            align-items: center;
            gap: 6px;
            color: #2563EB;
            font-size: 14px;
            font-weight: 600;
        }

        .wait-info i {
            color: #F59E0B;
        }

        .queue-length {
            background: #E2E8F0;
            color: #475569;
            padding: 4px 12px;
            border-radius: 30px;
            font-size: 12px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .queue-length i {
            color: #2563EB;
        }

        /* Refresh indicator */
        .refresh-indicator {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: #FFFFFF;
            border: 1px solid #E2E8F0;
            border-radius: 50px;
            padding: 12px 24px;
            display: flex;
            align-items: center;
            gap: 10px;
            color: #475569;
            font-size: 14px;
            font-weight: 500;
            z-index: 1000;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }

        .refresh-indicator i {
            color: #2563EB;
            animation: spin 2s linear infinite;
        }

        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        /* No data state */
        .no-data {
            text-align: center;
            padding: 30px;
            color: #94A3B8;
            font-style: italic;
            background: #F8FAFC;
            border-radius: 12px;
            font-size: 14px;
        }

        /* Responsive */
        @media (max-width: 1200px) {
            .clinic-grid {
                grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            }
        }

        @media (max-width: 768px) {
            body {
                padding: 12px;
            }
            
            .hospital-title h1 {
                font-size: 28px;
            }
            
            .batch-info-grid {
                grid-template-columns: 1fr;
            }
            
            .clinic-grid {
                grid-template-columns: 1fr;
            }
            
            .hospital-header {
                padding: 20px;
            }
        }

        /* Small decorative touches */
        .clinic-display-card {
            position: relative;
        }

        .clinic-display-card::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 2px;
            background: linear-gradient(90deg, transparent, #2563EB, transparent);
            opacity: 0;
            transition: opacity 0.2s;
        }

        .clinic-display-card:hover::after {
            opacity: 0.3;
        }

        /* Better typography */
        .queue-length i, .wait-info i {
            font-size: 12px;
        }

        /* Soft shadows */
        .batch-card, .batch-progress, .clinic-display-card {
            box-shadow: 0 4px 12px -4px rgba(0, 0, 0, 0.05);
        }
    </style>
</head>
<body>
    <div class="display-container">
        <!-- Hospital Header -->
        <div class="hospital-header">
            <div class="header-top">
                <div class="hospital-title">
                    <h1>CAMP EVANGELISTA</h1>
                    <h2>STATION HOSPITAL</h2>
                </div>
                <div class="hospital-badge">
                    <i class="fas fa-shield-alt"></i> 4ID.P.A
                </div>
            </div>

            <!-- Batch Information -->
            <div class="batch-info-grid">
                <div class="batch-card">
                    <div class="batch-icon">
                        <i class="fas fa-layer-group"></i>
                    </div>
                    <div class="batch-info">
                        <h3>Current Batch</h3>
                        <div class="batch-number"><?php echo $batch_info['batch_number']; ?></div>
                    </div>
                </div>

                <div class="batch-progress">
                    <div class="progress-header">
                        <span style="color: #64748B;">Progress to next batch</span>
                        <span class="wait-time"><?php echo round($batch_progress); ?>%</span>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?php echo $batch_progress; ?>%;"></div>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-top: 10px;">
                        <span style="color: #64748B;"><?php echo $batch_info['current_count']; ?>/20 patients</span>
                        <span class="wait-time">
                            <i class="fas fa-clock"></i> Est. wait: <?php echo $batch_info['current_count'] * 2; ?> mins
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Clinic Grid -->
        <div class="clinic-grid">
            <?php foreach ($clinics as $clinic): ?>
            <div class="clinic-display-card">
                <div class="clinic-header">
                    <span class="clinic-name"><?php echo $clinic['name']; ?></span>
                    <span class="clinic-batch">BATCH <?php echo $batch_info['batch_number']; ?></span>
                </div>
                
                <div class="clinic-body">
                    <!-- Current Patient -->
                    <?php if ($clinic['current_patient']): ?>
                    <div class="current-patient">
                        <div class="current-label">NOW SERVING</div>
                        <div class="current-number"><?php echo $clinic['current_patient']; ?></div>
                        <div class="current-name"><?php echo $clinic['current_patient_name'] ?? 'Patient'; ?></div>
                    </div>
                    <?php else: ?>
                    <div class="current-patient" style="border-color: #64748B;">
                        <div class="current-label">NO ACTIVE PATIENT</div>
                        <div class="current-number">---</div>
                        <div class="current-name">Waiting for next</div>
                    </div>
                    <?php endif; ?>

                    <!-- Next 5 Patients -->
                    <div class="next-patients">
                        <div class="next-label">
                            <i class="fas fa-arrow-right"></i> NEXT 5 PATIENTS
                        </div>
                        <?php if (!empty($clinic['next_patients_array'])): ?>
                            <ul class="next-list">
                                <?php foreach ($clinic['next_patients_array'] as $index => $next): ?>
                                <li class="next-item">
                                    <span class="next-position"><?php echo $index + 1; ?></span>
                                    <span class="next-number"><?php echo $next; ?></span>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <div class="no-data">No patients in queue</div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="clinic-footer">
                    <div class="wait-info">
                        <i class="fas fa-hourglass-half"></i>
                        WAIT TIME: <?php echo calculateWaitTime(count($clinic['next_patients_array']) + ($clinic['in_progress'] ? 1 : 0)); ?> MINS
                    </div>
                    <div class="queue-length">
                        <i class="fas fa-users"></i> <?php echo $clinic['waiting_count']; ?> in queue
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Auto-refresh indicator -->
    <div class="refresh-indicator">
        <i class="fas fa-sync-alt"></i>
        <span>Updating in <span id="countdown">30</span>s</span>
    </div>

    <script>
        // Auto-refresh every 30 seconds
        let countdown = 30;
        const countdownEl = document.getElementById('countdown');
        
        setInterval(() => {
            countdown--;
            if (countdownEl) {
                countdownEl.textContent = countdown;
            }
            if (countdown <= 0) {
                location.reload();
            }
        }, 1000);
    </script>
</body>
</html>