<?php
require 'config/db.php';
$stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'nip'");
print_r($stmt->fetch());
