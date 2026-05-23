<?php
require_once 'config/db.php';
$ids = [6, 7, 8];
foreach($ids as $id) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM seksi WHERE id_bidang = ?");
    $stmt->execute([$id]);
    echo "BIDANG $id SEKSI COUNT: " . $stmt->fetchColumn() . "\n";
}
