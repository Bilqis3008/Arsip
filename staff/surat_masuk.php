<?php
session_start();
require_once '../config/db.php';

// Auth Check for Staff
if (!isset($_SESSION['user_nip']) || $_SESSION['user_role'] !== 'staff') {
    header('Location: ../auth/login.php');
    exit;
}

$nip_staff = $_SESSION['user_nip'];

// Fetch User Data for Header
$stmt = $pdo->prepare("SELECT u.*, s.nama_seksi FROM users u LEFT JOIN seksi s ON u.id_seksi = s.id_seksi WHERE u.nip = ?");
$stmt->execute([$nip_staff]);
$admin = $stmt->fetch();

$id_seksi = $_SESSION['user_seksi'] ?? $admin['id_seksi'];
$id_bidang = $_SESSION['user_bidang'] ?? $admin['id_bidang'];

$search = $_GET['search'] ?? '';
$tab = $_GET['tab'] ?? 'pending'; // pending | history

// --- FETCH TASK LIST ---
$query = "SELECT sm.*, 
          sk.id_surat_keluar as reply_id,
          sk.status as reply_status,
          d.nip_penerima,
          u.nama as pemberi_nama,
          p.nama as penerima_nama,
          sk.status as reply_status_sk
          FROM surat_masuk sm
          LEFT JOIN (
              SELECT * FROM disposisi WHERE id_disposisi IN (
                  SELECT MAX(id_disposisi) FROM disposisi 
                  WHERE (id_seksi = ? OR (id_bidang = ? AND id_seksi IS NULL AND ? IS NULL))
                  GROUP BY id_surat_masuk
              )
          ) d ON sm.id_surat_masuk = d.id_surat_masuk
          LEFT JOIN users u ON d.nip_pemberi = u.nip
          LEFT JOIN users p ON d.nip_penerima = p.nip
          LEFT JOIN surat_keluar sk ON sm.id_surat_masuk = sk.id_surat_masuk
          WHERE (sm.id_seksi = ? OR (sm.id_bidang = ? AND sm.id_seksi IS NULL AND ? IS NULL))
          AND sm.perlu_balasan = 1 
          AND " . ($tab === 'pending' ? "(sk.id_surat_keluar IS NULL)" : "(sk.id_surat_keluar IS NOT NULL OR (sm.status IN ('selesai', 'diarsipkan') AND sm.perlu_balasan = 0))") . "
          AND (sm.perihal LIKE ? OR sm.nomor_surat LIKE ? OR sm.pengirim LIKE ?)
          ORDER BY sm.tanggal_terima DESC LIMIT 50";

$stmt = $pdo->prepare($query);
$stmt->execute([$id_seksi, $id_bidang, $id_seksi, $id_seksi, $id_bidang, $id_seksi, "%$search%", "%$search%", "%$search%"]);
$tasks = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Surat Tugas - Staff Operational</title>
    <link rel="stylesheet" href="../css/staff/home.css">
    <link rel="stylesheet" href="../css/staff/surat_masuk.css">
    <link rel="stylesheet" href="../css/notifications.css">
