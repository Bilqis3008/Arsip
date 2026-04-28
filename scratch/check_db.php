<?php
require 'config/db.php';
echo "BIDANG:\n";
$stmt = $pdo->query('SELECT * FROM bidang');
print_r($stmt->fetchAll());
echo "\nSEKSI:\n";
$stmt = $pdo->query('SELECT * FROM seksi');
print_r($stmt->fetchAll());
?>
