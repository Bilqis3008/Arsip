<?php
session_start();
require_once '../config/db.php';

// Auth Check for Kepala Dinas
if (!isset($_SESSION['user_nip']) || $_SESSION['user_role'] !== 'kepala_dinas') {
    header('Location: ../auth/login.php');
    exit;
}

$nip = $_SESSION['user_nip'];
$tab = $_GET['tab'] ?? 'unread'; // unread | history
$search = $_GET['search'] ?? '';

// Fetch Head Data
$stmt = $pdo->prepare("SELECT * FROM users WHERE nip = ?");
$stmt->execute([$nip]);
$head = $stmt->fetch();

// --- FETCH LIST ---
if ($tab === 'unread') {
    $query = "SELECT * FROM surat_masuk WHERE status = 'tercatat' AND (perihal LIKE ? OR nomor_surat LIKE ? OR pengirim LIKE ?) ORDER BY created_at DESC";
} else {
    $query = "SELECT sm.*, MAX(d.tanggal_disposisi) as tgl_disposisi FROM surat_masuk sm 
              JOIN disposisi d ON sm.id_surat_masuk = d.id_surat_masuk 
              WHERE (sm.perihal LIKE ? OR sm.nomor_surat LIKE ? OR sm.pengirim LIKE ?) 
              GROUP BY sm.id_surat_masuk ORDER BY tgl_disposisi DESC";
}

$stmt = $pdo->prepare($query);
$stmt->execute(["%$search%", "%$search%", "%$search%"]);
$mails = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Surat Masuk - Kadin Panel</title>
    <link rel="stylesheet" href="../css/kadin/home.css">
    <link rel="stylesheet" href="../css/kadin/surat_masuk.css">
    <link rel="stylesheet" href="../css/notifications.css">
</head>
<body>
    <!-- Sidebar (Same as Dashboard) -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <svg class="icon" style="width: 24px; height: 24px; stroke: var(--accent);"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path></svg>
            <h2>KADIS PANEL</h2>
        </div>
        <nav class="sidebar-menu">
            <div class="menu-label">Main Executive</div>
            <a href="home.php" class="menu-item"><svg class="icon" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg> Dashboard</a>
            <div class="menu-label">Disposisi & Agenda</div>
            <a href="surat_masuk.php" class="menu-item active"><svg class="icon" viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg> Surat Masuk</a>
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
            <div class="header-title"><h1>Daftar Agenda Surat Masuk</h1></div>
            <div class="user-profile">
                <div class="user-info"><span class="user-name"><?= htmlspecialchars($head['nama']) ?></span><span class="user-role">Kepala Dinas</span></div>
                <div class="user-avatar"><?= strtoupper(substr($head['nama'], 0, 1)) ?></div>
            </div>
        </header>

        <div class="content-body">
            <!-- Tabs -->
            <div class="kadin-tabs">
                <a href="surat_masuk.php?tab=unread" class="tab-btn <?= $tab === 'unread' ? 'active' : '' ?>"><svg class="icon"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg> Belum Didisposisi</a>
                <a href="surat_masuk.php?tab=history" class="tab-btn <?= $tab === 'history' ? 'active' : '' ?>"><svg class="icon"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg> Riwayat Disposisi</a>
            </div>

            <!-- Search Explorer -->
            <div class="explorer-bar">
                <form method="GET" class="search-box">
                    <input type="hidden" name="tab" value="<?= $tab ?>">
                    <svg class="icon"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                    <input type="text" name="search" placeholder="Cari perihal atau nomor surat..." value="<?= htmlspecialchars($search) ?>">
                </form>
            </div>

            <!-- Table Card -->
            <div class="table-card">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Tgl Terima</th>
                            <th>Identitas Surat</th>
                            <th>Pengirim</th>
                            <th>Sifat</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($mails)): ?>
                            <tr><td colspan="5" style="text-align: center; padding: 4rem; color: var(--text-muted);">Tidak ada data untuk ditampilkan.</td></tr>
                        <?php else: ?>
                            <?php foreach ($mails as $m): ?>
                                <tr>
                                    <td><b><?= date('d/m/Y', strtotime($m['tanggal_terima'])) ?></b></td>
                                    <td>
                                        <div style="font-weight: 700; color: var(--primary);"><?= htmlspecialchars($m['perihal']) ?></div>
                                        <div style="font-size: 0.75rem; color: var(--text-muted);">No: <?= htmlspecialchars($m['nomor_surat']) ?></div>
                                    </td>
                                    <td><?= htmlspecialchars($m['pengirim']) ?></td>
                                    <td><span class="badge-<?= $m['sifat_surat'] === 'biasa' ? 'normal' : 'urgent' ?>"><?= ucfirst($m['sifat_surat']) ?></span></td>
                                    <td>
                                        <div style="display: flex; gap: 0.5rem; justify-content: center;">
                                            <?php if ($m['file_path']): ?>
                                                <a href="../<?= htmlspecialchars($m['file_path']) ?>" target="_blank" class="btn-dispo" style="background: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd;" title="Preview Dokumen">
                                                    <svg class="icon" viewBox="0 0 24 24" style="width: 16px; height: 16px;"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                                </a>
                                            <?php endif; ?>

                                            <?php if ($tab === 'unread'): ?>
                                                <a href="disposisi_surat.php?id=<?= $m['id_surat_masuk'] ?>" class="btn-dispo"><svg class="icon"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 1 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg> Disposisi</a>
                                            <?php else: ?>
                                                <a href="monitoring_surat.php?id=<?= $m['id_surat_masuk'] ?>" class="btn-dispo" style="background: var(--bg-body); color: var(--text-main); border: 1px solid var(--border);"><svg class="icon"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg> Track</a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
    <script src="../js/notifications.js"></script>
</body>
</html>
