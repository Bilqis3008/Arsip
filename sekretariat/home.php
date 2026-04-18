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
$today_count = $pdo->query("SELECT COUNT(*) FROM surat_masuk WHERE DATE(tanggal_terima) = CURDATE()")->fetchColumn();
$month_count = $pdo->query("SELECT COUNT(*) FROM surat_masuk WHERE MONTH(tanggal_terima) = MONTH(CURDATE()) AND YEAR(tanggal_terima) = YEAR(CURDATE())")->fetchColumn();
$pending_disp = $pdo->query("SELECT COUNT(*) FROM surat_masuk WHERE status = 'tercatat'")->fetchColumn();
$on_process = $pdo->query("SELECT COUNT(*) FROM surat_masuk WHERE status IN ('didispokan', 'diteruskan')")->fetchColumn();

// Fetch Recent Activity (Last 5 Incoming Mails)
$recent_mails = $pdo->query("SELECT * FROM surat_masuk ORDER BY created_at DESC LIMIT 5")->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Sekretariat - Arsip Digital</title>
    <link rel="stylesheet" href="../css/sekretariat/home.css">
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
            <a href="home.php" class="menu-item active">
                <svg class="icon" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
                Dashboard
            </a>
            
            <div class="menu-label">Buku Agenda</div>
            <a href="surat_masuk.php" class="menu-item">
                <svg class="icon" viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
                Surat Masuk
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

    <!-- Main Content -->
    <main class="main-content">
        <header class="content-header">
            <div class="header-title">
                <h1>Dashboard Statistik</h1>
            </div>
            <div class="user-profile">
                <div class="user-info">
                    <span class="user-name"><?= htmlspecialchars($admin['nama']) ?></span>
                    <span class="user-role">Sekretariat</span>
                </div>
                <div class="user-avatar">
                    <?= strtoupper(substr($admin['nama'], 0, 1)) ?>
                </div>
            </div>
        </header>

        <div class="content-body">
            <div class="welcome-banner">
                <h2>Selamat Datang, <?= htmlspecialchars($admin['nama']) ?>!</h2>
                <p>Anda masuk sebagai Sekretariat. Kelola arsip dan surat masuk melalui panel kontrol di bawah ini.</p>
            </div>

            <!-- Stats Grid -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon icon-today">
                        <svg class="icon" style="width: 24px; height: 24px;"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                    </div>
                    <div class="stat-details">
                        <h3>Masuk Hari Ini</h3>
                        <p class="number"><?= $today_count ?></p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon icon-month">
                        <svg class="icon" style="width: 24px; height: 24px;"><path d="M21 10H3M16 2v4M8 2v4m13 4v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-8h18z"></path></svg>
                    </div>
                    <div class="stat-details">
                        <h3>Masuk Bulan Ini</h3>
                        <p class="number"><?= $month_count ?></p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon icon-pending">
                        <svg class="icon" style="width: 24px; height: 24px;"><path d="M5 22h14M5 2h14m-2 0v5l-5 5-5-5V2m2 20v-5l5-5 5 5v5"></path></svg>
                    </div>
                    <div class="stat-details">
                        <h3>Belum Disposisi</h3>
                        <p class="number"><?= $pending_disp ?></p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon icon-process">
                        <svg class="icon" style="width: 24px; height: 24px;"><line x1="12" y1="2" x2="12" y2="6"></line><line x1="12" y1="18" x2="12" y2="22"></line><line x1="4.93" y1="4.93" x2="7.76" y2="7.76"></line><line x1="16.24" y1="16.24" x2="19.07" y2="19.07"></line><line x1="2" y1="12" x2="6" y2="12"></line><line x1="18" y1="12" x2="22" y2="12"></line><line x1="4.93" y1="19.07" x2="7.76" y2="16.24"></line><line x1="16.24" y1="7.76" x2="19.07" y2="4.93"></line></svg>
                    </div>
                    <div class="stat-details">
                        <h3>Sedang Diproses</h3>
                        <p class="number"><?= $on_process ?></p>
                    </div>
                </div>
            </div>

            <div class="bottom-grid">
                <!-- Activity Section -->
                <div class="card">
                    <div class="card-header">
                        <h2>Surat Masuk Terbaru</h2>
                        <a href="surat_masuk.php" style="font-size: 0.8rem; color: var(--primary); text-decoration: none; font-weight: 600;">Lihat Semua</a>
                    </div>
                    <div class="recent-activity">
                        <?php if (empty($recent_mails)): ?>
                            <p style="text-align: center; color: var(--text-muted); padding: 2rem;">Tidak ada aktivitas terbaru.</p>
                        <?php else: ?>
                            <?php foreach ($recent_mails as $mail): ?>
                                <li class="activity-item">
                                    <div class="activity-point" style="background: <?= $mail['status'] === 'tercatat' ? 'var(--warning)' : 'var(--success)' ?>"></div>
                                    <div class="activity-content">
                                        <p><?= htmlspecialchars($mail['perihal']) ?></p>
                                        <span>Dari: <?= htmlspecialchars($mail['pengirim']) ?> • <?= date('d M Y, H:i', strtotime($mail['created_at'])) ?></span>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Stats Summary -->
                <div class="card">
                    <div class="card-header">
                        <h2>Ringkasan Statistik</h2>
                    </div>
                    <div class="chart-placeholder">
                        <div style="text-align: center;">
                            <svg class="icon" style="width: 48px; height: 48px; opacity: 0.2; margin-bottom: 1rem;"><path d="M21.21 15.89A10 10 0 1 1 8 2.83"></path><path d="M22 12A10 10 0 0 0 12 2v10z"></path></svg>
                            <p>Statistik Visual<br>Segera Hadir</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
