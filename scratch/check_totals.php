<?php
require_once 'config/db.php';
$id_bidang = 2;
$stmt = $pdo->prepare("SELECT COUNT(*) FROM surat_masuk WHERE status IN ('selesai', 'diarsipkan') AND id_bidang = ?");
$stmt->execute([$id_bidang]);
$total_m = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM surat_keluar sk JOIN users u ON sk.uploaded_by = u.nip WHERE sk.status = 'diarsipkan' AND u.id_bidang = ?");
$stmt->execute([$id_bidang]);
$total_k = $stmt->fetchColumn();

echo "Masuk: $total_m, Keluar: $total_k";
?>
