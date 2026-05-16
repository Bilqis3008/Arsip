<?php
require_once 'config/db.php';
$stmt = $pdo->query("DESCRIBE surat_keluar");
$res = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach($res as $row) {
    echo $row['Field'] . " - " . $row['Type'] . "\n";
}
?>
