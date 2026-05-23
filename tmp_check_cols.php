<?php
require_once 'c:/laragon/www/Arsip/config/db.php';
$stmt = $pdo->query("SELECT * FROM users LIMIT 1");
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if ($row) {
    echo implode(", ", array_keys($row));
} else {
    echo "TABLE IS EMPTY";
}
