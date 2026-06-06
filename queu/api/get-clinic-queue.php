<?php
require_once dirname(__DIR__) . '/config/database.php';

header('Content-Type: application/json');

$database = new Database();
$db = $database->getConnection();

$clinic_id = isset($_GET['clinic_id']) ? $_GET['clinic_id'] : 0;

if ($clinic_id) {
    // Get waiting count
    $query = "SELECT COUNT(*) as waiting 
              FROM queue_entries 
              WHERE clinic_id = :clinic_id 
              AND status IN ('waiting', 'called')
              AND DATE(registered_at) = CURDATE()";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':clinic_id', $clinic_id);
    $stmt->execute();
    $waiting = $stmt->fetch(PDO::FETCH_ASSOC)['waiting'];
    
    // Get in-progress count
    $query = "SELECT COUNT(*) as in_progress 
              FROM queue_entries 
              WHERE clinic_id = :clinic_id 
              AND status = 'in-progress'
              AND DATE(registered_at) = CURDATE()";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':clinic_id', $clinic_id);
    $stmt->execute();
    $in_progress = $stmt->fetch(PDO::FETCH_ASSOC)['in_progress'];
    
    // Get clinic capacity
    $query = "SELECT capacity_per_hour FROM clinics WHERE id = :clinic_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':clinic_id', $clinic_id);
    $stmt->execute();
    $capacity = $stmt->fetch(PDO::FETCH_ASSOC)['capacity_per_hour'];
    
    // Calculate estimated wait time (assuming 10 minutes per patient)
    $estimated_wait = ($waiting + $in_progress) * 10;
    
    echo json_encode([
        'success' => true,
        'waiting' => (int)$waiting,
        'in_progress' => (int)$in_progress,
        'capacity' => (int)$capacity,
        'estimated_wait' => $estimated_wait
    ]);
} else {
    echo json_encode(['success' => false, 'error' => 'No clinic ID provided']);
}
?>