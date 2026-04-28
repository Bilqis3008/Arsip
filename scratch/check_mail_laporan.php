<?php
require_once 'config/db.php';
$stmt = $pdo->query('SELECT id_surat_masuk, perihal, status, id_bidang, id_seksi, perlu_balasan FROM surat_masuk WHERE perihal LIKE "%Laporan%"');
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
?>
