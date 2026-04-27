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

// --- FETCH BIDANG & SEKSI FOR DROPDOWNS ---
$bidang_list = $pdo->query("SELECT * FROM bidang ORDER BY nama_bidang ASC")->fetchAll();
$seksi_list = $pdo->query("SELECT * FROM seksi ORDER BY nama_seksi ASC")->fetchAll();

// --- HANDLE DISPOSITION SUBMISSION ---
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_disposisi'])) {
    $id_bidang = $_POST['id_bidang'];
    $id_seksi = !empty($_POST['id_seksi']) ? $_POST['id_seksi'] : null;
    $isi_disposisi = $_POST['isi_disposisi'];
    $sifat_disposisi = 'biasa'; // Defaulted to biasa
    $tanggal_disposisi = date('Y-m-d H:i:s');

    try {
        $pdo->beginTransaction();

        // 1. Insert into Disposisi Table
        $stmt = $pdo->prepare("INSERT INTO disposisi (id_surat_masuk, nip_pemberi, id_bidang, id_seksi, isi_disposisi, sifat_disposisi, tanggal_disposisi) 
                               VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$id_surat, $nip_kadis, $id_bidang, $id_seksi, $isi_disposisi, $sifat_disposisi, $tanggal_disposisi]);

        // 2. Update Surat Masuk Status & Current Unit Location
        $stmt = $pdo->prepare("UPDATE surat_masuk SET status = 'didispokan', id_bidang = ?, id_seksi = ? WHERE id_surat_masuk = ?");
        $stmt->execute([$id_bidang, $id_seksi, $id_surat]);

        $pdo->commit();
        
        // Notification Logic
        require_once '../shared/notification_helper.php';
        notifyAdminBidang($pdo, $id_bidang, "Disposisi Baru dari Kadin: " . $mail['perihal'], "../admin_perbidang/surat_masuk.php");

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
    <title>Disposisi Surat - Kadis Panel</title>
    <link rel="stylesheet" href="../css/kadin/home.css">
    <link rel="stylesheet" href="../css/kadin/disposisi_surat.css">
    <link rel="stylesheet" href="../css/notifications.css">
</head>
<body>
    <!-- Sidebar (Same as Dashboard) -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <svg class="icon" style="width: 24px; height: 24px; stroke: var(--accent);"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path></svg>
            <h2>KADIS PANEL</h2>
        </div>
        <nav class="sidebar-menu">
            <div class="menu-label">Main Executive</div>
            <a href="home.php" class="menu-item"><svg class="icon" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg> Dashboard</a>
            <div class="menu-label">Disposisi & Agenda</div>
            <a href="surat_masuk.php" class="menu-item"><svg class="icon" viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg> Surat Masuk</a>
            <a href="disposisi_surat.php" class="menu-item active"><svg class="icon" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 1 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg> Disposisi Surat</a>
            <div class="menu-label">Monitoring</div>
            <a href="monitoring_surat.php" class="menu-item"><svg class="icon" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg> Monitoring Alur</a>
            <a href="laporan.php" class="menu-item"><svg class="icon" viewBox="0 0 24 24"><line x1="18" y1="20" x2="18" y2="10"></line><line x1="12" y1="20" x2="12" y2="4"></line><line x1="6" y1="20" x2="6" y2="14"></line></svg> Laporan</a>
            <div class="menu-label">System</div>
            <a href="profil.php" class="menu-item"><svg class="icon" viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg> Profil Saya</a>
        </nav>
        <div class="sidebar-footer">
            <a href="../auth/logout.php" class="logout-btn"><svg class="icon" viewBox="0 0 24 24" style="stroke: #fda4af;"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg> Logout Sesi</a>
        </div>
    </aside>

    <main class="main-content">
        <header class="content-header">
            <div class="header-title"><h1>Input Instruksi Disposisi</h1></div>
        </header>

        <div class="content-body">
            <?php if ($message): ?><div style="padding: 1rem; background: #dcfce7; color: #15803d; border-radius: 1rem; margin-bottom: 1.5rem; font-weight: 700;"><?= $message ?></div><?php endif; ?>
            <?php if ($error): ?><div style="padding: 1rem; background: #fee2e2; color: #b91c1c; border-radius: 1rem; margin-bottom: 1.5rem; font-weight: 700;"><?= $error ?></div><?php endif; ?>

            <div class="dispo-container">
                <!-- Left: Document Details -->
                <div class="card-doc">
                    <div class="doc-header">
                        <h2><?= htmlspecialchars($mail['perihal']) ?></h2>
                        <span class="badge"><?= htmlspecialchars($mail['status']) ?></span>
                    </div>
                    <div class="doc-meta-grid" style="border-bottom: none;">
                        <div class="meta-item"><label>Nomor Surat</label><span><?= htmlspecialchars($mail['nomor_surat']) ?></span></div>
                        <div class="meta-item"><label>Pengirim</label><span><?= htmlspecialchars($mail['pengirim']) ?></span></div>
                        <div class="meta-item"><label>Tanggal Surat</label><span><?= date('d M Y', strtotime($mail['tanggal_surat'])) ?></span></div>
                        <div class="meta-item"><label>Sifat Surat</label><span style="color: <?= $mail['sifat_surat'] === 'biasa' ? 'var(--text-muted)' : 'var(--danger)' ?>;"><?= ucfirst($mail['sifat_surat']) ?></span></div>
                    </div>
                </div>

                <!-- Right: Form -->
                <div class="card-form">
                    <div class="form-title">
                        <svg class="icon"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path></svg>
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
                            <svg class="icon" style="stroke: var(--accent);"><polyline points="20 6 9 17 4 12"></polyline></svg> Simpan & Teruskan
                        </button>
                    </form>
                    <?php else: ?>
                        <div style="text-align: center; padding: 2rem; border-radius: 1rem; background: #f8fafc; border: 1px solid var(--border);">
                            <svg class="icon" style="width: 48px; height: 48px; color: var(--success); margin-bottom: 1rem;"><circle cx="12" cy="12" r="10"></circle><polyline points="12 8 12 12 16 14"></polyline></svg>
                            <p style="font-weight: 700; color: var(--text-main);">Surat Ini Sudah Didisposisi</p>
                            <p style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.5rem;">Cek riwayat di menu Monitoring untuk melihat perkembangan tindak lanjut.</p>
                            <a href="monitoring_surat.php?id=<?= $id_surat ?>" class="btn-submit" style="margin-top: 1.5rem; text-decoration: none;">Lihat Monitoring</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
    <script src="../js/notifications.js"></script>
</body>
</html>
