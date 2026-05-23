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

// --- HANDLE CANCEL DISPOSITION ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_disposisi'])) {
    $id_target = $_POST['id_surat'];
    try {
        $pdo->beginTransaction();

        // 1. Delete disposisi entries created by Kepala Dinas for this letter
        $stmt1 = $pdo->prepare("DELETE FROM disposisi WHERE id_surat_masuk = ? AND nip_pemberi = ?");
        $stmt1->execute([$id_target, $nip]);

        // 2. Set status of surat_masuk back to 'tercatat' (belum didisposisi) and set id_bidang, id_seksi to NULL
        $stmt2 = $pdo->prepare("UPDATE surat_masuk SET status = 'tercatat', id_bidang = NULL, id_seksi = NULL WHERE id_surat_masuk = ?");
        $stmt2->execute([$id_target]);

        $pdo->commit();

        header("Location: surat_masuk.php?tab=unread");
        exit;
    } catch (PDOException $e) {
        $pdo->rollBack();
        echo "<script>alert('Gagal membatalkan disposisi: " . addslashes($e->getMessage()) . "');</script>";
    }
}

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
            <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
            <h2>KEPALA DINAS</h2>
        </div>
        <nav class="sidebar-menu">
            <div class="menu-label">Main Executive</div>
            <a href="home.php" class="menu-item"><svg class="icon" viewBox="0 0 24 24"><rect width="7" height="7" x="3" y="3" rx="1"/><rect width="7" height="7" x="14" y="3" rx="1"/><rect width="7" height="7" x="14" y="14" rx="1"/><rect width="7" height="7" x="3" y="14" rx="1"/></svg> Dashboard</a>
            <div class="menu-label">Disposisi & Agenda</div>
            <a href="surat_masuk.php" class="menu-item active"><svg class="icon" viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg> Surat Masuk</a>
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
            <div class="header-title"><h1>Daftar Agenda Surat Masuk</h1></div>
            <div class="user-profile">
                <div class="user-info"><span class="user-name"><?= htmlspecialchars($head['nama']) ?></span><span class="user-role">Kepala Dinas</span></div>
                <div class="user-avatar"><?= strtoupper(substr($head['nama'], 0, 1)) ?></div>
            </div>
        </header>

        <div class="content-body">
            <!-- Tabs -->
            <div class="kadin-tabs">
                <a href="surat_masuk.php?tab=unread" class="tab-btn <?= $tab === 'unread' ? 'active' : '' ?>"><svg class="icon" viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg> Belum Didisposisi</a>
                <a href="surat_masuk.php?tab=history" class="tab-btn <?= $tab === 'history' ? 'active' : '' ?>"><svg class="icon" viewBox="0 0 24 24"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg> Riwayat Disposisi</a>
            </div>

            <!-- Search Explorer -->
            <div class="explorer-bar">
                <form method="GET" class="search-box">
                    <input type="hidden" name="tab" value="<?= $tab ?>">
                    <svg class="icon" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
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
                            <tr><td colspan="5" class="empty-table-cell">Tidak ada data untuk ditampilkan.</td></tr>
                        <?php else: ?>
                            <?php foreach ($mails as $m): ?>
                                <tr>
                                    <td><b><?= date('d/m/Y', strtotime($m['tanggal_terima'])) ?></b></td>
                                    <td>
                                        <div class="mail-title"><?= htmlspecialchars($m['perihal']) ?></div>
                                        <div class="mail-subtitle">No: <?= htmlspecialchars($m['nomor_surat']) ?></div>
                                    </td>
                                    <td><?= htmlspecialchars($m['pengirim']) ?></td>
                                    <td><span class="badge-<?= $m['sifat_surat'] === 'biasa' ? 'normal' : 'urgent' ?>"><?= ucfirst($m['sifat_surat']) ?></span></td>
                                    <td>
                                        <div class="action-container">
                                            <?php if ($m['file_path']): ?>
                                                <a href="../<?= htmlspecialchars($m['file_path']) ?>" target="_blank" class="btn-dispo btn-preview-doc" title="Preview Dokumen">
                                                    <svg class="icon btn-icon-small" viewBox="0 0 24 24"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
                                                </a>
                                            <?php endif; ?>

                                            <?php if ($tab === 'unread'): ?>
                                                <a href="disposisi_surat.php?id=<?= $m['id_surat_masuk'] ?>" class="btn-dispo"><svg class="icon" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 1 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg> Disposisi</a>
                                            <?php else: ?>
                                                <a href="monitoring_surat.php?id=<?= $m['id_surat_masuk'] ?>" class="btn-dispo btn-track-doc"><svg class="icon" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg> Track</a>
                                                <?php if ($m['status'] === 'didispokan'): ?>
                                                    <form method="POST" style="margin: 0; display: inline-block;" onsubmit="return confirm('Apakah Anda yakin ingin membatalkan disposisi untuk surat &quot;<?= htmlspecialchars($m['perihal']) ?>&quot;?');">
                                                        <input type="hidden" name="id_surat" value="<?= $m['id_surat_masuk'] ?>">
                                                        <button type="submit" name="cancel_disposisi" class="btn-dispo btn-cancel-dispo">
                                                            <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg> Batal Disposisi
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
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
