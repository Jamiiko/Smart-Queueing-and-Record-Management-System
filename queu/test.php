<?php
echo "<h2>File Structure Test</h2>";

$root = __DIR__;
echo "Root directory: " . $root . "<br><br>";

$files_to_check = [
    'staff/clinic-dashboard.php',
    'staff/patient-queue.php',
    'staff/registration.php',
    'includes/navbar.php',
    'config/database.php',
    'includes/QueueManager.php'
];

echo "<ul>";
foreach ($files_to_check as $file) {
    $full_path = $root . '/' . $file;
    if (file_exists($full_path)) {
        echo "<li style='color: green;'>✅ $file - Found</li>";
    } else {
        echo "<li style='color: red;'>❌ $file - NOT FOUND at: $full_path</li>";
    }
}
echo "</ul>";
?>