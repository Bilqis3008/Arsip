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
    <link rel="stylesheet" href="../css/notifications.css">
    <style>
        .verification-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(380px, 1fr)); gap: 2rem; margin-top: 1rem; }
        .verification-card { background: #fff; border-radius: 2rem; border: 1px solid var(--border); overflow: hidden; transition: var(--transition); display: flex; flex-direction: column; }
        .verification-card:hover { transform: translateY(-8px); box-shadow: var(--shadow-premium); border-color: var(--primary-light); }
        .v-card-header { padding: 2rem; background: #f8fafc; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: flex-start; }
        .v-card-body { padding: 2rem; flex: 1; }
        .v-card-footer { padding: 1.5rem 2rem; background: #fff; border-top: 1px solid var(--border); display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
        .nametag-img { width: 100%; height: 200px; object-fit: cover; border-radius: 1rem; cursor: pointer; border: 1px solid var(--border); transition: var(--transition); }
        .nametag-img:hover { opacity: 0.9; transform: scale(1.02); }
        .info-label { font-size: 0.7rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.1em; margin-bottom: 0.25rem; }
        .info-value { font-weight: 700; color: var(--text-main); margin-bottom: 1.25rem; font-size: 1rem; }
        .btn-approve { background: linear-gradient(135deg, var(--success), #059669); color: #fff; border: none; padding: 0.8rem; border-radius: 1rem; font-weight: 800; cursor: pointer; transition: var(--transition); text-align: center; text-decoration: none; }
        .btn-reject { background: #fef2f2; color: var(--danger); border: 1px solid #fee2e2; padding: 0.8rem; border-radius: 1rem; font-weight: 800; cursor: pointer; transition: var(--transition); text-align: center; text-decoration: none; }
        .btn-approve:hover { transform: translateY(-2px); box-shadow: 0 8px 15px rgba(16, 185, 129, 0.2); }
        .btn-reject:hover { background: var(--danger); color: #fff; }
        .btn-premium-action { background: linear-gradient(135deg, var(--primary), var(--primary-dark)); color: #fff; padding: 0.75rem 1.5rem; border-radius: 1rem; font-weight: 800; text-decoration: none; display: flex; align-items: center; gap: 0.5rem; transition: var(--transition); box-shadow: 0 4px 12px rgba(99, 102, 241, 0.2); }
        .btn-premium-action:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(99, 102, 241, 0.3); }
        @media (max-width: 768px) { .verification-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <aside class="sidebar">
        <div class="sidebar-header">
            <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path></svg>
            <h2>ARSIP DIGITAL</h2>
        </div>
        <nav class="sidebar-menu">
            <div class="menu-label">Menu Utama</div>
            <a href="home.php" class="menu-item"><svg class="icon" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg> Dashboard</a>
            <div class="menu-label">Buku Agenda</div>
            <a href="surat_masuk.php" class="menu-item"><svg class="icon" viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg> Surat Masuk</a>
            <a href="surat_keluar.php" class="menu-item"><svg class="icon" viewBox="0 0 24 24"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg> Surat Keluar</a>
            <div class="menu-label">Administrasi Sistem</div>
            <a href="manajemen_pengguna.php" class="menu-item"><svg class="icon" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle></svg> Manajemen Pengguna</a>
            <a href="verifikasi_staff.php" class="menu-item active"><svg class="icon" viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg> Verifikasi Staff</a>
            <a href="monitoring_surat.php" class="menu-item"><svg class="icon" viewBox="0 0 24 24"><path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"></path><polyline points="14 2 14 8 20 8"></polyline><path d="M16 13a2 2 0 1 1-4 0v-2a2 2 0 1 0-4 0"></path><line x1="12" y1="14" x2="12" y2="19"></line></svg> Monitoring Surat</a>
            <div class="menu-label">Monitoring</div>
            <a href="monitoring_laporan.php" class="menu-item"><svg class="icon" viewBox="0 0 24 24"><line x1="18" y1="20" x2="18" y2="10"></line><line x1="12" y1="20" x2="12" y2="4"></line><line x1="6" y1="20" x2="6" y2="14"></line></svg> Laporan</a>
            <div class="menu-label">Akun</div>
            <a href="profil.php" class="menu-item"><svg class="icon" viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg> Profil Saya</a>
        </nav>
        <div class="sidebar-footer"><a href="../auth/logout.php" class="logout-btn"><svg class="icon" viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg> Keluar Sistem</a></div>
    </aside>

    <main class="main-content">
        <header class="content-header">
            <div class="header-title"><h1>Verifikasi Staff</h1><p style="font-size:0.9rem; color:var(--text-muted);">Validasi identitas personel sebelum memberikan akses penuh ke sistem.</p></div>
            <div style="display: flex; align-items: center; gap: 1.5rem;">
                <?php if (!empty($pending_staff)): ?>
                    <a href="?action=approve_all" class="btn-premium-action" onclick="return confirm('Setujui seluruh staff yang sedang menunggu?')">
                        <svg class="icon" style="width:18px; height:18px;"><polyline points="20 6 9 17 4 12"></polyline></svg>
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
                    <div class="card" style="grid-column: 1 / -1; text-align: center; padding: 5rem;">
                        <svg class="icon" style="width: 64px; height: 64px; color: var(--border); margin-bottom: 1.5rem;"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                        <h2 style="color: var(--text-muted);">Belum ada antrian verifikasi.</h2>
                        <p style="color: var(--text-muted);">Seluruh registrasi staff telah diproses.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($pending_staff as $staff): ?>
                        <div class="verification-card">
                            <div class="v-card-header">
                                <div>
                                    <h3 style="font-size: 1.25rem; font-weight: 800; color: var(--text-main); margin-bottom: 0.25rem;"><?= htmlspecialchars($staff['nama'] ?? '') ?></h3>
                                    <span style="font-family:'JetBrains Mono'; font-size: 0.85rem; color: var(--primary); font-weight: 700;">NIP: <?= htmlspecialchars($staff['nip'] ?? '') ?></span>
                                </div>
                                <span class="badge badge-nonaktif" style="background: rgba(245, 158, 11, 0.1); color: var(--warning);">Pending</span>
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
                                    <div style="background:#f1f5f9; padding:2rem; border-radius:1rem; text-align:center; color:var(--text-muted); font-size:0.9rem; font-style:italic;">Staff tidak mengunggah Name Tag.</div>
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
