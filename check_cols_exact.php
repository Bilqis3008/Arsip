<?php
require_once 'c:/laragon/www/Arsip/config/db.php';
$stmt = $pdo->query("SHOW COLUMNS FROM users");
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo $row['Field'] . "\n";
}
