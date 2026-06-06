<?php
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

// Check if admin exists
$query = "SELECT * FROM users WHERE username = 'admin'";
$stmt = $db->query($query);
$admin = $stmt->fetch(PDO::FETCH_ASSOC);

if ($admin) {
    echo "Admin user found. Current password hash: " . $admin['password'] . "<br><br>";
    
    // Test password verification
    $test_password = 'admin123';
    if (password_verify($test_password, $admin['password'])) {
        echo "✅ Password 'admin123' works with current hash!";
    } else {
        echo "❌ Password 'admin123' does NOT work with current hash.<br>";
        echo "Creating new hash for 'admin123'...<br>";
        
        $new_hash = password_hash('admin123', PASSWORD_DEFAULT);
        echo "New hash: " . $new_hash . "<br><br>";
        
        // Update the password
        $update = "UPDATE users SET password = :password WHERE username = 'admin'";
        $update_stmt = $db->prepare($update);
        $update_stmt->bindParam(':password', $new_hash);
        
        if ($update_stmt->execute()) {
            echo "✅ Password updated successfully! You can now login with username: admin and password: admin123";
        } else {
            echo "❌ Failed to update password";
        }
    }
} else {
    echo "Admin user not found. Creating new admin user...<br>";
    
    $hash = password_hash('admin123', PASSWORD_DEFAULT);
    $query = "INSERT INTO users (username, password, full_name, role) VALUES ('admin', :password, 'System Administrator', 'admin')";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':password', $hash);
    
    if ($stmt->execute()) {
        echo "✅ Admin user created successfully! You can now login with username: admin and password: admin123";
    } else {
        echo "❌ Failed to create admin user";
    }
}
?>