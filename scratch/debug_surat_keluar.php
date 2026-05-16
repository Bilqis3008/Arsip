<?php
require_once 'config/db.php';
$stmt = $pdo->query("SELECT sk.id_surat_keluar, sk.nomor_surat_keluar, sk.tanggal_surat, sk.status, u.nama as pengirim, b.nama_bidang, b.id_bidang
                     FROM surat_keluar sk 
                     JOIN users u ON sk.uploaded_by = u.nip 
                     LEFT JOIN bidang b ON u.id_bidang = b.id_bidang");
$res = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($res, JSON_PRETTY_PRINT);
?>
