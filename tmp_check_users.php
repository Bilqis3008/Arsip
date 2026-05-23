<?php
require 'config/db.php';
$stmt = $pdo->query("DESCRIBE users");
foreach($stmt->fetchAll() as $row) {
    echo $row['Field'] . "\n";
}
