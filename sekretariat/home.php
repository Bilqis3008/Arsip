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
            <a href="home.php" class="menu-item active"><svg class="icon" viewBox="0 0 24 24"><rect width="7" height="7" x="3" y="3" rx="1"/><rect width="7" height="7" x="14" y="3" rx="1"/><rect width="7" height="7" x="14" y="14" rx="1"/><rect width="7" height="7" x="3" y="14" rx="1"/></svg> Dashboard</a>
            <div class="menu-label">Buku Agenda</div>
            <a href="surat_masuk.php" class="menu-item"><svg class="icon" viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg> Surat Masuk</a>
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

        <div class="sidebar-footer">
            <a href="../auth/logout.php" class="logout-btn">
                <svg class="icon" viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5"/><path d="M21 12H9"/></svg>
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
                        <svg class="icon" viewBox="0 0 24 24"><rect width="18" height="18" x="3" y="4" rx="2" ry="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
                    </div>
                    <div class="stat-details">
                        <h3>Agenda Hari Ini</h3>
                        <p class="number"><?= $today_count ?></p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon icon-month">
                        <svg class="icon" viewBox="0 0 24 24"><path d="M21 10H3M16 2v4M8 2v4m13 4v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-8h18z"/></svg>
                    </div>
                    <div class="stat-details">
                        <h3>Volume Bulan Ini</h3>
                        <p class="number"><?= $month_count ?></p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon icon-pending">
                        <svg class="icon" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
                    </div>
                    <div class="stat-details">
                        <h3>Belum Disposisi</h3>
                        <p class="number"><?= $pending_disp ?></p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon icon-process">
                        <svg class="icon" viewBox="0 0 24 24"><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/></svg>
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
