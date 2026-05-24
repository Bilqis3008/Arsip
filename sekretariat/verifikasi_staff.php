<?php
session_start();
require_once '../config/db.php';

// Auth Check
if (!isset($_SESSION['user_nip']) || $_SESSION['user_role'] !== 'sekretariat') {
    header('Location: ../auth/login.php');
    exit;
}

$nip_admin = $_SESSION['user_nip'];
$success_msg = "";
$error_msg = "";

// Handle Approval/Rejection
if (isset($_GET['action'])) {
    $action = $_GET['action'];
    
    if ($action === 'approve_all') {
        try {
            $stmt = $pdo->prepare("UPDATE users SET status = 'aktif' WHERE status = 'pending' AND role = 'staff'");
            $stmt->execute();
            $success_msg = "Semua pendaftaran staff berhasil disetujui!";
        } catch (PDOException $e) {
            $error_msg = "Gagal menyetujui semua staff: " . $e->getMessage();
        }
    } elseif ($action === 'approve' && isset($_GET['nip'])) {
        $nip = $_GET['nip'];
        try {
            $stmt = $pdo->prepare("UPDATE users SET status = 'aktif' WHERE nip = ?");
            $stmt->execute([$nip]);
            $success_msg = "Staff berhasil disetujui!";
        } catch (PDOException $e) {
            $error_msg = "Gagal menyetujui staff: " . $e->getMessage();
        }
    } elseif ($action === 'reject' && isset($_GET['nip'])) {
        $nip = $_GET['nip'];
        try {
            $stmt = $pdo->prepare("UPDATE users SET status = 'nonaktif' WHERE nip = ?");
            $stmt->execute([$nip]);
            $success_msg = "Staff berhasil ditolak!";
        } catch (PDOException $e) {
            $error_msg = "Gagal menolak staff: " . $e->getMessage();
        }
    }
}

// Fetch Pending Staff
$stmt = $pdo->prepare("SELECT u.*, b.nama_bidang, s.nama_seksi FROM users u 
                       LEFT JOIN bidang b ON u.id_bidang = b.id_bidang 
                       LEFT JOIN seksi s ON u.id_seksi = s.id_seksi 
                       WHERE u.status = 'pending' AND u.role = 'staff'
                       ORDER BY u.nama ASC");
$stmt->execute();
$pending_staff = $stmt->fetchAll();

// Fetch Admin Data
$stmt = $pdo->prepare("SELECT * FROM users WHERE nip = ?");
$stmt->execute([$nip_admin]);
$admin = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verifikasi Staff - Arsip Digital Premium</title>
    <link rel="stylesheet" href="../css/sekretariat/home.css">
    <link rel="stylesheet" href="../css/sekretariat/verifikasi_staff.css">
    <link rel="stylesheet" href="../css/notifications.css">
</head>
<body>
    <aside class="sidebar">
        <div class="sidebar-header">
            <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path></svg>
            <h2>ARSIP DIGITAL</h2>
        </div>
        <nav class="sidebar-menu">
            <div class="menu-label">Menu Utama</div>
            <a href="home.php" class="menu-item"><svg class="icon" viewBox="0 0 24 24"><rect width="7" height="7" x="3" y="3" rx="1"/><rect width="7" height="7" x="14" y="3" rx="1"/><rect width="7" height="7" x="14" y="14" rx="1"/><rect width="7" height="7" x="3" y="14" rx="1"/></svg> Dashboard</a>
            <div class="menu-label">Buku Agenda</div>
            <a href="surat_masuk.php" class="menu-item"><svg class="icon" viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg> Surat Masuk</a>
            <a href="surat_keluar.php" class="menu-item"><svg class="icon" viewBox="0 0 24 24"><path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/></svg> Surat Keluar</a>
            <div class="menu-label">Administrasi Sistem</div>
            <a href="manajemen_pengguna.php" class="menu-item"><svg class="icon" viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg> Manajemen Pengguna</a>
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
            <div class="header-title"><h1>Verifikasi Staff</h1><p class="header-desc-muted">Validasi identitas personel sebelum memberikan akses penuh ke sistem.</p></div>
            <div class="header-profile-container">
                <?php if (!empty($pending_staff)): ?>
                    <a href="?action=approve_all" class="btn-premium-action" onclick="return confirm('Setujui seluruh staff yang sedang menunggu?')">
                        <svg class="icon btn-icon-sm" viewBox="0 0 24 24"><path d="m9 11 3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                        Approve All (<?= count($pending_staff) ?>)
                    </a>
                <?php endif; ?>
                <div class="user-profile">
                    <div class="user-info">
                        <span class="user-name"><?= htmlspecialchars($admin['nama'] ?? 'Admin') ?></span>
                        <span class="user-role">Sekretariat</span>
                    </div>
                    <div class="user-avatar"><?= strtoupper(substr((string)($admin['nama'] ?? 'A'), 0, 1)) ?></div>
                </div>
            </div>
        </header>

        <div class="content-body">
            <?php if ($success_msg): ?>
                <div class="alert-message alert-success"><svg class="icon alert-icon"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg> <?= $success_msg ?></div>
            <?php endif; ?>

            <div class="verification-grid">
                <?php if (empty($pending_staff)): ?>
                    <div class="card empty-state-card">
                        <svg class="icon empty-state-icon" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M16 8l-8 8m0-8l8 8"/></svg>
                        <h2 class="empty-state-title">Belum ada antrian verifikasi.</h2>
                        <p class="empty-state-desc">Seluruh registrasi staff telah diproses.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($pending_staff as $staff): ?>
                        <div class="verification-card">
                            <div class="v-card-header">
                                <div>
                                    <h3><?= htmlspecialchars($staff['nama'] ?? '') ?></h3>
                                    <span class="v-card-header-nip">NIP: <?= htmlspecialchars($staff['nip'] ?? '') ?></span>
                                </div>
                                <span class="badge badge-nonaktif badge-pending-color">Pending</span>
                            </div>
                            <div class="v-card-body">
                                <div class="info-label">Email Institusi</div>
                                <div class="info-value"><?= htmlspecialchars($staff['email'] ?? '-') ?></div>
                                <div class="info-label">Unit Penempatan</div>
                                <div class="info-value"><?= htmlspecialchars($staff['nama_bidang'] ?? 'Umum') ?> / <?= htmlspecialchars($staff['nama_seksi'] ?? 'Pelaksana') ?></div>
                                
                                <div class="info-label">Bukti Identitas (Name Tag)</div>
                                <?php if ($staff['name_tag']): ?>
                                    <img src="../uploads/nametags/<?= $staff['name_tag'] ?>" class="nametag-img" alt="Name Tag Preview" onclick="window.open(this.src)">
                                <?php else: ?>
                                    <div class="empty-tag-placeholder">Staff tidak mengunggah Name Tag.</div>
                                <?php endif; ?>
                            </div>
                            <div class="v-card-footer">
                                <a href="?action=reject&nip=<?= $staff['nip'] ?>" class="btn-reject" onclick="return confirm('Tolak registrasi staff ini?')">Tolak</a>
                                <a href="?action=approve&nip=<?= $staff['nip'] ?>" class="btn-approve" onclick="return confirm('Berikan akses aktif ke staff ini?')">Setujui</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </main>
    <script src="../js/notifications.js"></script>
</body>
</html>
