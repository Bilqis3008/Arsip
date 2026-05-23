<?php
require 'config/db.php';
$stmt = $pdo->query("DESCRIBE surat_keluar");
foreach($stmt->fetchAll() as $row) {
    echo $row['Field'] . "\n";
}
