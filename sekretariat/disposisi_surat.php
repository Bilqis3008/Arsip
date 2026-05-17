<?php
session_start();
require_once '../config/db.php';

// Auth Check for Sekretariat
if (!isset($_SESSION['user_nip']) || $_SESSION['user_role'] !== 'sekretariat') {
    header('Location: ../auth/login.php');
    exit;
}

$nip_admin = $_SESSION['user_nip'];
$id_surat = $_GET['id'] ?? null;

if (!$id_surat) {
    header('Location: surat_masuk.php');
    exit;
}

// Bidang ID for Sekretariat Umum
$id_bidang_sekretariat = 8;

// --- FETCH LETTER & KADIN DISPO ---
$stmt = $pdo->prepare("SELECT sm.*, d.isi_disposisi as instruksi_kadin, d.sifat_disposisi, d.tanggal_disposisi as tgl_kadin 
                       FROM surat_masuk sm 
                       JOIN disposisi d ON sm.id_surat_masuk = d.id_surat_masuk 
                       AND d.id_bidang = ?
                       AND d.nip_pemberi IN (SELECT nip FROM users WHERE role = 'kepala_dinas')
                       WHERE sm.id_surat_masuk = ?");
$stmt->execute([$id_bidang_sekretariat, $id_surat]);
$mail = $stmt->fetch();

if (!$mail) {
    header('Location: surat_masuk.php');
    exit;
}

// --- FETCH ALL STAFF IN SEKRETARIAT UMUM ---
$stmt = $pdo->prepare("SELECT nip, nama FROM users WHERE id_bidang = ? AND role = 'staff' ORDER BY nama ASC");
$stmt->execute([$id_bidang_sekretariat]);
$staff_list = $stmt->fetchAll();

// --- HANDLE INTERNAL DISPOSITION ---
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_dispro_internal'])) {
    $nip_penerima = $_POST['nip_penerima']; 
    $isi_disposisi = $_POST['isi_disposisi'];
    $sifat_disposisi = $mail['sifat_disposisi'];
    $tanggal_disposisi = date('Y-m-d H:i:s');

    try {
        $pdo->beginTransaction();

        // 1. Insert New Disposisi (Internal) to Staff
        $stmt = $pdo->prepare("INSERT INTO disposisi (id_surat_masuk, nip_pemberi, id_bidang, nip_penerima, isi_disposisi, sifat_disposisi, tanggal_disposisi) 
                               VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$id_surat, $nip_admin, $id_bidang_sekretariat, $nip_penerima, $isi_disposisi, $sifat_disposisi, $tanggal_disposisi]);

        // 2. Update Surat Masuk Status to 'selesai' (Automatically Archived) and set task for staff
        $stmt = $pdo->prepare("UPDATE surat_masuk SET status = 'selesai', id_bidang = ?, perlu_balasan = 1 WHERE id_surat_masuk = ?");
        $stmt->execute([$id_bidang_sekretariat, $id_surat]);

        $pdo->commit();
        
        // Notification Logic
        require_once '../shared/notification_helper.php';
        $notif_msg = "Instruksi Baru dari Sekretariat (Tugas): " . $mail['perihal'];
        addNotification($pdo, $nip_penerima, $notif_msg, "../staff/surat_masuk.php");

        $message = "Surat Tugas berhasil diteruskan ke Staf dan otomatis diarsipkan.";
        $mail['status'] = 'selesai';
    } catch (PDOException $e) {
        $pdo->rollBack();
        $error = "Gagal memproses disposisi: " . $e->getMessage();
    }
}

// Fetch Admin Info for sidebar
$stmt = $pdo->prepare("SELECT * FROM users WHERE nip = ?");
$stmt->execute([$nip_admin]);
$admin = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Disposisi Tugas - Sekretariat</title>
    <link rel="stylesheet" href="../css/sekretariat/home.css">
    <link rel="stylesheet" href="../css/sekretariat/disposisi_surat.css">
    <link rel="stylesheet" href="../css/notifications.css">
