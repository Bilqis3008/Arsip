<?php
require_once 'config/db.php';
$stmt1 = $pdo->query("DESCRIBE surat_masuk");
$columns1 = $stmt1->fetchAll();
$stmt2 = $pdo->query("DESCRIBE surat_keluar");
$columns2 = $stmt2->fetchAll();
echo json_encode(['surat_masuk' => $columns1, 'surat_keluar' => $columns2], JSON_PRETTY_PRINT);
