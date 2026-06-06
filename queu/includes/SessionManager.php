<?php
// includes/SessionManager.php - Session Timeout & Activity Tracking
// Camp Evangelista Station Hospital

class SessionManager {
    private $timeout_minutes = 30;  // Session timeout after 30 minutes of inactivity
    private $db;
    
    public function __construct($db = null) {
        $this->db = $db;
    }
    
    /**
     * Check if session has timed out
     * Call this at the beginning of every page after session_start()
     */
    public function checkTimeout() {
        // Check if last activity is set
        if (isset($_SESSION['last_activity'])) {
            $inactive_time = time() - $_SESSION['last_activity'];
            
            // If inactive for more than timeout minutes
            if ($inactive_time > ($this->timeout_minutes * 60)) {
                $this->logout('Session timeout due to inactivity');
                return false;
            }
        }
        
        // Update last activity time
        $_SESSION['last_activity'] = time();
        return true;
    }
    
    /**
     * Log user activity to database
     */
    public function logActivity($action) {
        if ($this->db && isset($_SESSION['user_id'])) {
            try {
                $ip_address = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
                $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
                $page = basename($_SERVER['PHP_SELF']);
                $full_action = $action . " on " . $page;
                
                $stmt = $this->db->prepare("
                    INSERT INTO user_activity (user_id, action, ip_address, user_agent, created_at) 
                    VALUES (?, ?, ?, ?, NOW())
                ");
                $stmt->execute([$_SESSION['user_id'], $full_action, $ip_address, $user_agent]);
            } catch (PDOException $e) {
                // Silently fail - don't break the page if logging fails
            }
        }
    }
    
    /**
     * Logout user and clear session
     */
    private function logout($reason) {
        // Log the logout reason if database is available
        if ($this->db && isset($_SESSION['user_id'])) {
            try {
                $stmt = $this->db->prepare("
                    INSERT INTO user_activity (user_id, action, ip_address, created_at) 
                    VALUES (?, ?, ?, NOW())
                ");
                $ip_address = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
                $stmt->execute([$_SESSION['user_id'], "Auto-logout: $reason", $ip_address]);
            } catch (PDOException $e) {
                // Silently fail
            }
        }
        
        // Destroy session
        $_SESSION = array();
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
        }
        session_destroy();
        
        // Redirect to login with timeout parameter
        header('Location: ../index.php?timeout=1');
        exit();
    }
    
    /**
     * Get remaining session time in minutes
     */
    public function getRemainingTime() {
        if (isset($_SESSION['last_activity'])) {
            $elapsed = time() - $_SESSION['last_activity'];
            $remaining = ($this->timeout_minutes * 60) - $elapsed;
            return max(0, round($remaining / 60));
        }
        return $this->timeout_minutes;
    }
}