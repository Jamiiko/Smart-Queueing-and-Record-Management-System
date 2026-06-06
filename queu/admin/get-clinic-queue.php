<?php
// admin/get-clinic-queue.php - AJAX endpoint for clinic queue data
// Camp Evangelista Station Hospital

require_once dirname(__DIR__) . '/config/database.php';

session_start();

// Check if admin is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$clinic_id = isset($_GET['clinic_id']) ? (int)$_GET['clinic_id'] : 0;

if ($clinic_id <= 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Invalid clinic ID']);
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Get clinic queue with patient details
$query = "SELECT 
            q.queue_number,
            q.status,
            q.priority_level,
            q.registered_at,
            TIMESTAMPDIFF(MINUTE, q.registered_at, NOW()) as waiting_minutes,
            p.first_name,
            p.last_name,
            p.patient_type
          FROM queue_entries q
          JOIN patients p ON q.patient_id = p.id
          WHERE q.clinic_id = :clinic_id 
          AND q.status IN ('waiting', 'called', 'in-progress')
          AND DATE(q.registered_at) = CURDATE()
          ORDER BY 
            FIELD(q.priority_level, 'PR1', 'PR2', 'PR3'),
            FIELD(q.status, 'called', 'in-progress', 'waiting'),
            q.registered_at ASC";

$stmt = $db->prepare($query);
$stmt->bindParam(':clinic_id', $clinic_id);
$stmt->execute();
$queue = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Format registered time
foreach ($queue as &$patient) {
    $patient['registered_time'] = date('h:i A', strtotime($patient['registered_at']));
}

// Get stats for this clinic
$stats_query = "SELECT 
                    COUNT(CASE WHEN status = 'waiting' THEN 1 END) as waiting,
                    COUNT(CASE WHEN status = 'called' THEN 1 END) as called,
                    COUNT(CASE WHEN status = 'in-progress' THEN 1 END) as in_progress,
                    COUNT(*) as total_today
                FROM queue_entries
                WHERE clinic_id = :clinic_id
                AND DATE(registered_at) = CURDATE()";

$stats_stmt = $db->prepare($stats_query);
$stats_stmt->bindParam(':clinic_id', $clinic_id);
$stats_stmt->execute();
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'queue' => $queue,
    'stats' => [
        'waiting' => $stats['waiting'] ?? 0,
        'called' => $stats['called'] ?? 0,
        'in_progress' => $stats['in_progress'] ?? 0,
        'total_today' => $stats['total_today'] ?? 0
    ]
]);
?>