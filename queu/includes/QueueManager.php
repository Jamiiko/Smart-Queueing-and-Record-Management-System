<?php
class QueueManager {
    private $conn;
    private $batch_size = 20;
    
    public function __construct($db) {
        $this->conn = $db;
    }
    
    /**
     * Generate a unique transaction token for tracking
     * Format: TXN-YYYYMMDD-XXXXXX (e.g., TXN-20260126-000123)
     */
    public function generateTransactionToken() {
        $max_attempts = 5;
        $attempt = 0;
        
        while ($attempt < $max_attempts) {
            $prefix = 'TXN-' . date('Ymd') . '-';
            $sequence = str_pad(rand(1, 999999), 6, '0', STR_PAD_LEFT);
            $token = $prefix . $sequence;
            
            // Check if token already exists
            $check_query = "SELECT COUNT(*) as count FROM queue_entries WHERE transaction_token = :token";
            $check_stmt = $this->conn->prepare($check_query);
            $check_stmt->bindParam(':token', $token);
            $check_stmt->execute();
            $result = $check_stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result['count'] == 0) {
                return $token;
            }
            
            $attempt++;
        }
        
        // Fallback: use timestamp + random
        return 'TXN-' . date('YmdHis') . '-' . rand(100, 999);
    }
    
    /**
     * Get the last sequence number for today's tokens (DEPRECATED)
     */
    private function getLastTokenSequence() {
        $query = "SELECT COUNT(*) as count FROM queue_entries 
                  WHERE DATE(registered_at) = CURDATE()";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'] ?? 0;
    }
    
    public function getCurrentBatch() {
        $current_hour = date('Y-m-d H:00:00');
        $next_hour = date('Y-m-d H:00:00', strtotime('+1 hour'));
        
        $query = "SELECT COUNT(DISTINCT patient_id) as count 
                  FROM queue_entries 
                  WHERE batch_hour = :batch_hour";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':batch_hour', $current_hour);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $current_count = $result ? $result['count'] : 0;
        
        $remaining_slots = $this->batch_size - $current_count;
        
        return [
            'current_hour' => $current_hour,
            'next_hour' => $next_hour,
            'current_count' => $current_count,
            'remaining_slots' => $remaining_slots,
            'is_full' => $remaining_slots <= 0,
            'batch_number' => date('H')
        ];
    }
    
    public function generateQueueNumber($priority, $batch_hour) {
        $prefix = '';
        switch($priority) {
            case 'PR1': $prefix = 'M'; break;
            case 'PR2': $prefix = 'P'; break;
            case 'PR3': $prefix = 'R'; break;
        }
        
        $batch = date('H', strtotime($batch_hour));
        
        $query = "SELECT COUNT(DISTINCT patient_id) as count 
                  FROM queue_entries 
                  WHERE batch_hour = :batch_hour";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':batch_hour', $batch_hour);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $sequence = str_pad(($row['count'] ?? 0) + 1, 3, '0', STR_PAD_LEFT);
        
        return $prefix . '-' . $batch . '-' . $sequence;
    }
    
    public function determinePriority($patient) {
        if ($patient['patient_type'] == 'military') {
            return 'PR1';
        } elseif ($patient['is_pwd'] == 1 || $patient['is_senior'] == 1 || $patient['is_pregnant'] == 1) {
            return 'PR2';
        } else {
            return 'PR3';
        }
    }
    
    public function addToQueue($patient_id, $clinic_id, $appointment_time = null, $force_batch_hour = null) {
        $query = "SELECT * FROM patients WHERE id = :patient_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':patient_id', $patient_id);
        $stmt->execute();
        $patient = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$patient) {
            return ['success' => false, 'error' => 'Patient not found'];
        }
        
        $priority = $this->determinePriority($patient);
        
        if ($force_batch_hour) {
            $batch_hour = $force_batch_hour;
        } else {
            $batch_info = $this->getCurrentBatch();
            $batch_hour = $batch_info['is_full'] ? $batch_info['next_hour'] : $batch_info['current_hour'];
        }
        
        // Check if patient already has a queue entry for this clinic today
        $existing_query = "SELECT queue_number, transaction_token FROM queue_entries 
                           WHERE patient_id = :patient_id 
                           AND clinic_id = :clinic_id
                           AND DATE(registered_at) = CURDATE() 
                           LIMIT 1";
        $existing_stmt = $this->conn->prepare($existing_query);
        $existing_stmt->bindParam(':patient_id', $patient_id);
        $existing_stmt->bindParam(':clinic_id', $clinic_id);
        $existing_stmt->execute();
        $existing = $existing_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existing) {
            // Patient already has a queue number for this clinic - reuse it!
            $queue_number = $existing['queue_number'];
            $transaction_token = $existing['transaction_token'];
        } else {
            // Generate new queue number and transaction token (unique)
            $queue_number = $this->generateQueueNumber($priority, $batch_hour);
            $transaction_token = $this->generateTransactionToken();
        }
        
        $appointment_type = $appointment_time ? 'appointment' : 'walk-in';
        
        $query = "INSERT INTO queue_entries 
                  (queue_number, transaction_token, patient_id, priority_level, clinic_id, 
                   appointment_type, appointment_time, batch_hour, registered_at, token_created_at) 
                  VALUES 
                  (:queue_number, :transaction_token, :patient_id, :priority, :clinic_id, 
                   :appointment_type, :appointment_time, :batch_hour, NOW(), NOW())";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':queue_number', $queue_number);
        $stmt->bindParam(':transaction_token', $transaction_token);
        $stmt->bindParam(':patient_id', $patient_id);
        $stmt->bindParam(':priority', $priority);
        $stmt->bindParam(':clinic_id', $clinic_id);
        $stmt->bindParam(':appointment_type', $appointment_type);
        $stmt->bindParam(':appointment_time', $appointment_time);
        $stmt->bindParam(':batch_hour', $batch_hour);
        
        if ($stmt->execute()) {
            return [
                'success' => true,
                'queue_number' => $queue_number,
                'transaction_token' => $transaction_token,
                'priority' => $priority,
                'batch_hour' => $batch_hour,
                'batch_number' => date('H', strtotime($batch_hour))
            ];
        }
        
        return ['success' => false, 'error' => 'Database error'];
    }
    
    // =========================================================================
    // FULLY FIXED: auto-routes patient to next clinic and assigns a NEW token
    // =========================================================================
    public function queueForNextClinic($patient_id, $current_clinic_id) {
        $query = "SELECT * FROM patients WHERE id = :patient_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':patient_id', $patient_id);
        $stmt->execute();
        $patient = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$patient) {
            return ['success' => false, 'error' => 'Patient not found'];
        }

        // Get patient's existing entry details to carry over their queue number prefix
        $existing_query = "SELECT queue_number, batch_hour FROM queue_entries 
                           WHERE patient_id = :patient_id 
                           AND clinic_id = :clinic_id
                           AND DATE(registered_at) = CURDATE() 
                           ORDER BY id DESC LIMIT 1";
        $existing_stmt = $this->conn->prepare($existing_query);
        $existing_stmt->bindParam(':patient_id', $patient_id);
        $existing_stmt->bindParam(':clinic_id', $current_clinic_id);
        $existing_stmt->execute();
        $existing = $existing_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existing) {
            $queue_number = $existing['queue_number'];
            $batch_hour = $existing['batch_hour'];
        } else {
            $batch_info = $this->getCurrentBatch();
            $batch_hour = $batch_info['is_full'] ? $batch_info['next_hour'] : $batch_info['current_hour'];
            $queue_number = $this->generateQueueNumber($this->determinePriority($patient), $batch_hour);
        }

        // Generate a BRAND NEW UNIQUE TOKEN here (Fixes the 1062 Duplicate Error)
        $transaction_token = $this->generateTransactionToken(); 
        
        // Get clinics already completed (including current one)
        $completed_query = "SELECT DISTINCT clinic_id 
                            FROM queue_entries 
                            WHERE patient_id = :patient_id 
                            AND status = 'completed'
                            AND DATE(registered_at) = CURDATE()";
        $completed_stmt = $this->conn->prepare($completed_query);
        $completed_stmt->bindParam(':patient_id', $patient_id);
        $completed_stmt->execute();
        $completed_clinics = $completed_stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Add current clinic to completed list
        $completed_clinics[] = $current_clinic_id;
        $exclude_ids = array_unique($completed_clinics);
        
        // Find least congested clinic not yet visited
        $clinics_by_congestion = $this->findLeastCongestedClinic($exclude_ids);
        
        if (empty($clinics_by_congestion)) {
            return [
                'success' => false, 
                'error' => 'No more clinics available',
                'all_completed' => true
            ];
        }
        
        $next_clinic = $clinics_by_congestion[0];
        
        // Check if already in queue for this upcoming clinic
        $check_query = "SELECT COUNT(*) as count FROM queue_entries 
                        WHERE patient_id = :patient_id 
                        AND clinic_id = :clinic_id 
                        AND DATE(registered_at) = CURDATE()";
        $check_stmt = $this->conn->prepare($check_query);
        $check_stmt->bindParam(':patient_id', $patient_id);
        $check_stmt->bindParam(':clinic_id', $next_clinic['id']);
        $check_stmt->execute();
        $check = $check_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (($check['count'] ?? 0) > 0) {
            return [
                'success' => false,
                'error' => 'Patient already in queue for this clinic'
            ];
        }
        
        $priority = $this->determinePriority($patient);
        
        // Insert new queue entry with the new UNIQUE token
        $insert_query = "INSERT INTO queue_entries 
                         (queue_number, transaction_token, patient_id, priority_level, clinic_id, 
                          appointment_type, batch_hour, registered_at, status, token_created_at) 
                         VALUES 
                         (:queue_number, :transaction_token, :patient_id, :priority, :clinic_id, 
                          'walk-in', :batch_hour, NOW(), 'waiting', NOW())";
        
        $insert_stmt = $this->conn->prepare($insert_query);
        $insert_stmt->bindParam(':queue_number', $queue_number);
        $insert_stmt->bindParam(':transaction_token', $transaction_token); // NEW UNIQUE TOKEN
        $insert_stmt->bindParam(':patient_id', $patient_id);
        $insert_stmt->bindParam(':priority', $priority);
        $insert_stmt->bindParam(':clinic_id', $next_clinic['id']);
        $insert_stmt->bindParam(':batch_hour', $batch_hour);
        
        if ($insert_stmt->execute()) {
            return [
                'success' => true,
                'clinic' => $next_clinic['name'],
                'clinic_id' => $next_clinic['id'],
                'queue_number' => $queue_number,
                'transaction_token' => $transaction_token,
                'remaining_clinics' => count($clinics_by_congestion) - 1
            ];
        }
        
        return ['success' => false, 'error' => 'Failed to queue for next clinic'];
    }
    
    public function getCompletedClinics($patient_id) {
        $query = "SELECT DISTINCT clinic_id 
                  FROM queue_entries 
                  WHERE patient_id = :patient_id 
                  AND status = 'completed'
                  AND DATE(registered_at) = CURDATE()";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':patient_id', $patient_id);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    
    public function findLeastCongestedClinic($exclude_clinic_ids = []) {
        $query = "SELECT 
                    c.id,
                    c.name,
                    COUNT(CASE WHEN q.status IN ('waiting', 'called', 'in-progress') THEN 1 END) as current_load,
                    c.capacity_per_hour
                  FROM clinics c
                  LEFT JOIN queue_entries q ON c.id = q.clinic_id 
                    AND DATE(q.registered_at) = CURDATE()
                  WHERE c.is_active = 1";
        
        if (!empty($exclude_clinic_ids)) {
            $exclude_list = implode(',', array_map('intval', $exclude_clinic_ids));
            $query .= " AND c.id NOT IN ($exclude_list)";
        }
        
        $query .= " GROUP BY c.id
                    ORDER BY current_load ASC, c.id ASC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getAllClinicsQueueStats() {
        $query = "SELECT 
                    c.id,
                    c.name,
                    COUNT(CASE WHEN q.status IN ('waiting', 'called') THEN 1 END) as waiting_count,
                    COUNT(CASE WHEN q.status = 'in-progress' THEN 1 END) as in_progress_count,
                    COUNT(CASE WHEN q.status = 'completed' THEN 1 END) as completed_count
                  FROM clinics c
                  LEFT JOIN queue_entries q ON c.id = q.clinic_id 
                    AND DATE(q.registered_at) = CURDATE()
                  WHERE c.is_active = 1
                  GROUP BY c.id
                  ORDER BY c.id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getClinicQueue($clinic_id) {
        $query = "SELECT q.*, p.first_name, p.last_name, p.patient_type,
                         p.is_pwd, p.is_senior, p.is_pregnant,
                         TIMESTAMPDIFF(MINUTE, q.registered_at, NOW()) as waiting_minutes,
                         (SELECT COUNT(*) FROM queue_entries 
                          WHERE clinic_id = q.clinic_id 
                          AND status IN ('waiting', 'called') 
                          AND registered_at < q.registered_at) + 1 as position_in_clinic
                  FROM queue_entries q
                  JOIN patients p ON q.patient_id = p.id
                  WHERE q.clinic_id = :clinic_id 
                  AND q.status IN ('waiting', 'called', 'in-progress')
                  AND DATE(q.registered_at) = CURDATE()
                  ORDER BY 
                    FIELD(q.priority_level, 'PR1', 'PR2', 'PR3'),
                    q.registered_at ASC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':clinic_id', $clinic_id);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function callNextPatient($clinic_id) {
        $query = "SELECT id FROM queue_entries 
                  WHERE clinic_id = :clinic_id 
                  AND status = 'waiting'
                  AND DATE(registered_at) = CURDATE()
                  ORDER BY 
                    FIELD(priority_level, 'PR1', 'PR2', 'PR3'),
                    registered_at ASC 
                  LIMIT 1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':clinic_id', $clinic_id);
        $stmt->execute();
        
        if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $update = "UPDATE queue_entries 
                       SET status = 'called', called_at = NOW() 
                       WHERE id = :id";
            $updateStmt = $this->conn->prepare($update);
            $updateStmt->bindParam(':id', $row['id']);
            $updateStmt->execute();
            return $row['id'];
        }
        return null;
    }
    
    /**
     * Get queue entry by transaction token
     */
    public function getQueueByToken($token) {
        $query = "SELECT q.*, p.first_name, p.last_name, p.patient_type, c.name as clinic_name,
                         TIMESTAMPDIFF(MINUTE, q.registered_at, NOW()) as waiting_minutes
                  FROM queue_entries q
                  JOIN patients p ON q.patient_id = p.id
                  JOIN clinics c ON q.clinic_id = c.id
                  WHERE q.transaction_token = :token";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':token', $token);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get queue entry by ID (for printing)
     */
    public function getQueueById($id) {
        $query = "SELECT q.*, p.first_name, p.last_name, p.mrn, c.name as clinic_name
                  FROM queue_entries q
                  JOIN patients p ON q.patient_id = p.id
                  JOIN clinics c ON q.clinic_id = c.id
                  WHERE q.id = :id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
?>