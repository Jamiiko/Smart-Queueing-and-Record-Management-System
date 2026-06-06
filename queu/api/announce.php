<?php
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/QueueManager.php';

header('Content-Type: application/json');

session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$database = new Database();
$db = $database->getConnection();
$queueManager = new QueueManager($db);

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'call_patient':
        $queue_id = $_POST['queue_id'] ?? 0;
        if (!$queue_id) {
            echo json_encode(['success' => false, 'error' => 'No queue ID provided']);
            break;
        }
        
        if (!method_exists($queueManager, 'callPatient')) {
            echo json_encode(['success' => false, 'error' => 'Call patient method unavailable']);
            break;
        }

        $result = $queueManager->callPatient($queue_id);
        echo json_encode($result);
        break;
        
    case 'get_announcements':
        $since = $_GET['since'] ?? null;
        if (method_exists($queueManager, 'getRecentAnnouncements')) {
            $announcements = $queueManager->getRecentAnnouncements($since);
        } elseif (method_exists($queueManager, 'getAnnouncements')) {
            $announcements = $queueManager->getAnnouncements($since);
        } else {
            echo json_encode(['success' => false, 'error' => 'Announcement retrieval method unavailable']);
            break;
        }
        echo json_encode(['success' => true, 'announcements' => $announcements]);
        break;
        
    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
}
?>