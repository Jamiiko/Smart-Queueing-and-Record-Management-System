<?php
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

echo "<h2>🔧 Fixing User Passwords</h2>";

// List of users to create/fix
$users = [
    ['admin', 'admin123', 'System Administrator', 'admin', null],
    ['reg_staff', 'clinic123', 'Registration Staff', 'staff', 1],
    ['vital_staff', 'clinic123', 'Vital Signs Staff', 'nurse', 2],
    ['lab_tech', 'clinic123', 'Lab Technician', 'technician', 3],
    ['xray_tech', 'clinic123', 'X-Ray Technician', 'technician', 4],
    ['ecg_tech', 'clinic123', 'ECG Technician', 'technician', 5],
    ['dentist', 'clinic123', 'Dentist', 'doctor', 6],
    ['optometrist', 'clinic123', 'Optometrist', 'doctor', 7],
    ['dr_general', 'clinic123', 'General Practitioner', 'doctor', 8],
    ['nurse_reg', 'clinic123', 'Nurse Maria', 'nurse', 1],
    ['nurse_lab', 'clinic123', 'Nurse Jose', 'nurse', 3],
    ['nurse_ecg', 'clinic123', 'Nurse Ana', 'nurse', 5]
];

echo "<table border='1' cellpadding='8' style='border-collapse: collapse;'>";
echo "<tr><th>Username</th><th>Password</th><th>Status</th><th>Hash</th></tr>";

foreach ($users as $user) {
    $username = $user[0];
    $password = $user[1];
    $full_name = $user[2];
    $role = $user[3];
    $clinic_id = $user[4];
    
    // Generate new hash
    $hash = password_hash($password, PASSWORD_DEFAULT);
    
    // Check if user exists
    $check = "SELECT id FROM users WHERE username = ?";
    $stmt = $db->prepare($check);
    $stmt->execute([$username]);
    
    if ($stmt->rowCount() > 0) {
        // Update existing user
        $query = "UPDATE users SET password = ?, full_name = ?, role = ?, clinic_id = ? WHERE username = ?";
        $update = $db->prepare($query);
        $result = $update->execute([$hash, $full_name, $role, $clinic_id, $username]);
        $status = $result ? "✅ UPDATED" : "❌ FAILED";
    } else {
        // Insert new user
        $query = "INSERT INTO users (username, password, full_name, role, clinic_id) VALUES (?, ?, ?, ?, ?)";
        $insert = $db->prepare($query);
        $result = $insert->execute([$username, $hash, $full_name, $role, $clinic_id]);
        $status = $result ? "✅ CREATED" : "❌ FAILED";
    }
    
    echo "<tr>";
    echo "<td><strong>$username</strong></td>";
    echo "<td>$password</td>";
    echo "<td>$status</td>";
    echo "<td><code>" . substr($hash, 0, 30) . "...</code></td>";
    echo "</tr>";
}

echo "</table>";

// Verify the hashes work
echo "<h3>🔍 Verification Test:</h3>";
echo "<table border='1' cellpadding='8'>";
echo "<tr><th>Username</th><th>Test Password</th><th>Verification</th></tr>";

$test_users = ['admin', 'reg_staff', 'lab_tech', 'dentist'];
foreach ($test_users as $test_user) {
    $query = "SELECT password FROM users WHERE username = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$test_user]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        $test_password = ($test_user == 'admin') ? 'admin123' : 'clinic123';
        $valid = password_verify($test_password, $user['password']);
        $result = $valid ? "✅ VALID" : "❌ INVALID";
    } else {
        $result = "❌ USER NOT FOUND";
    }
    
    echo "<tr>";
    echo "<td>$test_user</td>";
    echo "<td>" . (($test_user == 'admin') ? 'admin123' : 'clinic123') . "</td>";
    echo "<td>$result</td>";
    echo "</tr>";
}

echo "</table>";

echo "<h3>📋 Next Steps:</h3>";
echo "<ol>";
echo "<li>Run this script to fix all passwords</li>";
echo "<li>Try logging in with the credentials above</li>";
echo "<li>If still issues, check the database table structure</li>";
echo "</ol>";
?>