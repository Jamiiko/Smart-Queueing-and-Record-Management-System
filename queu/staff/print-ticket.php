<?php
// staff/print-ticket.php - Print Queue Ticket for Staff
// Camp Evangelista Station Hospital

require_once dirname(__DIR__) . '/config/database.php';

session_start();

// ============================================
// AUTHENTICATION CHECK - Staff only
// ============================================
if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit();
}

$allowed_roles = ['admin', 'doctor', 'nurse', 'technician', 'staff'];
if (!in_array($_SESSION['role'], $allowed_roles)) {
    header('Location: ../unauthorized.php');
    exit();
}

// ============================================
// DATABASE CONNECTION
// ============================================
$database = new Database();
$db = $database->getConnection();

$transaction_token = isset($_GET['token']) ? $_GET['token'] : '';

// Get queue entry details
$query = "SELECT q.*, p.first_name, p.last_name, p.mrn, p.patient_type, p.is_pwd, p.is_senior, p.is_pregnant, c.name as clinic_name
          FROM queue_entries q
          JOIN patients p ON q.patient_id = p.id
          JOIN clinics c ON q.clinic_id = c.id
          WHERE q.transaction_token = :token";

$stmt = $db->prepare($query);
$stmt->bindParam(':token', $transaction_token);
$stmt->execute();
$queue = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$queue) {
    die("Invalid ticket request.");
}

// ============================================
// QR CODE URL - Points to patient-portal/track-queue.php
// ============================================
// Get base URL dynamically (handles localhost, domain, subfolder paths)
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'];
$base_path = rtrim(dirname(dirname($_SERVER['PHP_SELF'])), '/'); // Gets /queu (or root)

// Construct the absolute URL to patient-portal/track-queue.php
$tracking_url = $protocol . $host . $base_path . '/patient-portal/track-queue.php?token=' . urlencode($queue['transaction_token']);
$qr_code_url = "https://api.qrserver.com/v1/create-qr-code/?size=120x120&data=" . urlencode($tracking_url);

// Determine priority display
$priority_display = [
    'PR1' => ['text' => 'MILITARY', 'bg' => '#FF6F61', 'color' => 'white'],
    'PR2' => ['text' => 'PRIORITY', 'bg' => '#FFB84D', 'color' => '#333'],
    'PR3' => ['text' => 'REGULAR', 'bg' => '#A4D1B1', 'color' => '#333']
];
$p_info = $priority_display[$queue['priority_level']] ?? $priority_display['PR3'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print Ticket | <?php echo htmlspecialchars($queue['queue_number']); ?> | Camp Evangelista</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* Thermal Printer Optimized - 80mm width */
        @page {
            size: 80mm auto;
            margin: 0;
        }
        
        @media print {
            body { 
                margin: 0; 
                padding: 0;
                background: white;
            }
            .no-print { 
                display: none !important; 
            }
            .ticket {
                box-shadow: none;
                border: none;
                margin: 0;
                padding: 8px;
                width: 100%;
            }
            .ticket-header {
                padding: 8px 0 !important;
            }
            .queue-number {
                font-size: 28px !important;
                padding: 8px !important;
            }
            .qr-code img {
                width: 80px !important;
                height: 80px !important;
            }
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', 'Segoe UI', sans-serif;
            background: #e0e0e0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 16px;
        }
        
        .ticket {
            max-width: 350px;
            width: 100%;
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            overflow: hidden;
            font-size: 11px;
        }
        
        .ticket-section {
            padding: 10px 12px;
            border-bottom: 1px dashed #e0e0e0;
        }
        
        .ticket-section:last-child {
            border-bottom: none;
        }
        
        /* Header */
        .ticket-header {
            text-align: center;
            background: #1a3a5c;
            color: white;
            padding: 12px;
        }
        
        .hospital-name {
            font-size: 14px;
            font-weight: 700;
            letter-spacing: 0.5px;
        }
        
        .hospital-address {
            font-size: 8px;
            opacity: 0.8;
            margin-top: 2px;
        }
        
        .ticket-type {
            background: rgba(255,255,255,0.2);
            display: inline-block;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 9px;
            margin-top: 6px;
            font-weight: 600;
        }
        
        /* Queue Number */
        .queue-section {
            text-align: center;
            padding: 16px 12px !important;
            background: #f8f9fa;
        }
        
        .queue-label {
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #666;
        }
        
        .queue-number {
            font-size: 32px;
            font-weight: 800;
            font-family: 'Courier New', monospace;
            letter-spacing: 2px;
            color: #1a3a5c;
            background: white;
            padding: 8px 16px;
            border-radius: 8px;
            display: inline-block;
            margin-top: 6px;
            border: 1px solid #e0e0e0;
        }
        
        /* Priority Badge */
        .priority-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 9px;
            font-weight: 700;
            margin-top: 8px;
        }
        
        /* Token Section */
        .token-section {
            background: #f0f7ff;
            text-align: center;
        }
        
        .token-label {
            font-size: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #4A90E2;
        }
        
        .token-value {
            font-size: 11px;
            font-weight: 700;
            font-family: 'Courier New', monospace;
            background: white;
            padding: 6px 10px;
            border-radius: 6px;
            display: inline-block;
            margin-top: 6px;
            border: 1px solid #d0e0f0;
            word-break: break-all;
        }
        
        /* Info Rows */
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 5px 0;
            border-bottom: 1px dotted #f0f0f0;
        }
        
        .info-row:last-child {
            border-bottom: none;
        }
        
        .info-label {
            color: #666;
            font-weight: 500;
        }
        
        .info-value {
            font-weight: 600;
            color: #333;
            text-align: right;
        }
        
        /* QR Section */
        .qr-section {
            text-align: center;
            background: white;
        }
        
        .qr-code {
            display: inline-block;
            margin: 5px 0;
        }
        
        .qr-code img {
            width: 90px;
            height: 90px;
            border: 1px solid #e0e0e0;
            padding: 4px;
            background: white;
        }
        
        .tracking-note {
            font-size: 8px;
            color: #888;
            margin-top: 5px;
        }
        
        /* Footer */
        .ticket-footer {
            text-align: center;
            background: #f5f5f5;
            font-size: 8px;
            color: #999;
            padding: 10px !important;
        }
        
        .watermark {
            font-size: 7px;
            color: #ccc;
            text-align: center;
            margin-top: 8px;
        }
        
        /* Button Group */
        .button-group {
            display: flex;
            gap: 12px;
            margin-top: 16px;
        }
        
        .btn-print {
            flex: 1;
            background: #009688;
            color: white;
            border: none;
            padding: 12px;
            border-radius: 40px;
            font-weight: 600;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.2s;
        }
        
        .btn-print:hover {
            background: #00796B;
        }
        
        .btn-back {
            flex: 1;
            background: #4A90E2;
            color: white;
            border: none;
            padding: 12px;
            border-radius: 40px;
            font-weight: 600;
            cursor: pointer;
            font-size: 14px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.2s;
        }
        
        .btn-back:hover {
            background: #3A7BC8;
            transform: translateY(-1px);
        }
    </style>
