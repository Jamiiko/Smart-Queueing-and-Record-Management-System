<?php
require_once '../config/database.php';

header('Content-Type: text/html');

$database = new Database();
$db = $database->getConnection();

$clinic_id = isset($_GET['clinic_id']) ? $_GET['clinic_id'] : 0;

if ($clinic_id) {
    $query = "SELECT q.*, p.first_name, p.last_name
              FROM queue_entries q
              JOIN patients p ON q.patient_id = p.id
              WHERE q.clinic_id = :clinic_id 
              AND q.status IN ('waiting', 'called', 'in-progress')
              ORDER BY 
                FIELD(q.priority_level, 'PR1', 'PR2', 'PR3'),
                q.registered_at ASC
              LIMIT 5";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':clinic_id', $clinic_id);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        echo '<div class="list-group">';
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $status_class = $row['status'] == 'waiting' ? 'warning' : 
                           ($row['status'] == 'called' ? 'info' : 'primary');
            echo '<div class="list-group-item">';
            echo '<div class="d-flex justify-content-between">';
            echo '<span><strong>' . $row['queue_number'] . '</strong> - ' . 
                 $row['last_name'] . ', ' . $row['first_name'] . '</span>';
            echo '<span class="badge bg-' . $status_class . '">' . ucfirst($row['status']) . '</span>';
            echo '</div>';
            echo '</div>';
        }
        echo '</div>';
    } else {
        echo '<p class="text-muted">No active patients</p>';
    }
}
?>