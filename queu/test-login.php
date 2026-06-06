<?php
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

echo "<h2>🔐 Login Test Page</h2>";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];
    
    $query = "SELECT u.*, c.name as clinic_name 
              FROM users u
              LEFT JOIN clinics c ON u.clinic_id = c.id
              WHERE u.username = :username";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':username', $username);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo "<h3>User Found:</h3>";
        echo "<pre>";
        print_r($user);
        echo "</pre>";
        
        if (password_verify($password, $user['password'])) {
            echo "<p style='color: green; font-weight: bold;'>✅ Password VERIFIED successfully!</p>";
        } else {
            echo "<p style='color: red; font-weight: bold;'>❌ Password verification FAILED!</p>";
            echo "<p>Attempted password: " . htmlspecialchars($password) . "</p>";
            echo "<p>Stored hash: " . $user['password'] . "</p>";
        }
    } else {
        echo "<p style='color: red;'>❌ User not found: $username</p>";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Login Test</title>
    <style>
        body { font-family: Arial; padding: 20px; }
        .container { max-width: 500px; margin: 0 auto; }
        input, button { padding: 8px; margin: 5px; width: 100%; }
    </style>
</head>
<body>
    <div class="container">
        <h3>Test Login Credentials:</h3>
        <form method="POST">
            <input type="text" name="username" placeholder="Username" required>
            <input type="password" name="password" placeholder="Password" required>
            <button type="submit">Test Login</button>
        </form>
        
        <hr>
        <h4>Available Test Credentials:</h4>
        <ul>
            <li><strong>admin</strong> / admin123</li>
            <li><strong>reg_staff</strong> / clinic123</li>
            <li><strong>lab_tech</strong> / clinic123</li>
            <li><strong>dentist</strong> / clinic123</li>
        </ul>
    </div>
</body>
</html>
