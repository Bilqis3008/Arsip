<?php
require_once 'config/db.php';
$stmt = $pdo->query("DESCRIBE disposisi");
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo $row['Field'] . " - " . $row['Type'] . "\n";
}
