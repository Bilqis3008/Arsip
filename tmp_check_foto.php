<?php
require_once 'c:/laragon/www/Arsip/config/db.php';
$stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'foto'");
$row = $stmt->fetch(PDO::FETCH_ASSOC);
print_r($row);