</head>
<body>
    <div>
        <!-- Ticket -->
        <div class="ticket">
            <!-- Header -->
            <div class="ticket-header">
                <div class="hospital-name">4ID STATION HOSPITAL</div>
                <div class="hospital-address">Camp Evangelista, Cagayan de Oro City</div>
                <div class="ticket-type">OUTPATIENT QUEUE TICKET</div>
            </div>
            
            <!-- Queue Number -->
            <div class="ticket-section queue-section">
                <div class="queue-label">YOUR QUEUE NUMBER</div>
                <div class="queue-number"><?php echo htmlspecialchars($queue['queue_number']); ?></div>
                <div>
                    <span class="priority-badge" style="background: <?php echo $p_info['bg']; ?>; color: <?php echo $p_info['color']; ?>;">
                        <?php echo $p_info['text']; ?>
                    </span>
                </div>
            </div>
            
            <!-- Transaction Token -->
            <div class="ticket-section token-section">
                <div class="token-label"><i class="fas fa-key"></i> PRIVATE TRACKING TOKEN</div>
                <div class="token-value"><?php echo htmlspecialchars($queue['transaction_token']); ?></div>
                <div style="font-size: 7px; margin-top: 4px; color: #666;">
                    Keep this token private - used to track your queue
                </div>
            </div>
            
            <!-- Patient Information -->
            <div class="ticket-section">
                <div class="info-row">
                    <span class="info-label">Patient Name</span>
                    <span class="info-value"><?php echo htmlspecialchars($queue['last_name'] . ', ' . $queue['first_name']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">MRN</span>
                    <span class="info-value"><?php echo htmlspecialchars($queue['mrn']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Clinic</span>
                    <span class="info-value"><?php echo htmlspecialchars($queue['clinic_name']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Patient Type</span>
                    <span class="info-value"><?php echo ucfirst($queue['patient_type']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Date</span>
                    <span class="info-value"><?php echo date('M d, Y', strtotime($queue['registered_at'])); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Time</span>
                    <span class="info-value"><?php echo date('h:i A', strtotime($queue['registered_at'])); ?></span>
                </div>
            </div>
            
            <!-- QR Code Section -->
            <div class="ticket-section qr-section">
                <div class="qr-code">
                    <img src="<?php echo $qr_code_url; ?>" alt="QR Code">
                </div>
                <div class="tracking-note">
                    <i class="fas fa-qrcode"></i> Scan to track your queue status
                </div>
                <div style="font-size: 7px; margin-top: 4px; color: #888;">
                    or visit: /patient-portal/track-queue.php
                </div>
            </div>
            
            <!-- Footer Instructions -->
            <div class="ticket-section ticket-footer">
                <div><i class="fas fa-bell"></i> Please wait for your number to be called</div>
                <div style="margin-top: 4px;"><i class="fas fa-phone-alt"></i> For concerns: (088) 123-4567</div>
                <div class="watermark">Ticket #: <?php echo date('YmdHis'); ?></div>
            </div>
        </div>
        
        <!-- Screen Buttons -->
        <div class="no-print">
            <div class="button-group">
                <button onclick="window.print()" class="btn-print">
                    <i class="fas fa-print"></i> Print Ticket
                </button>
                <a href="clinic-dashboard.php?clinic_id=<?php echo $queue['clinic_id']; ?>" class="btn-back">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
        </div>
    </div>
    
    <script>
        // Auto open print dialog when page loads
        setTimeout(function() {
            window.print();
        }, 500);
    </script>
</body>
</html>