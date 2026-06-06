<?php
// heartbeat.php - Keep session alive during activity
session_start();

// Just touch the session to keep it alive
if (isset($_SESSION['user_id'])) {
    $_SESSION['last_activity'] = time();
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false]);
}