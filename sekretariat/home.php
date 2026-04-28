<?php
session_start();
require_once '../config/db.php';

// Auth Check
if (!isset($_SESSION['user_nip']) || $_SESSION['user_role'] !== 'sekretariat') {
    header('Location: ../auth/login.php');
    exit;
}

$nip = $_SESSION['user_nip'];

// Fetch Admin Data
$stmt = $pdo->prepare("SELECT * FROM users WHERE nip = ?");
$stmt->execute([$nip]);
$admin = $stmt->fetch();

// Statistics Queries
$today_count = $pdo->query("SELECT COUNT(*) FROM surat_masuk WHERE DATE(tanggal_terima) = CURDATE()")->fetchColumn() ?: 0;
$month_count = $pdo->query("SELECT COUNT(*) FROM surat_masuk WHERE MONTH(tanggal_terima) = MONTH(CURDATE()) AND YEAR(tanggal_terima) = YEAR(CURDATE())")->fetchColumn() ?: 0;
$pending_disp = $pdo->query("SELECT COUNT(*) FROM surat_masuk WHERE status = 'tercatat'")->fetchColumn() ?: 0;
$on_process = $pdo->query("SELECT COUNT(*) FROM surat_masuk WHERE status IN ('didispokan', 'diteruskan')")->fetchColumn() ?: 0;

