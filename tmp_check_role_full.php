<?php
require 'config/db.php';
$stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'role'");
$row = $stmt->fetch();
echo "Type: " . $row['Type'] . "\n";
