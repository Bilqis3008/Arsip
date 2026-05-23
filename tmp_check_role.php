<?php
require 'config/db.php';
$stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'role'");
print_r($stmt->fetch());