</head>
<body>
    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
            <h2>ARSIP DIGITAL</h2>
        </div>
        <nav class="sidebar-menu">
            <div class="menu-label">Menu Utama</div>
            <a href="home.php" class="menu-item"><svg class="icon" viewBox="0 0 24 24"><rect width="7" height="7" x="3" y="3" rx="1"/><rect width="7" height="7" x="14" y="3" rx="1"/><rect width="7" height="7" x="14" y="14" rx="1"/><rect width="7" height="7" x="3" y="14" rx="1"/></svg> Dashboard</a>
            <div class="menu-label">Buku Agenda</div>
            <a href="surat_masuk.php" class="menu-item active"><svg class="icon" viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg> Surat Masuk</a>
            <a href="surat_keluar.php" class="menu-item"><svg class="icon" viewBox="0 0 24 24"><path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/></svg> Surat Keluar</a>
            <div class="menu-label">Administrasi Sistem</div>
            <a href="manajemen_pengguna.php" class="menu-item"><svg class="icon" viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg> Manajemen Pengguna</a>
            <a href="verifikasi_staff.php" class="menu-item"><svg class="icon" viewBox="0 0 24 24"><path d="M3.85 8.62a4 4 0 0 1 4.78-4.77 4 4 0 0 1 6.74 0 4 4 0 0 1 4.78 4.78 4 4 0 0 1 0 6.74 4 4 0 0 1-4.77 4.78 4 4 0 0 1-6.75 0 4 4 0 0 1-4.78-4.77 4 4 0 0 1 0-6.76Z"/><path d="m9 12 2 2 4-4"/></svg> Verifikasi Staff</a>
            <a href="monitoring_surat.php" class="menu-item"><svg class="icon" viewBox="0 0 24 24"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg> Monitoring Surat</a>
            <div class="menu-label">Monitoring</div>
            <a href="monitoring_laporan.php" class="menu-item"><svg class="icon" viewBox="0 0 24 24"><path d="M3 3v18h18"/><path d="m19 9-5 5-4-4-3 3"/></svg> Laporan</a>
            <div class="menu-label">Akun</div>
            <a href="profil.php" class="menu-item"><svg class="icon" viewBox="0 0 24 24"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg> Profil Saya</a>
        </nav>
        <div class="sidebar-footer"><a href="../auth/logout.php" class="logout-btn"><svg class="icon" viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5"/><path d="M21 12H9"/></svg> Logout</a></div>
    </aside>

    <main class="main-content">
        <header class="content-header">
            <div class="header-title"><h1>Teruskan Surat Tugas ke Staf</h1></div>
            <div class="user-profile">
                <div class="user-info">
                    <span class="user-name"><?= htmlspecialchars($admin['nama']) ?></span>
                    <span class="user-role">Sekretariat</span>
                </div>
                <div class="user-avatar"><?= strtoupper(substr($admin['nama'], 0, 1)) ?></div>
            </div>
        </header>

        <div class="content-body">
            <?php if ($message): ?><div class="alert-success"><?= $message ?></div><?php endif; ?>
            <?php if ($error): ?><div class="alert-danger"><?= $error ?></div><?php endif; ?>

            <div class="dispo-container">
                <!-- Left: Doc & Kadin Inst -->
                <div class="card-doc">
                    <div class="doc-header">
                        <h2><?= htmlspecialchars($mail['perihal']) ?></h2>
                        <span class="badge"><?= htmlspecialchars($mail['status']) ?></span>
                    </div>

                    <?php if ($mail['instruksi_kadin']): ?>
                        <div class="kadin-instruction">
                            <p class="kadin-instruction-text">"<?= nl2br(htmlspecialchars($mail['instruksi_kadin'])) ?>"</p>
                            <div class="kadin-instruction-meta">INSTRUKSI KADIN • <?= date('d M Y H:i', strtotime($mail['tgl_kadin'])) ?></div>
                        </div>
                    <?php endif; ?>

                    <div class="doc-meta-grid">
                        <div class="meta-item"><label>Nomor Surat</label><span><?= htmlspecialchars($mail['nomor_surat']) ?></span></div>
                        <div class="meta-item"><label>Pengirim</label><span><?= htmlspecialchars($mail['pengirim']) ?></span></div>
                        <div class="meta-item"><label>Sifat Surat</label><span class="meta-sifat-text"><?= ucfirst($mail['sifat_surat']) ?></span></div>
                    </div>
                </div>

                <!-- Right: Internal Form -->
                <div class="card-form">
                    <div class="form-title">
                        <svg class="icon" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path></svg>
                        <h3>Tugaskan ke Staf Sekretariat Umum</h3>
                    </div>
                    <?php if ($mail['status'] === 'didispokan'): ?>
                    <form action="" method="POST">
                        <div class="form-group">
                            <label>Pilih Staf Pelaksana</label>
                            <select name="nip_penerima" required>
                                <option value="">-- Pilih Staf Sekretariat Umum --</option>
                                <?php foreach ($staff_list as $s): ?>
                                    <option value="<?= $s['nip'] ?>"><?= htmlspecialchars($s['nama']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Catatan Instruksi (Opsional)</label>
                            <textarea name="isi_disposisi" placeholder="Tulis instruksi tindak lanjut untuk staf..."></textarea>
                        </div>
                        <button type="submit" name="submit_dispro_internal" class="btn-submit">
                            <svg class="icon"><polyline points="20 6 9 17 4 12"></polyline></svg> Kirim Tugas
                        </button>
                    </form>
                    <?php else: ?>
                        <div class="processed-alert">
                            <svg class="icon processed-alert-icon"><circle cx="12" cy="12" r="10"></circle><polyline points="12 8 12 12 16 14"></polyline></svg>
                            <p class="processed-alert-title">Sudah Diteruskan</p>
                            <p class="processed-alert-subtitle">Tugas ini sedang dalam proses tindak lanjut oleh staf.</p>
                            <a href="surat_masuk.php" class="btn-submit processed-alert-btn">Kembali ke Daftar</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
    <script src="../js/notifications.js"></script>
</body>
</html>
