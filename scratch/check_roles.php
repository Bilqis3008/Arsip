<?php
require 'config/db.php';
echo "ROLES:\n";
$stmt = $pdo->query('SELECT DISTINCT role FROM users');
print_r($stmt->fetchAll());
?>
