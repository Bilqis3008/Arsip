<?php
require_once 'config/db.php';

try {
    $pdo->beginTransaction();
    
    // 1. Update status from 'diteruskan' to 'selesai' for Sekretariat (Bidang 8)
    // This makes them appear in reports.
    $stmt1 = $pdo->prepare("UPDATE surat_masuk SET status = 'selesai' WHERE id_bidang = 8 AND status = 'diteruskan'");
    $stmt1->execute();
    $count1 = $stmt1->rowCount();
    
    // 2. Set perlu_balasan = 1 for all mail that has a disposition to staff in Bidang 8
    // This makes them appear in staff task list.
    $stmt2 = $pdo->prepare("UPDATE surat_masuk sm
                           JOIN disposisi d ON sm.id_surat_masuk = d.id_surat_masuk
                           SET sm.perlu_balasan = 1
                           WHERE d.id_bidang = 8 AND d.nip_penerima IS NOT NULL");
    $stmt2->execute();
    $count2 = $stmt2->rowCount();
    
    $pdo->commit();
    echo "Migration Success:\n";
    echo "- Updated $count1 mails status to 'selesai'.\n";
    echo "- Enabled 'perlu_balasan' for $count2 mails.\n";
} catch (Exception $e) {
    $pdo->rollBack();
    echo "Migration Failed: " . $e->getMessage();
}
?>
