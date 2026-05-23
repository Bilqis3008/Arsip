<?php
require_once 'config/db.php';
$stmt = $pdo->prepare("SELECT * FROM bidang WHERE id_bidang = 8");
$stmt->execute();
$row = $stmt->fetch();
if ($row) {
    echo "ID 8 EXISTS: " . $row['nama_bidang'] . "\n";
} else {
    echo "ID 8 DOES NOT EXIST\n";
}
