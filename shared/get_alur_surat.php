<?php
require_once '../config/db.php';

$id = $_GET['id'] ?? null;
$type = $_GET['type'] ?? 'masuk'; // masuk | keluar

if (!$id) {
    echo json_encode([]);
    exit;
}

$history = [];

if ($type === 'masuk') {
    // 1. Original Record (Sekretariat)
    $stmt = $pdo->prepare("SELECT created_at as tanggal, 'Sekretariat' as aktor, 'Surat Masuk Diterima & Dicatat' as activity FROM surat_masuk WHERE id_surat_masuk = ?");
    $stmt->execute([$id]);
    if ($row = $stmt->fetch()) {
        $history[] = $row;
    }

    // 2. Dispositions (Chain of Command)
    $stmt = $pdo->prepare("SELECT d.tanggal_disposisi as tanggal, u.nama as aktor, d.isi_disposisi as activity 
                           FROM disposisi d 
                           JOIN users u ON d.nip_pemberi = u.nip 
                           WHERE d.id_surat_masuk = ? 
                           ORDER BY d.tanggal_disposisi ASC");
    $stmt->execute([$id]);
    while ($row = $stmt->fetch()) {
        $history[] = $row;
    }

    // 3. Final Reply (If any)
    $stmt = $pdo->prepare("SELECT created_at as tanggal, 'Staff / Seksi' as aktor, CONCAT('Surat Balasan Dibuat (No: ', nomor_surat_keluar, ')') as activity 
                           FROM surat_keluar WHERE id_surat_masuk = ?");
    $stmt->execute([$id]);
    if ($row = $stmt->fetch()) {
        $history[] = $row;
    }
} else {
    // For Outgoing Mail
    $stmt = $pdo->prepare("SELECT sk.created_at as tanggal, u.nama as aktor, 'Surat Keluar Dibuat / Draft' as activity 
                           FROM surat_keluar sk JOIN users u ON sk.uploaded_by = u.nip WHERE sk.id_surat_keluar = ?");
    $stmt->execute([$id]);
    if ($row = $stmt->fetch()) {
        $history[] = $row;
    }
    
    $stmt = $pdo->prepare("SELECT updated_at as tanggal, 'Admin Bidang' as aktor, 'Surat Disetujui & Diarsipkan' as activity 
                           FROM surat_keluar WHERE id_surat_keluar = ? AND status = 'diarsipkan'");
    $stmt->execute([$id]);
    if ($row = $stmt->fetch()) {
        $history[] = $row;
    }
}

header('Content-Type: application/json');
echo json_encode($history);