// Fetch Recent Activity (Last 5 Incoming Mails)
$recent_mails = $pdo->query("SELECT * FROM surat_masuk ORDER BY created_at DESC LIMIT 5")->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Sekretariat - Arsip Digital Premium</title>
    <link rel="stylesheet" href="../css/sekretariat/home.css">
    <link rel="stylesheet" href="../css/notifications.css">
    <style>
        /* Extra polish for dashboard specific elements */
        .welcome-content { display: flex; justify-content: space-between; align-items: center; }
        .welcome-img { width: 240px; opacity: 0.8; filter: drop-shadow(0 0 20px rgba(99, 102, 241, 0.4)); }
        @media (max-width: 992px) { .welcome-img { display: none; } }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path></svg>
            <h2>ARSIP DIGITAL</h2>
        </div>
        
        <nav class="sidebar-menu">
            <div class="menu-label">Menu Utama</div>
            <a href="home.php" class="menu-item active">
                <svg class="icon" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
                Dashboard
            </a>
            
            <div class="menu-label">Buku Agenda</div>
            <a href="surat_masuk.php" class="menu-item">
                <svg class="icon" viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
                Surat Masuk
            </a>
            <a href="surat_keluar.php" class="menu-item">
                <svg class="icon" viewBox="0 0 24 24"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>
                Surat Keluar
            </a>

            <div class="menu-label">Administrasi Sistem</div>
            <a href="manajemen_pengguna.php" class="menu-item">
                <svg class="icon" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                Manajemen Pengguna
            </a>
            <a href="verifikasi_staff.php" class="menu-item">
                <svg class="icon" viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                Verifikasi Staff
            </a>
            <a href="monitoring_surat.php" class="menu-item">
                <svg class="icon" viewBox="0 0 24 24"><path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"></path><polyline points="14 2 14 8 20 8"></polyline><path d="M16 13a2 2 0 1 1-4 0v-2a2 2 0 1 0-4 0"></path><line x1="12" y1="14" x2="12" y2="19"></line></svg>
                Monitoring Surat
            </a>
            
            <div class="menu-label">Statistik & Arsip</div>
            <a href="monitoring_laporan.php" class="menu-item">
                <svg class="icon" viewBox="0 0 24 24"><line x1="18" y1="20" x2="18" y2="10"></line><line x1="12" y1="20" x2="12" y2="4"></line><line x1="6" y1="20" x2="6" y2="14"></line></svg>
                Laporan Saya
            </a>
                        
            <div class="menu-label">Pengaturan Akun</div>
            <a href="profil.php" class="menu-item"><svg class="icon" viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg> Profil Saya</a>
        </nav>

        <div class="sidebar-footer">
            <a href="../auth/logout.php" class="logout-btn">
                <svg class="icon" viewBox="0 0 24 24" style="stroke: #fda4af;"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
                Keluar Sistem
            </a>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <header class="content-header">
            <div class="header-title">
                <h1>Dashboard Statistik</h1>
            </div>
            <div class="user-profile">
                <div class="user-info">
                    <span class="user-name"><?= htmlspecialchars($admin['nama'] ?? 'Admin') ?></span>
                    <span class="user-role">Sekretariat Umum</span>
                </div>
                <div class="user-avatar">
                    <?= strtoupper(substr((string)($admin['nama'] ?? 'A'), 0, 1)) ?>
                </div>
            </div>
        </header>

        <div class="content-body">
            <div class="welcome-banner">
                <div class="welcome-content">
                    <div>
                        <h2>Selamat Datang Kembali, <?= htmlspecialchars(explode(' ', (string)($admin['nama'] ?? 'Admin'))[0]) ?>!</h2>
                        <p>Kelola seluruh alur administrasi persuratan dan manajemen arsip digital secara efisien melalui pusat kontrol Sekretariat.</p>
                    </div>
                    <div class="welcome-img">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="0.5" style="color: rgba(255,255,255,0.2); width: 100%; height: 100%;"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path></svg>
                    </div>
                </div>
            </div>

            <!-- Stats Grid -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon icon-today">
                        <svg class="icon" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                    </div>
                    <div class="stat-details">
                        <h3>Agenda Hari Ini</h3>
                        <p class="number"><?= $today_count ?></p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon icon-month">
                        <svg class="icon" viewBox="0 0 24 24"><path d="M21 10H3M16 2v4M8 2v4m13 4v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-8h18z"></path></svg>
                    </div>
                    <div class="stat-details">
                        <h3>Volume Bulan Ini</h3>
                        <p class="number"><?= $month_count ?></p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon icon-pending">
                        <svg class="icon" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                    </div>
                    <div class="stat-details">
                        <h3>Belum Disposisi</h3>
                        <p class="number"><?= $pending_disp ?></p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon icon-process">
                        <svg class="icon" viewBox="0 0 24 24"><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"></path></svg>
                    </div>
                    <div class="stat-details">
                        <h3>Alur Berjalan</h3>
                        <p class="number"><?= $on_process ?></p>
                    </div>
                </div>
            </div>

            <div class="bottom-grid">
                <!-- Activity Section -->
                <div class="card">
                    <div class="card-header">
                        <h2>Surat Masuk Terbaru</h2>
                        <a href="surat_masuk.php" class="view-all">Lihat Semua</a>
                    </div>
                    <div class="recent-activity">
                        <?php if (empty($recent_mails)): ?>
                            <div style="text-align: center; padding: 3rem; opacity: 0.5;">
                                <svg class="icon" style="width: 48px; height: 48px; margin-bottom: 1rem;"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path></svg>
                                <p>Belum ada aktivitas persuratan tercatat.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($recent_mails as $mail): ?>
                                <li class="activity-item">
                                    <div class="activity-point" style="background: <?= $mail['status'] === 'tercatat' ? 'var(--warning)' : 'var(--success)' ?>"></div>
                                    <div class="activity-content">
                                        <p><?= htmlspecialchars($mail['perihal'] ?? '') ?></p>
                                        <span>Dari: <?= htmlspecialchars($mail['pengirim'] ?? '') ?> • <b style="color: var(--primary);"><?= date('d M Y', strtotime($mail['created_at'] ?? 'now')) ?></b></span>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Stats Summary -->
                <div class="card">
                    <div class="card-header">
                        <h2>Analisis Grafik</h2>
                    </div>
                    <div class="chart-placeholder">
                        <div style="text-align: center;">
                            <svg class="icon" style="width: 64px; height: 64px; opacity: 0.1; margin-bottom: 1.5rem;"><path d="M21.21 15.89A10 10 0 1 1 8 2.83"></path><path d="M22 12A10 10 0 0 0 12 2v10z"></path></svg>
                            <p style="font-weight: 700; color: var(--text-muted);">Visualisasi Data<br><span style="font-size: 0.8rem; font-weight: 500;">Modul Analitik Segera Aktif</span></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
    <script src="../js/notifications.js"></script>
</body>
</html>
