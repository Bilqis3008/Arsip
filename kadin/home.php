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
            <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
            <h2>KEPALA DINAS</h2>
        </div>
        <nav class="sidebar-menu">
            <div class="menu-label">Main Executive</div>
            <a href="home.php" class="menu-item active"><svg class="icon" viewBox="0 0 24 24"><rect width="7" height="7" x="3" y="3" rx="1"/><rect width="7" height="7" x="14" y="3" rx="1"/><rect width="7" height="7" x="14" y="14" rx="1"/><rect width="7" height="7" x="3" y="14" rx="1"/></svg> Dashboard</a>
            <div class="menu-label">Disposisi & Agenda</div>
            <a href="surat_masuk.php" class="menu-item"><svg class="icon" viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg> Surat Masuk</a>
            <a href="disposisi_surat.php" class="menu-item"><svg class="icon" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 1 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg> Disposisi Surat</a>
            <div class="menu-label">Monitoring</div>
            <a href="monitoring_surat.php" class="menu-item"><svg class="icon" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="m12 8 0 4 2 2"/></svg> Monitoring Alur</a>
            <a href="laporan.php" class="menu-item"><svg class="icon" viewBox="0 0 24 24"><path d="M3 3v18h18"/><path d="m19 9-5 5-4-4-3 3"/></svg> Laporan</a>
            <div class="menu-label">System</div>
            <a href="profil.php" class="menu-item"><svg class="icon" viewBox="0 0 24 24"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg> Profil Saya</a>
        </nav>
        <div class="sidebar-footer">
            <a href="../auth/logout.php" class="logout-btn"><svg class="icon" viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5"/><path d="M21 12H9"/></svg> Logout</a>
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
                    <div class="stat-icon icon-waiting"><svg class="icon" viewBox="0 0 24 24"><path d="M12 2v4"/><path d="M12 18v4"/><path d="m4.93 4.93 2.83 2.83"/><path d="m16.24 16.24 2.83 2.83"/><path d="M2 12h4"/><path d="M18 12h4"/><path d="m4.93 19.07 2.83-2.83"/><path d="m16.24 7.76 2.83-2.83"/></svg></div>
                    <div class="stat-info"><span class="label">Belum Disposisi</span><span class="value"><?= $belum_disposisi ?></span></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon icon-all"><svg class="icon" viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></div>
                    <div class="stat-info"><span class="label">Proses Unit</span><span class="value"><?= $proses_unit ?></span></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon icon-done"><svg class="icon" viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg></div>
                    <div class="stat-info"><span class="label">Selesai/Arsip</span><span class="value"><?= $selesai_arsip ?></span></div>
                </div>
            </div>

            <!-- Main Features Grid -->
            <div class="dashboard-grid">
                <!-- Weekly Distribution Chart (Left) -->
                <div class="card">
                    <div class="card-header"><h3><svg class="icon" viewBox="0 0 24 24"><path d="M3 3v18h18"/><path d="m19 9-5 5-4-4-3 3"/></svg> Distribusi Surat Mingguan</h3></div>
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
                    <div class="card-header"><h3><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg> Perlu Disposisi</h3></div>
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
