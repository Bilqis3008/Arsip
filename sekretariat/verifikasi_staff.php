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
    <title>Verifikasi Staff - Arsip Digital</title>
    <link rel="stylesheet" href="../css/sekretariat/home.css">
    <link rel="stylesheet" href="../css/sekretariat/verifikasi_staff.css">
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
            <a href="surat_masuk.php" class="menu-item">
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
            <a href="verifikasi_staff.php" class="menu-item active">
                <svg class="icon" viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                Verifikasi Staff
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
            <div class="header-title">
                <h1>Verifikasi Staff Baru</h1>
                <p>Setujui atau tolak pendaftaran staff internal baru.</p>
            </div>
            <div style="display: flex; align-items: center; gap: 1.5rem;">
                <?php if (!empty($pending_staff)): ?>
                    <a href="?action=approve_all" class="btn btn-approve-all" onclick="return confirm('Apakah Anda yakin ingin menyetujui SEMUA pendaftaran staff yang ada?')">
                        <svg viewBox="0 0 24 24" style="width: 18px; height: 18px; margin-right: 0.5rem; fill: none; stroke: currentColor; stroke-width: 2.5;"><polyline points="20 6 9 17 4 12"></polyline></svg>
                        Setujui Semua (<?= count($pending_staff) ?>)
                    </a>
                <?php endif; ?>
                <div class="user-profile">
                    <div class="user-info">
                        <span class="user-name"><?= htmlspecialchars($admin['nama']) ?></span>
                        <span class="user-role">Sekretariat</span>
                    </div>
                    <div class="user-avatar"><?= strtoupper(substr($admin['nama'], 0, 1)) ?></div>
                </div>
            </div>
        </header>

        <div class="content-body">
            <?php if ($success_msg): ?>
                <div class="alert alert-success"><?= $success_msg ?></div>
            <?php endif; ?>
            <?php if ($error_msg): ?>
                <div class="alert alert-danger"><?= $error_msg ?></div>
            <?php endif; ?>

            <div class="verification-grid">
                <?php if (empty($pending_staff)): ?>
                    <div class="empty-state">
                        <p>Tidak ada pendaftaran staff yang menunggu verifikasi.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($pending_staff as $staff): ?>
                        <div class="verification-card">
                            <div class="card-header">
                                <div class="staff-basic">
                                    <h3><?= htmlspecialchars($staff['nama']) ?></h3>
                                    <span>NIP: <?= htmlspecialchars($staff['nip']) ?></span>
                                </div>
                                <span class="badge pending">Menunggu</span>
                            </div>
                            <div class="card-body">
                                <div class="info-item">
                                    <label>Email</label>
                                    <p><?= htmlspecialchars($staff['email']) ?></p>
                                </div>
                                <div class="info-item">
                                    <label>Unit Kerja</label>
                                    <p>
                                        <?= htmlspecialchars($staff['nama_bidang'] ?? '') ?> 
                                        <?= ($staff['nama_bidang'] && $staff['nama_seksi']) ? ' - ' : '' ?>
                                        <?= htmlspecialchars($staff['nama_seksi'] ?? '') ?>
                                    </p>
                                </div>
                                <div class="nametag-preview">
                                    <label>Name Tag / ID Card</label>
                                    <?php if ($staff['name_tag']): ?>
                                        <img src="../uploads/nametags/<?= $staff['name_tag'] ?>" alt="Name Tag" onclick="window.open(this.src)">
                                    <?php else: ?>
                                        <p class="no-image">Tidak ada gambar diunggah</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="card-footer">
                                <a href="?action=reject&nip=<?= $staff['nip'] ?>" class="btn btn-reject" onclick="return confirm('Tolak pendaftaran staff ini?')">Tolak</a>
                                <a href="?action=approve&nip=<?= $staff['nip'] ?>" class="btn btn-approve">Setujui</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </main>
</body>
</html>
