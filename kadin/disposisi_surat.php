<?php
session_start();
require_once '../config/db.php';

// Auth Check for Kepala Dinas
if (!isset($_SESSION['user_nip']) || $_SESSION['user_role'] !== 'kepala_dinas') {
    header('Location: ../auth/login.php');
    exit;
}

$nip_kadis = $_SESSION['user_nip'];
$id_surat = $_GET['id'] ?? null;

if (!$id_surat) {
    header('Location: surat_masuk.php');
    exit;
}

// --- FETCH LETTER DETAILS ---
$stmt = $pdo->prepare("SELECT * FROM surat_masuk WHERE id_surat_masuk = ?");
$stmt->execute([$id_surat]);
$mail = $stmt->fetch();

if (!$mail) {
    header('Location: surat_masuk.php');
    exit;
}

// --- FETCH BIDANG FOR DROPDOWN ---
$bidang_list = $pdo->query("SELECT * FROM bidang ORDER BY nama_bidang ASC")->fetchAll();

// --- HANDLE DISPOSITION SUBMISSION ---
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_disposisi'])) {
    $id_bidang = $_POST['id_bidang'];
    $isi_disposisi = $_POST['isi_disposisi'];
    $sifat_disposisi = 'biasa'; // Defaulted to biasa
    $tanggal_disposisi = date('Y-m-d H:i:s');

    try {
        $pdo->beginTransaction();

        // 1. Insert into Disposisi Table (id_seksi is null as Kadin only chooses Bidang)
        $stmt = $pdo->prepare("INSERT INTO disposisi (id_surat_masuk, nip_pemberi, id_bidang, id_seksi, isi_disposisi, sifat_disposisi, tanggal_disposisi) 
                               VALUES (?, ?, ?, NULL, ?, ?, ?)");
        $stmt->execute([$id_surat, $nip_kadis, $id_bidang, $isi_disposisi, $sifat_disposisi, $tanggal_disposisi]);

        // 2. Update Surat Masuk Status & Current Unit Location
        $stmt = $pdo->prepare("UPDATE surat_masuk SET status = 'didispokan', id_bidang = ?, id_seksi = NULL WHERE id_surat_masuk = ?");
        $stmt->execute([$id_bidang, $id_surat]);

        $pdo->commit();
        
        // Notification Logic
        require_once '../shared/notification_helper.php';
        $notif_link = ($id_bidang == 8) ? "../sekretariat/surat_masuk.php" : "../admin_perbidang/surat_masuk.php";
        notifyAdminBidang($pdo, $id_bidang, "Disposisi Baru dari Kadin: " . $mail['perihal'], $notif_link);

        $message = "Disposisi berhasil disimpan dan diteruskan ke Bidang terkait.";
        // Refresh mail data to show updated status
        $mail['status'] = 'didispokan';
    } catch (PDOException $e) {
        $pdo->rollBack();
        $error = "Terjadi kesalahan: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Disposisi Surat - Kepala Dinas</title>
    <link rel="stylesheet" href="../css/kadin/home.css">
    <link rel="stylesheet" href="../css/kadin/disposisi_surat.css">
    <link rel="stylesheet" href="../css/notifications.css">
</head>
<body>
    <!-- Sidebar (Same as Dashboard) -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
            <h2>KEPALA DINAS</h2>
        </div>
        <nav class="sidebar-menu">
            <div class="menu-label">Main Executive</div>
            <a href="home.php" class="menu-item"><svg class="icon" viewBox="0 0 24 24"><rect width="7" height="7" x="3" y="3" rx="1"/><rect width="7" height="7" x="14" y="3" rx="1"/><rect width="7" height="7" x="14" y="14" rx="1"/><rect width="7" height="7" x="3" y="14" rx="1"/></svg> Dashboard</a>
            <div class="menu-label">Disposisi & Agenda</div>
            <a href="surat_masuk.php" class="menu-item"><svg class="icon" viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg> Surat Masuk</a>
            <a href="disposisi_surat.php" class="menu-item active"><svg class="icon" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 1 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg> Disposisi Surat</a>
            <div class="menu-label">Monitoring</div>
            <a href="monitoring_surat.php" class="menu-item"><svg class="icon" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="m12 8 0 4 2 2"/></svg> Monitoring Alur</a>
            <a href="laporan.php" class="menu-item"><svg class="icon" viewBox="0 0 24 24"><path d="M3 3v18h18"/><path d="m19 9-5 5-4-4-3 3"/></svg> Laporan</a>
            <div class="menu-label">System</div>
            <a href="profil.php" class="menu-item"><svg class="icon" viewBox="0 0 24 24"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg> Profil Saya</a>
        </nav>
        <div class="sidebar-footer">
            <a href="../auth/logout.php" class="logout-btn"><svg class="icon" viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5"/><path d="M21 12H9"/></svg> Logout</a>
        </div>
    </aside>

    <main class="main-content">
        <header class="content-header">
            <div class="header-title"><h1>Input Instruksi Disposisi</h1></div>
        </header>

        <div class="content-body">
            <?php if ($message): ?><div class="alert-success"><?= $message ?></div><?php endif; ?>
            <?php if ($error): ?><div class="alert-danger"><?= $error ?></div><?php endif; ?>

            <div class="dispo-container">
                <!-- Left: Document Details -->
                <div class="card-doc">
                    <div class="doc-header">
                        <h2><?= htmlspecialchars($mail['perihal']) ?></h2>
                        <span class="badge"><?= htmlspecialchars($mail['status']) ?></span>
                    </div>
                    <div class="doc-meta-grid meta-no-border">
                        <div class="meta-item"><label>Nomor Surat</label><span><?= htmlspecialchars($mail['nomor_surat']) ?></span></div>
                        <div class="meta-item"><label>Pengirim</label><span><?= htmlspecialchars($mail['pengirim']) ?></span></div>
                        <div class="meta-item"><label>Tanggal Surat</label><span><?= date('d M Y', strtotime($mail['tanggal_surat'])) ?></span></div>
                        <div class="meta-item"><label>Sifat Surat</label><span style="color: <?= $mail['sifat_surat'] === 'biasa' ? 'var(--text-muted)' : 'var(--danger)' ?>;"><?= ucfirst($mail['sifat_surat']) ?></span></div>
                    </div>
                </div>

                <!-- Right: Form -->
                <div class="card-form">
                    <div class="form-title">
                        <svg class="icon" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 1 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                        <h3>Lanjutkan Instruksi</h3>
                    </div>
                    <?php if ($mail['status'] === 'tercatat'): ?>
                    <form action="" method="POST">
                        <div class="form-group">
                            <label>Tujuan Bidang / Bagian</label>
                            <select name="id_bidang" id="id_bidang" required>
                                <option value="">-- Pilih Bidang --</option>
                                <?php foreach ($bidang_list as $b): ?>
                                    <option value="<?= $b['id_bidang'] ?>"><?= htmlspecialchars($b['nama_bidang']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Isi Instruksi / Catatan (Opsional)</label>
                            <textarea name="isi_disposisi" placeholder="Tulis instruksi tindak lanjut di sini..."></textarea>
                        </div>
                        <button type="submit" name="submit_disposisi" class="btn-submit">
                            <svg class="icon" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"></polyline></svg> Simpan & Teruskan
                        </button>
                    </form>
                    <?php else: ?>
                        <div class="processed-alert">
                            <svg class="icon processed-alert-icon" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/></svg>
                            <p class="processed-alert-title">Surat Ini Sudah Didisposisi</p>
                            <p class="processed-alert-subtitle">Cek riwayat di menu Monitoring untuk melihat perkembangan tindak lanjut.</p>
                            <a href="monitoring_surat.php?id=<?= $id_surat ?>" class="btn-submit processed-alert-btn">Lihat Monitoring</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
    <script src="../js/notifications.js"></script>
</body>
</html>
