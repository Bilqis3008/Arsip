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
    <link rel="stylesheet" href="../css/admin_perbidang/disposisi_surat.css">
    <style>
        .sidebar { background: var(--navy); }
        .menu-item.active { background: rgba(37, 99, 235, 0.1); color: var(--primary); }
    </style>
    <link rel="stylesheet" href="../css/notifications.css">
</head>
<body>
    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <svg class="icon" style="width: 24px; height: 24px;"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path></svg>
            <h2>ARSIP DIGITAL</h2>
        </div>
        
        <nav class="sidebar-menu">
            <div class="menu-label">Menu Utama</div>
            <a href="home.php" class="menu-item">
                <svg class="icon" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
                Dashboard
            </a>
            
            <div class="menu-label">Buku Agenda</div>
            <a href="surat_masuk.php" class="menu-item active">
                <svg class="icon" viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
                Surat Masuk
            </a>
            <a href="surat_keluar.php" class="menu-item">
                <svg class="icon" viewBox="0 0 24 24">
                    <line x1="22" y1="2" x2="11" y2="13"></line>
                    <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
                </svg>
                Surat Keluar
            </a>
            <div class="menu-label">Administrasi Sistem</div>
            <a href="manajemen_pengguna.php" class="menu-item">
                <svg class="icon" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                Manajemen Pengguna
            </a>
            <a href="monitoring_surat.php" class="menu-item">
                <svg class="icon" viewBox="0 0 24 24"><path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"></path><polyline points="14 2 14 8 20 8"></polyline><path d="M16 13a2 2 0 1 1-4 0v-2a2 2 0 1 0-4 0"></path><line x1="12" y1="14" x2="12" y2="19"></line></svg>
                Monitoring Surat
            </a>
            
            <div class="menu-label">Monitoring</div>
            <a href="monitoring_laporan.php" class="menu-item">
                <svg class="icon" viewBox="0 0 24 24"><line x1="18" y1="20" x2="18" y2="10"></line><line x1="12" y1="20" x2="12" y2="4"></line><line x1="6" y1="20" x2="6" y2="14"></line></svg>
                Laporan
            </a>
                        
            <div class="menu-label">Akun</div>
            <a href="profil.php" class="menu-item"><svg class="icon" viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg> Profil Saya</a>
        </nav>

        <div class="sidebar-footer">
            <a href="../auth/logout.php" class="logout-btn">
                <svg class="icon" viewBox="0 0 24 24" style="stroke: #fda4af;"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
                Keluar Sistem
            </a>
        </div>
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
            <?php if ($message): ?><div style="padding: 1rem; background: #dcfce7; color: #15803d; border-radius: 1rem; margin-bottom: 2rem; font-weight: 700;"><?= $message ?></div><?php endif; ?>
            <?php if ($error): ?><div style="padding: 1rem; background: #fee2e2; color: #b91c1c; border-radius: 1rem; margin-bottom: 2rem; font-weight: 700;"><?= $error ?></div><?php endif; ?>

            <div class="dispo-container">
                <!-- Left: Doc & Kadin Inst -->
                <div class="card-doc">
                    <div class="doc-header">
                        <h2><?= htmlspecialchars($mail['perihal']) ?></h2>
                        <span class="badge" style="background: #e0e7ff; color: #4338ca;"><?= htmlspecialchars($mail['status']) ?></span>
                    </div>

                    <?php if ($mail['instruksi_kadin']): ?>
                        <div class="kadin-instruction">
                            <p style="font-weight: 600; color: #92400e; line-height: 1.6;">"<?= nl2br(htmlspecialchars($mail['instruksi_kadin'])) ?>"</p>
                            <div style="margin-top: 1rem; font-size: 0.7rem; color: #b45309; font-weight: 800;">INSTRUKSI KADIN • <?= date('d M Y H:i', strtotime($mail['tgl_kadin'])) ?></div>
                        </div>
                    <?php endif; ?>

                    <div class="doc-meta-grid">
                        <div class="meta-item"><label>Nomor Surat</label><span><?= htmlspecialchars($mail['nomor_surat']) ?></span></div>
                        <div class="meta-item"><label>Pengirim</label><span><?= htmlspecialchars($mail['pengirim']) ?></span></div>
                        <div class="meta-item"><label>Sifat Surat</label><span style="color: var(--primary); text-transform: uppercase;"><?= ucfirst($mail['sifat_surat']) ?></span></div>
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
                            <svg class="icon" style="stroke: white;"><polyline points="20 6 9 17 4 12"></polyline></svg> Kirim Tugas
                        </button>
                    </form>
                    <?php else: ?>
                        <div style="text-align: center; padding: 2rem; border-radius: 1rem; background: #f8fafc; border: 1px solid var(--border);">
                            <svg class="icon" style="width: 48px; height: 48px; color: var(--primary); margin-bottom: 1rem;"><circle cx="12" cy="12" r="10"></circle><polyline points="12 8 12 12 16 14"></polyline></svg>
                            <p style="font-weight: 700; color: #0f172a;">Sudah Diteruskan</p>
                            <p style="font-size: 0.8rem; color: #64748b; margin-top: 0.5rem;">Tugas ini sedang dalam proses tindak lanjut oleh staf.</p>
                            <a href="surat_masuk.php" class="btn-submit" style="margin-top: 1.5rem; text-decoration: none; display: inline-block;">Kembali ke Daftar</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
    <script src="../js/notifications.js"></script>
</body>
</html>
