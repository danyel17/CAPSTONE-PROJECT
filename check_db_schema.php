<?php
/**
 * Check Database Schema - Find the correct password column name
 */

require_once 'config.php';

$conn = getDBConnection();

echo "<h2>Users Table Schema</h2>";
$result = $conn->query("DESCRIBE users");
echo "<table border='1' style='border-collapse: collapse;'>";
echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
while($row = $result->fetch_assoc()) {
    echo "<tr>";
    echo "<td>" . htmlspecialchars($row['Field']) . "</td>";
    echo "<td>" . htmlspecialchars($row['Type']) . "</td>";
    echo "<td>" . htmlspecialchars($row['Null']) . "</td>";
    echo "<td>" . htmlspecialchars($row['Key']) . "</td>";
    echo "<td>" . htmlspecialchars($row['Default'] ?? 'NULL') . "</td>";
    echo "</tr>";
}
echo "</table>";

// Check current password for the email
$email = 'danielmantesprado@gmail.com';
echo "<h2>Current User Data for: $email</h2>";
$stmt = $conn->prepare("SELECT user_id, email, password_hash, full_name FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $user = $result->fetch_assoc();
    echo "<p><strong>User ID:</strong> " . htmlspecialchars($user['user_id']) . "</p>";
    echo "<p><strong>Email:</strong> " . htmlspecialchars($user['email']) . "</p>";
    echo "<p><strong>Full Name:</strong> " . htmlspecialchars($user['full_name']) . "</p>";
    echo "<p><strong>Password Hash (first 50 chars):</strong> " . htmlspecialchars(substr($user['password_hash'], 0, 50)) . "...</p>";
} else {
    echo "<p>User not found!</p>";
}

$stmt->close();
$conn->close();
?>