</head>
<body>
    <aside class="sidebar">
        <div class="sidebar-header">
            <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
            <h2>STAFF PANEL</h2>
        </div>
        <nav class="sidebar-menu">
            <div class="menu-label">Main Dashboard</div>
            <a href="home.php" class="menu-item"><svg class="icon" viewBox="0 0 24 24"><rect width="7" height="7" x="3" y="3" rx="1"/><rect width="7" height="7" x="14" y="3" rx="1"/><rect width="7" height="7" x="14" y="14" rx="1"/><rect width="7" height="7" x="3" y="14" rx="1"/></svg> Dashboard</a>
            <div class="menu-label">Pekerjaan Saya</div>
            <a href="surat_masuk.php" class="menu-item active"><svg class="icon" viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg> Surat Tugas</a>
            <a href="tindak_lanjut.php" class="menu-item"><svg class="icon" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 1 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg> Kerjakan Balasan</a>
            <div class="menu-label">Monitoring & Arsip</div>
            <a href="monitoring.php" class="menu-item"><svg class="icon" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="m12 8 0 4 2 2"/></svg> Monitoring Alur</a>
            <a href="laporan.php" class="menu-item"><svg class="icon" viewBox="0 0 24 24"><path d="M3 3v18h18"/><path d="m19 9-5 5-4-4-3 3"/></svg> Laporan</a>
            <div class="menu-label">Account</div>
            <a href="profil.php" class="menu-item"><svg class="icon" viewBox="0 0 24 24"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg> Profil Saya</a>
        </nav>
        <div class="sidebar-footer">
            <a href="../auth/logout.php" class="logout-btn"><svg class="icon" viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5"/><path d="M21 12H9"/></svg> Logout</a>
        </div>
    </aside>

    <main class="main-content">
        <header class="content-header">
            <div class="header-title">
                <h1>Agenda Surat Tugas</h1>
                <p>Kelola surat yang ditugaskan ke seksi Anda.</p>
            </div>
            <div class="header-actions">
                <div class="explorer-bar-compact">
                    <form method="GET" class="search-field-premium">
                        <input type="hidden" name="tab" value="<?= htmlspecialchars((string)$tab) ?>">
                        <svg class="icon" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                        <input type="text" name="search" placeholder="Cari perihal..." value="<?= htmlspecialchars((string)($search ?? '')) ?>">
                    </form>
                </div>
                <div class="date-box-header">
                    <div class="date-box-label">Tanggal</div>
                    <div class="date-box-value"><?= date('d F Y') ?></div>
                </div>
                <div class="user-profile-header">
                    <div class="user-info-header">
                        <span class="user-name-header"><?= htmlspecialchars((string)($admin['nama'] ?? '')) ?></span>
                        <span class="user-role-header">Staf <?= htmlspecialchars((string)($admin['nama_seksi'] ?? 'Seksi')) ?></span>
                    </div>
                    <div class="user-avatar-header"><?= strtoupper(substr((string)($admin['nama_seksi'] ?? ''), 0, 1) ?: 'S') ?></div>
                </div>
            </div>
        </header>

        <!-- Tabs Navigation -->
        <div class="tabs-container">
            <a href="surat_masuk.php?tab=pending" class="tab-link <?= $tab === 'pending' ? 'active' : '' ?>">
                <svg class="icon" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="m12 8 0 4 2 2"/></svg>
                Belum Dikerjakan
                <?php if($tab === 'pending'): ?><div class="tab-link-indicator"></div><?php endif; ?>
            </a>
            <a href="surat_masuk.php?tab=history" class="tab-link <?= $tab === 'history' ? 'active' : '' ?>">
                <svg class="icon" viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                Riwayat Selesai
                <?php if($tab === 'history'): ?><div class="tab-link-indicator"></div><?php endif; ?>
            </a>
        </div>

        <section class="task-list">
            <?php if (empty($tasks)): ?>
                <div class="empty-task-container">
                    <svg class="icon empty-task-icon" viewBox="0 0 24 24"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
                    <p class="empty-task-text">
                        <?= $tab === 'pending' ? 'Belum ada surat yang ditugaskan ke seksi ini.' : 'Belum ada riwayat tugas yang tuntas.' ?>
                    </p>
                </div>
            <?php else: ?>
                <?php foreach ($tasks as $t): 
                    $is_selesai = ($tab === 'history');
                    $is_mine = ($t['nip_penerima'] === $_SESSION['user_nip']);
                ?>
                    <div class="task-item <?= $is_mine ? 'task-mine' : '' ?>">
                        <div class="date-box">
                            <div class="day"><?= date('d', strtotime($t['tanggal_terima'] ?? 'now')) ?></div>
                            <div class="month"><?= date('M Y', strtotime($t['tanggal_terima'] ?? 'now')) ?></div>
                        </div>
                        <div class="task-info">
                            <div class="task-status-row">
                                <?php if ($is_selesai): ?>
                                    <?php if (in_array($t['reply_status_sk'], ['disetujui', 'diarsipkan'])): ?>
                                        <span class="badge-selesai">✓ SELESAI</span>
                                    <?php else: ?>
                                        <span class="badge-verifikasi">⏳ SEDANG DIVERIFIKASI</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="task-badge"><?= $is_mine ? 'Tugas Anda' : 'Tugas Seksi' ?></span>
                                <?php endif; ?>
                            </div>
                            <h3 class="task-perihal"><?= htmlspecialchars($t['perihal'] ?? '') ?></h3>
                            <p class="task-nomor">No: <?= htmlspecialchars($t['nomor_surat'] ?? '') ?></p>
                            <?php if ($t['penerima_nama']): ?>
                                <div class="assigned-staff-info">
                                    <svg class="icon assigned-staff-icon" viewBox="0 0 24 24"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                                    Ditugaskan ke: <?= htmlspecialchars($t['penerima_nama']) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="sender-box">
                            <svg class="icon sender-icon" viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                            <span class="sender-name-text"><?= htmlspecialchars($t['pengirim'] ?? '') ?></span>
                        </div>
                        <div class="task-actions">
                            <a href="tindak_lanjut.php?id=<?= (int)$t['id_surat_masuk'] ?>" class="btn-work <?= $is_selesai ? 'btn-work-history' : '' ?>">
                                <?php if ($is_selesai): ?>
                                    <svg class="icon" viewBox="0 0 24 24"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg> Lihat Arsip
                                <?php else: ?>
                                    <svg class="icon" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 1 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg> Kerjakan
                                <?php endif; ?>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>
    </main>
    <script src="../js/notifications.js"></script>
</body>
</html>
