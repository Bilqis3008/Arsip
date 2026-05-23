<?php
require_once 'config/db.php';
$stmt = $pdo->query("SELECT DISTINCT role FROM users");
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo $row['role'] . "\n";
}
