<?php
session_start();
require_once '../config/db.php';

// Auth Check for Staff
if (!isset($_SESSION['user_nip']) || $_SESSION['user_role'] !== 'staff') {
    header('Location: ../auth/login.php');
    exit;
}

$nip = $_SESSION['user_nip'];

// Fetch Staff Info (Bidang & Seksi)
$stmt = $pdo->prepare("SELECT u.*, b.nama_bidang, s.nama_seksi FROM users u 
                       LEFT JOIN bidang b ON u.id_bidang = b.id_bidang 
                       LEFT JOIN seksi s ON u.id_seksi = s.id_seksi 
                       WHERE u.nip = ?");
$stmt->execute([$nip]);
$user = $stmt->fetch();

$id_seksi = $user['id_seksi'];

// --- STATS ---
// 1. Tugas Belum Selesai (Ada di seksi ini tapi belum ada balasan yang diarsipkan)
$stmt = $pdo->prepare("SELECT COUNT(*) FROM surat_masuk sm 
                       LEFT JOIN surat_keluar sk ON sm.id_surat_masuk = sk.id_surat_masuk
                       WHERE sm.id_seksi = ? AND (sk.status IS NULL OR sk.status != 'diarsipkan')");
$stmt->execute([$id_seksi]);
$count_pending = $stmt->fetchColumn();

// 2. Draft Balasan (Surat Keluar status draft by this staff)
$stmt = $pdo->prepare("SELECT COUNT(*) FROM surat_keluar WHERE uploaded_by = ? AND status = 'draft'");
$stmt->execute([$nip]);
$count_draft = $stmt->fetchColumn();

// 3. Tugas Selesai (Balasan sudah disetujui/diarsipkan)
$stmt = $pdo->prepare("SELECT COUNT(*) FROM surat_keluar sk 
                       JOIN surat_masuk sm ON sk.id_surat_masuk = sm.id_surat_masuk
                       WHERE sm.id_seksi = ? AND sk.status = 'diarsipkan'");
$stmt->execute([$id_seksi]);
$count_finished = $stmt->fetchColumn();

// 4. Total Upload
$stmt = $pdo->prepare("SELECT COUNT(*) FROM surat_keluar WHERE uploaded_by = ?");
$stmt->execute([$nip]);
$count_uploads = $stmt->fetchColumn();

// --- RECENT INSTRUCTIONS ---
$stmt = $pdo->prepare("SELECT d.*, sm.perihal, u.nama as pemberi_nama 
                       FROM disposisi d 
                       JOIN surat_masuk sm ON d.id_surat_masuk = sm.id_surat_masuk 
                       JOIN users u ON d.nip_pemberi = u.nip
                       WHERE d.id_seksi = ? 
                       ORDER BY d.tanggal_disposisi DESC LIMIT 5");
$stmt->execute([$id_seksi]);
$recent_dispo = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Dashboard - Arsip Kadin</title>
    <link rel="stylesheet" href="../css/staff/home.css">
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
            <a href="home.php" class="menu-item active"><svg class="icon" viewBox="0 0 24 24"><rect width="7" height="7" x="3" y="3" rx="1"/><rect width="7" height="7" x="14" y="3" rx="1"/><rect width="7" height="7" x="14" y="14" rx="1"/><rect width="7" height="7" x="3" y="14" rx="1"/></svg> Dashboard</a>
            <div class="menu-label">Pekerjaan Saya</div>
            <a href="surat_masuk.php" class="menu-item"><svg class="icon" viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg> Surat Tugas</a>
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
                <h1>Dashboard Operasional</h1>
                <p>Halo, <?= htmlspecialchars((string)($user['nama'] ?? '')) ?>. Berikut ringkasan hari ini.</p>
            </div>
            <div class="header-actions">
                <div class="date-box-header">
                    <div class="date-box-label">Tanggal</div>
                    <div class="date-box-value"><?= date('d F Y') ?></div>
                </div>
                <div class="user-profile-header">
                    <div class="user-info-header">
                        <span class="user-name-header"><?= htmlspecialchars((string)($user['nama'] ?? '')) ?></span>
                        <span class="user-role-header">Staf <?= htmlspecialchars((string)($user['nama_seksi'] ?? 'Seksi')) ?></span>
                    </div>
                    <div class="user-avatar-header"><?= strtoupper(substr((string)($user['nama_seksi'] ?? ''), 0, 1) ?: 'S') ?></div>
                </div>
            </div>
        </header>

        <section class="stats-grid">
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon amber">
                        <svg class="icon" viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                    </div>
                    <span class="badge badge-teal">Tugas</span>
                </div>
                <div class="stat-value"><?= (int)$count_pending ?></div>
                <div class="stat-label">Sedang Diproses</div>
            </div>
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon rose">
                        <svg class="icon" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 1 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                    </div>
                </div>
                <div class="stat-value"><?= (int)$count_draft ?></div>
                <div class="stat-label">Draft Balasan</div>
            </div>
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon teal">
                        <svg class="icon" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                    </div>
                </div>
                <div class="stat-value"><?= (int)$count_finished ?></div>
                <div class="stat-label">Selesai Dikerjakan</div>
            </div>
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon blue">
                        <svg class="icon" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="m17 8-5-5-5 5"/><path d="M12 3v12"/></svg>
                    </div>
                </div>
                <div class="stat-value"><?= (int)$count_uploads ?></div>
                <div class="stat-label">Surat Diunggah</div>
            </div>
        </section>

        <div class="data-card">
            <div class="card-header">
                <h3>Instruksi Tindak Lanjut Terbaru</h3>
                <a href="surat_masuk.php" class="link-primary-sm">Lihat Semua &rarr;</a>
            </div>
            <div class="p-4-container">
                <?php if (empty($recent_dispo)): ?>
                    <p class="empty-state-text">Belum ada instruksi tugas baru.</p>
                <?php else: ?>
                    <table class="data-table-full">
                        <thead>
                            <tr class="table-header-row">
                                <th class="table-header-col">Perihal Surat</th>
                                <th class="table-header-col">Instruksi Pimpinan</th>
                                <th class="table-header-col">Tanggal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_dispo as $d): ?>
                                <tr class="table-body-row">
                                    <td class="table-body-col">
                                        <div class="perihal-title-text"><?= htmlspecialchars($d['perihal'] ?? '') ?></div>
                                        <div class="pemberi-subtext">Dari: <?= htmlspecialchars($d['pemberi_nama'] ?? '') ?></div>
                                    </td>
                                    <td class="instruksi-col-text"><?= nl2br(htmlspecialchars($d['isi_disposisi'] ?? '')) ?></td>
                                    <td class="date-col-text"><?= date('d M Y', strtotime($d['tanggal_disposisi'] ?? 'now')) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </main>
    <script src="../js/notifications.js"></script>
</body>
</html>
