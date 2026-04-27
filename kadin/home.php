<?php
session_start();
require_once '../config/db.php';

// Auth Check for Kepala Dinas
if (!isset($_SESSION['user_nip']) || $_SESSION['user_role'] !== 'kepala_dinas') {
    header('Location: ../auth/login.php');
    exit;
}

$nip = $_SESSION['user_nip'];

// --- FETCH HEAD DATA ---
$stmt = $pdo->prepare("SELECT * FROM users WHERE nip = ?");
$stmt->execute([$nip]);
$head = $stmt->fetch();

// --- STATISTICS ---
$belum_disposisi = $pdo->query("SELECT COUNT(*) FROM surat_masuk WHERE status = 'tercatat'")->fetchColumn();
$proses_unit = $pdo->query("SELECT COUNT(*) FROM surat_masuk WHERE status IN ('didispokan', 'diteruskan')")->fetchColumn();
$selesai_arsip = $pdo->query("SELECT COUNT(*) FROM surat_masuk WHERE status = 'selesai'")->fetchColumn();

// --- WEEKLY DATA FOR CHART ---
// Fetch counts for the last 5 operational days (Monday-Friday)
$weekly_data = [];
$days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri'];
foreach ($days as $day) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM surat_masuk WHERE DAYNAME(tanggal_terima) = ? AND tanggal_terima >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)");
    $stmt->execute([$day]);
    $weekly_data[$day] = $stmt->fetchColumn();
}
$max_val = max($weekly_data) ?: 1; // For relative height calculation

// --- RECENT MAIL WAITING ---
$stmt = $pdo->query("SELECT * FROM surat_masuk WHERE status = 'tercatat' ORDER BY created_at DESC LIMIT 5");
$recent_mail = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Kepala Dinas - Arsip Digital</title>
    <link rel="stylesheet" href="../css/kadin/home.css">
    <link rel="stylesheet" href="../css/notifications.css">
</head>
<body>
    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <svg class="icon" style="width: 24px; height: 24px; stroke: var(--accent);"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path></svg>
            <h2>KADIS PANEL</h2>
        </div>
        <nav class="sidebar-menu">
            <div class="menu-label">Main Executive</div>
            <a href="home.php" class="menu-item active"><svg class="icon" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg> Dashboard</a>
            <div class="menu-label">Disposisi & Agenda</div>
            <a href="surat_masuk.php" class="menu-item"><svg class="icon" viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg> Surat Masuk</a>
            <a href="disposisi_surat.php" class="menu-item"><svg class="icon" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 1 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg> Disposisi Surat</a>
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
            <div class="header-title"><h1>Dashboard Kepala Dinas</h1></div>
            <div class="user-profile">
                <div class="user-info"><span class="user-name"><?= htmlspecialchars($head['nama']) ?></span><span class="user-role">Kepala Dinas</span></div>
                <div class="user-avatar"><?= strtoupper(substr($head['nama'], 0, 1)) ?></div>
            </div>
        </header>

        <div class="content-body">
            <!-- Stats Bar -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon icon-waiting"><svg class="icon"><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"></path></svg></div>
                    <div class="stat-info"><span class="label">Belum Disposisi</span><span class="value"><?= $belum_disposisi ?></span></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon icon-all"><svg class="icon"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg></div>
                    <div class="stat-info"><span class="label">Proses Unit</span><span class="value"><?= $proses_unit ?></span></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon icon-done"><svg class="icon"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg></div>
                    <div class="stat-info"><span class="label">Selesai/Arsip</span><span class="value"><?= $selesai_arsip ?></span></div>
                </div>
            </div>

            <!-- Main Features Grid -->
            <div class="dashboard-grid">
                <!-- Weekly Distribution Chart (Left) -->
                <div class="card">
                    <div class="card-header"><h3><svg class="icon"><line x1="18" y1="20" x2="18" y2="10"></line><line x1="12" y1="20" x2="12" y2="4"></line><line x1="6" y1="20" x2="6" y2="14"></line></svg> Distribusi Surat Mingguan</h3></div>
                    <div class="chart-container">
                        <?php foreach ($weekly_data as $day => $count): 
                            $height = ($count / $max_val) * 100;
                        ?>
                            <div class="chart-bar-wrapper">
                                <div class="bar" style="height: <?= $height ?>%;">
                                    <span class="bar-val"><?= $count ?></span>
                                </div>
                                <span class="bar-label"><?= $day ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Recent Mail Awaiting Action (Right) -->
                <div class="card">
                    <div class="card-header"><h3><svg class="icon"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg> Perlu Disposisi</h3></div>
                    <div class="mail-list">
                        <?php if (empty($recent_mail)): ?>
                            <p style="text-align: center; color: var(--text-muted); padding: 2rem;">Tidak ada surat menunggu.</p>
                        <?php else: ?>
                            <?php foreach ($recent_mail as $rm): ?>
                                <a href="disposisi_surat.php?id=<?= $rm['id_surat_masuk'] ?>" class="mail-item">
                                    <div class="mail-badge <?= $rm['sifat_surat'] === 'biasa' ? 'normal' : 'urgent' ?>"></div>
                                    <div class="mail-info">
                                        <p><?= htmlspecialchars($rm['perihal']) ?></p>
                                        <span>No: <?= htmlspecialchars($rm['nomor_surat']) ?></span>
                                    </div>
                                    <div class="mail-meta">
                                        <span class="date"><?= date('d M', strtotime($rm['tanggal_terima'])) ?></span>
                                        <span class="action">Dispo &rarr;</span>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>
    <script src="../js/notifications.js"></script>
</body>
</html>
