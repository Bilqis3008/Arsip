<?php
$file = 'c:/laragon/www/Arsip/sekretariat/surat_masuk.php';
$content = file_get_contents($file);

$start_marker = '$all_mails = $stmt->fetchAll();';
$end_marker = '// Pre-calculate next agenda number';

$pos_start = strpos($content, $start_marker);
$pos_end = strpos($content, $end_marker);

if ($pos_start !== false && $pos_end !== false) {
    $head = substr($content, 0, $pos_start + strlen($start_marker));
    $tail = substr($content, $pos_end);
    
    $middle = <<<'EOD'

$mails = [];
$riwayat_mails = [];
foreach ($all_mails as $m) {
    if ($m['status'] === 'selesai') {
        $riwayat_mails[] = $m;
    } else {
        $mails[] = $m;
    }
}

// --- FETCH SURAT TUGAS (DISPOSISI KADIN TO SEKRETARIAT UMUM) ---
$id_bidang_sekretariat = 8;
$query_tugas = "SELECT sm.*, d.isi_disposisi as instruksi_kadin, d.tanggal_disposisi as tgl_dispo_kadin, d.id_disposisi
              FROM surat_masuk sm 
              JOIN disposisi d ON sm.id_surat_masuk = d.id_surat_masuk
              WHERE d.id_bidang = ? 
              AND d.nip_pemberi IN (SELECT nip FROM users WHERE role = 'kepala_dinas')
              AND sm.status IN ('didispokan', 'diteruskan')
              AND (sm.perihal LIKE ? OR sm.nomor_surat LIKE ? OR sm.pengirim LIKE ?)
              ORDER BY sm.created_at DESC";
$stmt_tugas = $pdo->prepare($query_tugas);
$stmt_tugas->execute([$id_bidang_sekretariat, "%$search%", "%$search%", "%$search%"]);
$tugas_mails = $stmt_tugas->fetchAll();

// --- HANDLE ACTION: ARSIP TUGAS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'archive_tugas') {
    $id_target = $_POST['id_surat'];
    try {
        $stmt = $pdo->prepare("UPDATE surat_masuk SET status = 'selesai', id_bidang = ? WHERE id_surat_masuk = ?");
        $stmt->execute([$id_bidang_sekretariat, $id_target]);
        $_SESSION['success_msg'] = "Surat berhasil diarsipkan ke Sekretariat Umum.";
    } catch (PDOException $e) {
        $_SESSION['error_msg'] = "Gagal mengarsipkan: " . $e->getMessage();
    }
    header("Location: surat_masuk.php");
    exit;
}

EOD;

    file_put_contents($file, $head . $middle . $tail);
    echo "Successfully cleaned up surat_masuk.php fetch logic";
} else {
    echo "Markers not found!";
}
?>
