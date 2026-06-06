<?php
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

echo "<h2>✅ Final Verification</h2>";

// Check if 'staff' is now in ENUM
$query = "SHOW COLUMNS FROM users WHERE Field = 'role'";
$result = $db->query($query);
$column = $result->fetch(PDO::FETCH_ASSOC);

echo "<h3>📊 Role Column Definition:</h3>";
echo "<pre>";
print_r($column);
echo "</pre>";

// Show all users with their roles
$query = "SELECT u.*, c.name as clinic_name 
          FROM users u
          LEFT JOIN clinics c ON u.clinic_id = c.id
          ORDER BY u.id";

$users = $db->query($query)->fetchAll(PDO::FETCH_ASSOC);

echo "<h3>👥 All Users:</h3>";
echo "<table border='1' cellpadding='8' style='border-collapse: collapse;'>";
echo "<tr>
        <th>ID</th>
        <th>Username</th>
        <th>Full Name</th>
        <th>Role</th>
        <th>Clinic</th>
        <th>Login Test</th>
      </tr>";

foreach ($users as $user) {
    // Test password verification
    $test_password = ($user['username'] == 'admin') ? 'admin123' : 'clinic123';
    $valid = password_verify($test_password, $user['password']);
    $login_test = $valid ? "✅ Works" : "❌ Failed";
    
    // Highlight if role is missing
    $role_display = $user['role'] 
        ? "<span style='color: green; font-weight: bold;'>" . $user['role'] . "</span>" 
        : "<span style='color: red; font-weight: bold;'>MISSING</span>";
    
    echo "<tr>";
    echo "<td>" . $user['id'] . "</td>";
    echo "<td><strong>" . $user['username'] . "</strong></td>";
    echo "<td>" . $user['full_name'] . "</td>";
    echo "<td>" . $role_display . "</td>";
    echo "<td>" . ($user['clinic_name'] ?? 'N/A') . "</td>";
    echo "<td>" . $login_test . "</td>";
    echo "</tr>";
}
echo "</table>";

echo "<h3>🔑 Login Credentials:</h3>";
echo "<ul>";
echo "<li><strong>admin</strong> / admin123</li>";
echo "<li><strong>reg_staff</strong> / clinic123</li>";
echo "<li><strong>vital_staff</strong> / clinic123</li>";
echo "<li><strong>lab_tech</strong> / clinic123</li>";
echo "<li><strong>xray_tech</strong> / clinic123</li>";
echo "<li><strong>ecg_tech</strong> / clinic123</li>";
echo "<li><strong>dentist</strong> / clinic123</li>";
echo "<li><strong>optometrist</strong> / clinic123</li>";
echo "<li><strong>dr_general</strong> / clinic123</li>";
echo "<li><strong>nurse_reg</strong> / clinic123</li>";
echo "<li><strong>nurse_lab</strong> / clinic123</li>";
echo "<li><strong>nurse_ecg</strong> / clinic123</li>";
echo "</ul>";

echo "<p><a href='index.php' target='_blank'><button>Go to Login Page</button></a></p>";
?>