<?php
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

echo "<h2>🔧 Complete User Fix Script</h2>";

// First, let's check the table structure
echo "<h3>📊 Current Table Structure:</h3>";
$query = "DESCRIBE users";
$stmt = $db->query($query);
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<table border='1' cellpadding='5'>";
echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
foreach ($columns as $column) {
    echo "<tr>";
    echo "<td>" . $column['Field'] . "</td>";
    echo "<td>" . $column['Type'] . "</td>";
    echo "<td>" . $column['Null'] . "</td>";
    echo "<td>" . $column['Key'] . "</td>";
    echo "<td>" . ($column['Default'] ?? 'NULL') . "</td>";
    echo "</tr>";
}
echo "</table>";

// Now fix all users with correct data
echo "<h3>🔄 Fixing Users:</h3>";

// First, clear existing non-admin users (optional)
// $db->exec("DELETE FROM users WHERE username != 'admin'");

// Define users with ALL fields properly set
$users = [
    ['admin', 'admin123', 'System Administrator', 'admin', NULL],
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

echo "<table border='1' cellpadding='8' style='border-collapse: collapse; width: 100%;'>";
echo "<tr>
        <th>Username</th>
        <th>Password</th>
        <th>Full Name</th>
        <th>Role</th>
        <th>Clinic ID</th>
        <th>Status</th>
        <th>Hash Used</th>
      </tr>";

foreach ($users as $user) {
    $username = $user[0];
    $plain_password = $user[1];
    $full_name = $user[2];
    $role = $user[3];
    $clinic_id = $user[4];
    
    // Generate a NEW hash (different from the one we were using)
    $hash = password_hash($plain_password, PASSWORD_DEFAULT);
    
    // Check if user exists
    $check = "SELECT id FROM users WHERE username = ?";
    $stmt = $db->prepare($check);
    $stmt->execute([$username]);
    
    if ($stmt->rowCount() > 0) {
        // Update existing user with ALL fields
        $query = "UPDATE users SET 
                  password = ?, 
                  full_name = ?, 
                  role = ?, 
                  clinic_id = ? 
                  WHERE username = ?";
        $update = $db->prepare($query);
        $result = $update->execute([$hash, $full_name, $role, $clinic_id, $username]);
        $status = $result ? "✅ UPDATED" : "❌ UPDATE FAILED";
    } else {
        // Insert new user
        $query = "INSERT INTO users (username, password, full_name, role, clinic_id) 
                  VALUES (?, ?, ?, ?, ?)";
        $insert = $db->prepare($query);
        $result = $insert->execute([$username, $hash, $full_name, $role, $clinic_id]);
        $status = $result ? "✅ CREATED" : "❌ INSERT FAILED";
    }
    
    echo "<tr>";
    echo "<td><strong>$username</strong></td>";
    echo "<td>$plain_password</td>";
    echo "<td>$full_name</td>";
    echo "<td><span style='color: blue;'>$role</span></td>";
    echo "<td>" . ($clinic_id ?? 'NULL') . "</td>";
    echo "<td>$status</td>";
    echo "<td><code>" . substr($hash, 0, 30) . "...</code></td>";
    echo "</tr>";
}

echo "</table>";

// Verify the fixes
echo "<h3>✅ Verification:</h3>";
echo "<table border='1' cellpadding='8'>";
echo "<tr><th>Username</th><th>Test Password</th><th>Verification</th><th>Role</th><th>Clinic</th></tr>";

$test_users = ['admin', 'reg_staff', 'lab_tech', 'dentist', 'vital_staff'];
foreach ($test_users as $test_user) {
    $query = "SELECT u.*, c.name as clinic_name 
              FROM users u
              LEFT JOIN clinics c ON u.clinic_id = c.id
              WHERE u.username = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$test_user]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        $test_password = ($test_user == 'admin') ? 'admin123' : 'clinic123';
        $valid = password_verify($test_password, $user['password']);
        $result = $valid ? "✅ VALID" : "❌ INVALID";
        $role_display = $user['role'] ? "<span style='color: green;'>" . $user['role'] . "</span>" : "<span style='color: red;'>MISSING</span>";
        $clinic_display = $user['clinic_name'] ?? 'None';
    } else {
        $result = "❌ NOT FOUND";
        $role_display = "N/A";
        $clinic_display = "N/A";
    }
    
    echo "<tr>";
    echo "<td>$test_user</td>";
    echo "<td>" . (($test_user == 'admin') ? 'admin123' : 'clinic123') . "</td>";
    echo "<td>$result</td>";
    echo "<td>$role_display</td>";
    echo "<td>$clinic_display</td>";
    echo "</tr>";
}

echo "</table>";

echo "<h3>📋 Next Steps:</h3>";
echo "<ol>";
echo "<li>Run this script to fix all users</li>";
echo "<li>Try logging in again with the credentials</li>";
echo "<li>If still issues, check that the role field accepts the values we're inserting</li>";
echo "</ol>";
?>