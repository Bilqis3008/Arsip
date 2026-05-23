<?php
require_once 'config/db.php';
$stmt = $pdo->query("SELECT MAX(id_bidang) FROM bidang");
echo "MAX ID: " . $stmt->fetchColumn() . "\n";
