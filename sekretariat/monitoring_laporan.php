<?php
session_start();
require_once '../config/db.php';

// Auth Check
if (!isset($_SESSION['user_nip']) || $_SESSION['user_role'] !== 'sekretariat') {
    header('Location: ../auth/login.php');
    exit;
}

$nip_admin = $_SESSION['user_nip'];

// --- FETCH ADMIN DATA ---
$stmt = $pdo->prepare("SELECT * FROM users WHERE nip = ?");
$stmt->execute([$nip_admin]);
$admin = $stmt->fetch();

$kadin = $pdo->query("SELECT nama FROM users WHERE role='kepala_dinas' LIMIT 1")->fetchColumn() ?: 'Kepala Dinas';

// --- HANDLE FILTERS ---
$jenis_laporan = $_GET['jenis_laporan'] ?? 'total_surat';
$date_start = $_GET['date_start'] ?? date('Y-m-01');
$date_end = $_GET['date_end'] ?? date('Y-m-t');

// --- FETCH DATA (ONLY FINISHED/DIARSIPKAN STATUS) ---
$report_masuk = [];
$report_keluar = [];

if ($jenis_laporan === 'surat_masuk' || $jenis_laporan === 'total_surat') {
    $stmt_m = $pdo->prepare("SELECT sm.*, d.tanggal_disposisi, d.status_disposisi, b.nama_bidang, s.nama_seksi, u_in.nama as nama_sekretariat, u_tujuan.nama as nama_admin_bidang,
                            sk.status as reply_status, sk.nomor_surat_keluar as reply_no, sk.id_surat_keluar, sk.file_path as reply_file, u_reply.nama as nama_staf_reply
          FROM surat_masuk sm
          LEFT JOIN users u_in ON sm.input_by = u_in.nip 
          LEFT JOIN (
              SELECT d1.* FROM disposisi d1
              INNER JOIN (
                  SELECT id_surat_masuk, MAX(id_disposisi) as max_id 
                  FROM disposisi 
                  GROUP BY id_surat_masuk
              ) d2 ON d1.id_disposisi = d2.max_id
          ) d ON sm.id_surat_masuk = d.id_surat_masuk
          LEFT JOIN bidang b ON d.id_bidang = b.id_bidang
          LEFT JOIN seksi s ON d.id_seksi = s.id_seksi
          LEFT JOIN users u_tujuan ON d.nip_tujuan = u_tujuan.nip 
          LEFT JOIN (
              SELECT sk1.* FROM surat_keluar sk1
              INNER JOIN (
                  SELECT id_surat_masuk, MAX(id_surat_keluar) as max_id_sk
                  FROM surat_keluar WHERE id_surat_masuk IS NOT NULL
                  GROUP BY id_surat_masuk
              ) sk2 ON sk1.id_surat_keluar = sk2.max_id_sk
          ) sk ON sm.id_surat_masuk = sk.id_surat_masuk
          LEFT JOIN users u_reply ON sk.uploaded_by = u_reply.nip
          WHERE DATE(sm.tanggal_terima) BETWEEN ? AND ? 
          AND sm.status IN ('selesai', 'diarsipkan') 
          ORDER BY sm.created_at DESC");
    $stmt_m->execute([$date_start, $date_end]);
    $report_masuk = $stmt_m->fetchAll();
}

if ($jenis_laporan === 'surat_keluar' || $jenis_laporan === 'total_surat') {
    $stmt_k = $pdo->prepare("SELECT sk.*, u.nama as pengirim, u.id_bidang, s.nama_seksi, b.nama_bidang 
          FROM surat_keluar sk 
          LEFT JOIN users u ON sk.uploaded_by = u.nip 
          LEFT JOIN seksi s ON u.id_seksi = s.id_seksi 
          LEFT JOIN bidang b ON u.id_bidang = b.id_bidang 
          WHERE DATE(sk.tanggal_surat) BETWEEN ? AND ? 
          AND sk.status = 'diarsipkan' 
          ORDER BY sk.created_at DESC");
    $stmt_k->execute([$date_start, $date_end]);
    $report_keluar = $stmt_k->fetchAll();
}

// --- TOTALS ---
$total_masuk_period = count($report_masuk);
$total_keluar_period = count($report_keluar);
$total_surat_period = $total_masuk_period + $total_keluar_period;

// Fetch mappings of Admin Bidang by id_bidang
$stmt_admin = $pdo->query("SELECT id_bidang, nama FROM users WHERE role = 'admin_bidang'");
$admin_bidang_list = [];
while ($row = $stmt_admin->fetch()) {
    $admin_bidang_list[$row['id_bidang']] = $row['nama'];
}

$total_masuk_all = $pdo->query("SELECT COUNT(*) FROM surat_masuk WHERE status IN ('selesai', 'diarsipkan')")->fetchColumn();
$total_keluar_all = $pdo->query("SELECT COUNT(*) FROM surat_keluar WHERE status = 'diarsipkan'")->fetchColumn();
$total_surat_all = $total_masuk_all + $total_keluar_all;
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan - Arsip Digital</title>
    <link rel="stylesheet" href="../css/sekretariat/home.css">
    <link rel="stylesheet" href="../css/sekretariat/monitoring_laporan.css">
    <style>
        .summary-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.5rem; margin-top: 1.5rem; }
        .summary-card { background: #fff; padding: 2rem; border-radius: 1rem; border: 1px solid #e2e8f0; display: flex; align-items: center; gap: 1.5rem; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
        .summary-icon { width: 64px; height: 64px; border-radius: 1rem; display: flex; align-items: center; justify-content: center; font-size: 2rem; }
        .icon-masuk { background: rgba(59, 130, 246, 0.1); color: #3b82f6; }
        .icon-keluar { background: rgba(16, 185, 129, 0.1); color: #10b981; }
        .icon-total { background: rgba(99, 102, 241, 0.1); color: #6366f1; }
        .summary-details h4 { color: #64748b; font-size: 0.9rem; margin: 0 0 0.5rem 0; font-weight: 700; text-transform: uppercase; }
        .summary-details .value { font-size: 2.25rem; font-weight: 900; color: #0f172a; line-height: 1; }
        .section-title { font-size: 1.25rem; font-weight: 800; color: #0f172a; margin-bottom: 1.5rem; border-bottom: 2px solid #e2e8f0; padding-bottom: 0.75rem; }

        /* Modal styles from monitoring_surat */
        .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.5); z-index: 1000; align-items: center; justify-content: center; padding: 1rem; backdrop-filter: blur(4px); }
        .modal-content { background: #fff; width: 100%; max-width: 500px; border-radius: 1rem; padding: 2rem; position: relative; max-height: 90vh; overflow-y: auto; }
        .modal-close { position: absolute; top: 1.5rem; right: 1.5rem; background: none; border: none; font-size: 1.5rem; color: #64748b; cursor: pointer; }
        .timeline { position: relative; margin-top: 1rem; padding-left: 20px; border-left: 2px solid #e2e8f0; }
        .timeline-item { position: relative; padding-bottom: 1.5rem; }
        .timeline-item::before { content: ''; position: absolute; left: -26px; top: 0; width: 10px; height: 10px; border-radius: 50%; background: #fff; border: 2px solid #cbd5e1; }
        .timeline-item.done::before { background: #10b981; border-color: #10b981; }
        .timeline-item.active::before { background: #3b82f6; border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59,130,246,0.2); }
        .timeline-content h4 { margin: 0 0 0.25rem; color: #0f172a; font-size: 0.95rem; }
        .timeline-content p { margin: 0; color: #64748b; font-size: 0.85rem; line-height: 1.4; }
        .timeline-time { font-size: 0.75rem; color: #94a3b8; margin-top: 0.25rem; }
        
        .action-cell { display: flex; gap: 0.5rem; justify-content: center; }
        .action-btn { width: 34px; height: 34px; border-radius: 0.5rem; display: inline-flex; align-items: center; justify-content: center; border: none; cursor: pointer; transition: all 0.2s; color: white; text-decoration: none; }
        .action-btn svg { width: 18px; height: 18px; fill: none; stroke: currentColor; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }
        .action-btn-info { background: #3b82f6; }
        .action-btn-info:hover { background: #2563eb; }
        .action-btn-download { background: #10b981; }
        .action-btn-download:hover { background: #059669; }
        .action-btn-disabled { background: #e2e8f0; color: #94a3b8; cursor: not-allowed; }
    </style>
</head>

<body>
    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <svg class="icon" style="width: 24px; height: 24px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path></svg>
            <h2>ARSIP DIGITAL</h2>
        </div>

        <nav class="sidebar-menu">
            <div class="menu-label">Menu Utama</div>
            <a href="home.php" class="menu-item"><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg> Dashboard</a>

            <div class="menu-label">Buku Agenda</div>
            <a href="surat_masuk.php" class="menu-item"><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg> Surat Masuk</a>

            <div class="menu-label">Administrasi Sistem</div>
            <a href="manajemen_pengguna.php" class="menu-item"><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg> Manajemen Pengguna</a>
            <a href="monitoring_surat.php" class="menu-item"><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"></path><polyline points="14 2 14 8 20 8"></polyline><path d="M16 13a2 2 0 1 1-4 0v-2a2 2 0 1 0-4 0"></path><line x1="12" y1="14" x2="12" y2="19"></line></svg> Monitoring Surat</a>

            <div class="menu-label">Monitoring</div>
            <a href="monitoring_laporan.php" class="menu-item active"><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"></line><line x1="12" y1="20" x2="12" y2="4"></line><line x1="6" y1="20" x2="6" y2="14"></line></svg> Laporan</a>
            
            <div class="menu-label">Akun</div>
            <a href="profil.php" class="menu-item"><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg> Profil Saya</a>
        </nav>

        <div class="sidebar-footer">
            <a href="../auth/logout.php" class="logout-btn">
                <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg> Keluar Sistem
            </a>
        </div>
    </aside>

    <main class="main-content">
        <header class="content-header">
            <div class="header-title">
                <h1>Laporan Arsip Terselesaikan</h1>
            </div>
            <div class="user-profile">
                <div class="user-info">
                    <span class="user-name"><?= htmlspecialchars($admin['nama']) ?></span>
                    <span class="user-role">Sekretariat</span>
                </div>
                <div class="user-avatar"><?= strtoupper(substr($admin['nama'], 0, 1)) ?></div>
            </div>
        </header>

        <div class="content-body">
            <form method="GET" id="reportForm">
                <!-- Wrapper 1: Filter Laporan -->
                <div class="filter-card" style="margin-bottom: 1.25rem ; background: #fff; padding: 1.5rem; border-radius: 1rem; border: 1px solid #e2e8f0;">
                    <div class="form-group" style="margin: 0;">
                        <label style="display: block; font-size: 0.95rem; font-weight: 800; color: #0f172a; margin-bottom: 0.75rem;">Pilih Jenis Laporan Status Selesai / Arsip</label>
                        <select name="jenis_laporan" onchange="document.getElementById('reportForm').submit()" style="padding: 0.75rem 1rem; border: 2px solid #cbd5e1; border-radius: 0.5rem; width: 100%; max-width: 400px; font-weight: 700; color: #0f172a; font-size: 1rem; outline: none;">
                            <option value="surat_masuk" <?= $jenis_laporan === 'surat_masuk' ? 'selected' : '' ?>>Surat Masuk Terselesaikan</option>
                            <option value="surat_keluar" <?= $jenis_laporan === 'surat_keluar' ? 'selected' : '' ?>>Surat Keluar Diarsipkan</option>
                            <option value="total_surat" <?= $jenis_laporan === 'total_surat' ? 'selected' : '' ?>>Laporan Keseluruhan Arsip Total</option>
                        </select>
                    </div>
                </div>

                <!-- Wrapper 2: Filter Tanggal & Cetak -->
                <div class="filter-card" style="margin-bottom: 2rem; background: #fff; padding: 1.5rem; border-radius: 1rem; border: 1px solid #e2e8f0; display: flex; gap: 1.5rem; align-items: flex-end; flex-wrap: wrap;">
                    <div class="form-group" style="margin: 0;">
                        <label style="display: block; font-size: 0.85rem; font-weight: 700; color: #64748b; margin-bottom: 0.5rem;">Dari Tanggal</label>
                        <input type="date" name="date_start" value="<?= $date_start ?>" style="padding: 0.75rem; border: 1.5px solid #cbd5e1; border-radius: 0.5rem; min-width: 150px;">
                    </div>
                    <div class="form-group" style="margin: 0;">
                        <label style="display: block; font-size: 0.85rem; font-weight: 700; color: #64748b; margin-bottom: 0.5rem;">Sampai Tanggal</label>
                        <input type="date" name="date_end" value="<?= $date_end ?>" style="padding: 0.75rem; border: 1.5px solid #cbd5e1; border-radius: 0.5rem; min-width: 150px;">
                    </div>
                    <button type="submit" class="btn btn-primary" style="padding: 0.75rem 1.5rem; font-weight: 700; border-radius: 0.5rem;">
                        <svg class="icon" viewBox="0 0 24 24" style="width: 18px; height: 18px; margin-right: 0.5rem; vertical-align: bottom; fill: none; stroke: currentColor; stroke-width: 2;"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg> Terapkan Filter
                    </button>
                    <button type="button" onclick="window.print()" class="btn btn-success" style="padding: 0.75rem 1.5rem; font-weight: 700; border-radius: 0.5rem; margin-left: auto;">
                        <svg class="icon" viewBox="0 0 24 24" style="width: 18px; height: 18px; margin-right: 0.5rem; vertical-align: bottom; fill: none; stroke: currentColor; stroke-width: 2;"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg> Cetak Laporan
                    </button>
                </div>
            </form>

            <?php 
                function renderSuratMasukTable($report_masuk) {
            ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Nomor Surat</th>
                            <th>Pengirim</th>
                            <th>Tanggal Terima</th>
                            <th>Perihal</th>
                            <th style="width: 120px; text-align: center;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($report_masuk)): ?>
                            <tr><td colspan="5" style="text-align: center; color: #64748b; padding: 2rem;">Data tidak ditemukan pada periode ini.</td></tr>
                        <?php else: ?>
                            <?php foreach ($report_masuk as $p): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($p['nomor_surat']) ?></strong><br><small><?= htmlspecialchars($p['nomor_agenda']) ?></small></td>
                                    <td><?= htmlspecialchars($p['pengirim']) ?></td>
                                    <td><?= date('d M Y', strtotime($p['tanggal_terima'])) ?></td>
                                    <td style="color: #0f172a; font-weight: 500;">
                                        <?= htmlspecialchars($p['perihal']) ?>
                                        <?php if (!empty($p['reply_no'])): ?>
                                            <div style="margin-top: 4px; font-size: 0.8rem; color: #10b981; font-weight: 600;">
                                                <svg viewBox="0 0 24 24" style="width: 12px; height: 12px; fill: none; stroke: currentColor; stroke-width: 3; vertical-align: middle; margin-right: 2px;"><polyline points="20 6 9 17 4 12"></polyline></svg>
                                                Balasan: <?= htmlspecialchars($p['reply_no']) ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="action-cell">
                                        <button class="action-btn action-btn-info" onclick="showTrackerMasuk(<?= $p['id_surat_masuk'] ?>)" title="Tracking & Detail">
                                            <svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                        </button>
                                        <?php if (!empty($p['file_path'])): ?>
                                            <a href="../<?= htmlspecialchars($p['file_path']) ?>" target="_blank" class="action-btn action-btn-download" title="Lihat/Download Surat Masuk">
                                                <svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                                            </a>
                                        <?php else: ?>
                                            <button class="action-btn action-btn-disabled" title="Dokumen Tidak Tersedia" disabled>
                                                <svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                                            </button>
                                        <?php endif; ?>
                                        <?php if (!empty($p['reply_file'])): ?>
                                            <a href="../uploads/surat_keluar/<?= htmlspecialchars($p['reply_file']) ?>" target="_blank" class="action-btn" style="background:var(--accent);" title="Lihat Balasan (Out)">
                                                <svg viewBox="0 0 24 24" style="width:18px;height:18px;fill:none;stroke:currentColor;stroke-width:2.5;"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            <?php } ?>

            <?php 
                function renderSuratKeluarTable($report_keluar) {
            ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Nomor Surat</th>
                            <th>Tujuan</th>
                            <th>Tanggal Surat</th>
                            <th>Perihal</th>
                            <th style="width: 120px; text-align: center;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($report_keluar)): ?>
                            <tr><td colspan="5" style="text-align: center; color: #64748b; padding: 2rem;">Data tidak ditemukan pada periode ini.</td></tr>
                        <?php else: ?>
                            <?php foreach ($report_keluar as $k): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($k['nomor_surat_keluar']) ?></strong></td>
                                    <td><?= htmlspecialchars($k['tujuan']) ?></td>
                                    <td><?= date('d M Y', strtotime($k['tanggal_surat'])) ?></td>
                                    <td><?= htmlspecialchars($k['perihal']) ?></td>
                                    <td class="action-cell">
                                        <button class="action-btn action-btn-info" onclick="showTrackerKeluar(<?= $k['id_surat_keluar'] ?>)" title="Tracking & Detail">
                                            <svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                        </button>
                                        <?php if (!empty($k['file_path'])): ?>
                                            <a href="../uploads/surat_keluar/<?= htmlspecialchars($k['file_path']) ?>" target="_blank" class="action-btn action-btn-download" title="Lihat/Download Dokumen">
                                                <svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                                            </a>
                                        <?php else: ?>
                                            <button class="action-btn action-btn-disabled" title="Dokumen Tidak Tersedia" disabled>
                                                <svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            <?php } ?>

            <?php if ($jenis_laporan === 'surat_masuk'): ?>
            <section class="module-section active">
                <div class="card" style="border-radius: 1rem;">
                    <div class="table-container">
                        <?php renderSuratMasukTable($report_masuk); ?>
                    </div>
                </div>
            </section>

            <?php elseif ($jenis_laporan === 'surat_keluar'): ?>
            <section class="module-section active">
                <div class="card" style="border-radius: 1rem;">
                    <div class="table-container">
                        <?php renderSuratKeluarTable($report_keluar); ?>
                    </div>
                </div>
            </section>

            <?php elseif ($jenis_laporan === 'total_surat'): ?>
            <section class="module-section active">
                <h3 class="section-title" style="margin-top: 0.5rem;">Laporan Periode Aktif Arsip <span style="color: #64748b; font-size: 0.95rem; font-weight: 500;">(<?= date('d M Y', strtotime($date_start)) ?> - <?= date('d M Y', strtotime($date_end)) ?>)</span></h3>
                <div class="summary-cards">
                    <div class="summary-card">
                        <div class="summary-icon icon-masuk">
                            <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
                        </div>
                        <div class="summary-details">
                            <h4>Surat Masuk Terselesaikan</h4>
                            <div class="value"><?= $total_masuk_period ?></div>
                        </div>
                    </div>
                    <div class="summary-card">
                        <div class="summary-icon icon-keluar">
                            <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>
                        </div>
                        <div class="summary-details">
                            <h4>Surat Keluar Diarsipkan</h4>
                            <div class="value"><?= $total_keluar_period ?></div>
                        </div>
                    </div>
                    <div class="summary-card">
                        <div class="summary-icon icon-total">
                            <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><line x1="3" y1="9" x2="21" y2="9"></line><line x1="9" y1="21" x2="9" y2="9"></line></svg>
                        </div>
                        <div class="summary-details">
                            <h4>Total Keseluruhan Arsip</h4>
                            <div class="value"><?= $total_surat_period ?></div>
                        </div>
                    </div>
                </div>

                <div style="margin-top: 3rem;"></div>
                <h3 class="section-title">Daftar Surat Masuk <span style="color: #64748b; font-size: 0.95rem; font-weight: 500;">(Terselesaikan / Diarsipkan)</span></h3>
                <div class="card" style="border-radius: 1rem; margin-bottom: 2rem;">
                    <div class="table-container">
                        <?php renderSuratMasukTable($report_masuk); ?>
                    </div>
                </div>

                <div style="margin-top: 3rem;"></div>
                <h3 class="section-title">Daftar Surat Keluar <span style="color: #64748b; font-size: 0.95rem; font-weight: 500;">(Telah Diarsipkan)</span></h3>
                <div class="card" style="border-radius: 1rem; margin-bottom: 2rem;">
                    <div class="table-container">
                        <?php renderSuratKeluarTable($report_keluar); ?>
                    </div>
                </div>

            </section>
            <?php endif; ?>
            
        </div>
    </main>

    <!-- Tracking Modal -->
    <div class="modal-overlay" id="tracker-modal" onclick="closeTracker()">
        <div class="modal-content" onclick="event.stopPropagation()">
            <button class="modal-close" onclick="closeTracker()">
                <svg viewBox="0 0 24 24" style="width:24px; height:24px; fill:none; stroke:currentColor; stroke-width:2;"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
            </button>
            <h3 style="margin-bottom: 0.5rem; color: #0f172a; font-size: 1.25rem;">Live Tracking Alur Surat</h3>
            <p id="tracker-subtitle" style="color: #64748b; font-size: 0.9rem; margin-bottom: 1.5rem;"></p>
            
            <div id="tracker-mail-info" style="background: #f8fafc; padding: 1.25rem; border-radius: 0.75rem; border: 1px solid #e2e8f0; margin-bottom: 1.5rem; display: none;"></div>

            <div id="tracker-details">
                <div class="timeline" id="timeline-box"></div>
            </div>
        </div>
    </div>

    <script>
        const suratMasukData = <?= json_encode($report_masuk) ?>;
        const suratKeluarData = <?= json_encode($report_keluar) ?>;
        const namaKadin = <?= json_encode($kadin) ?>;
        const adminBidangDict = <?= json_encode($admin_bidang_list) ?>;

        function showTrackerMasuk(id) {
            const mail = suratMasukData.find(m => m.id_surat_masuk == id);
            
            document.getElementById('tracker-subtitle').textContent = `Surat Masuk #${mail.nomor_surat}`;

            const infoBox = document.getElementById('tracker-mail-info');
            infoBox.style.display = 'block';
            const tgl = new Date(mail.tanggal_terima).toLocaleDateString('id-ID', {day: 'numeric', month: 'short', year: 'numeric'});
            infoBox.innerHTML = `
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; font-size: 0.85rem;">
                    <div style="grid-column: span 2;">
                        <span style="color: #64748b; display: block; font-size: 0.75rem; text-transform: uppercase; font-weight: 700; margin-bottom: 0.25rem;">Perihal</span>
                        <strong style="color: #0f172a; font-size: 0.95rem;">${mail.perihal}</strong>
                    </div>
                    <div>
                        <span style="color: #64748b; display: block; font-size: 0.75rem; text-transform: uppercase; font-weight: 700; margin-bottom: 0.25rem;">Pengirim</span>
                        <strong style="color: #0f172a;">${mail.pengirim}</strong>
                    </div>
                    <div>
                        <span style="color: #64748b; display: block; font-size: 0.75rem; text-transform: uppercase; font-weight: 700; margin-bottom: 0.25rem;">Tanggal Terima</span>
                        <strong style="color: #0f172a;">${tgl}</strong>
                    </div>
                </div>
            `;

            const timeline = document.getElementById('timeline-box');
            timeline.innerHTML = '';

            const sekreName = mail.nama_sekretariat || 'Staf Sekretariat';
            addTimelineItem(`${sekreName} (Sekretariat)`, 'Resepsionis/Sekretariat mencatat agenda baru.', mail.created_at, 'done');

            const kadinFull = `${namaKadin} (Kepala Dinas)`;
            addTimelineItem(kadinFull, 'Memberikan arah dan disposisi kepada unit bersangkutan.', mail.tanggal_disposisi, 'done');

            const adminBidangName = mail.nama_admin_bidang || 'Admin Bidang';
            const deskripsiBidang = mail.nama_bidang ? `(Admin ${mail.nama_bidang})` : '';
            addTimelineItem(`${adminBidangName} ${deskripsiBidang}`.trim(), `Telah ditindaklanjuti dan diselesaikan pada seksi/bidang.`, null, 'done');

            if (mail.reply_status) {
                const staffName = mail.nama_staf_reply || 'Staf Sub-Seksi';
                const seksiTitle = mail.nama_seksi ? `(Staf ${mail.nama_seksi})` : '(Staf Seksi)';
                addTimelineItem(`${staffName} ${seksiTitle}`, `Telah membuat tindak lanjut balasan (${mail.reply_no}).`, null, 'done');
                addTimelineItem(`${adminBidangName} ${deskripsiBidang}`, `Telah memverifikasi dan menyetujui balasan.`, null, 'done');
                addTimelineItem('Finalisasi', 'Surat masuk tuntas dan balasan telah diterbitkan/diarsipkan.', null, 'done');
            } else {
                const divisiTarget = mail.nama_seksi ? (mail.nama_seksi + ' - ' + mail.nama_bidang) : (mail.nama_bidang || 'Seksi / Bidang Terkait');
                addTimelineItem('Arsip Digital', `Surat telah disimpan dalam database arsip pada ${divisiTarget}.`, null, 'done');
            }

            document.getElementById('tracker-modal').style.display = 'flex';
        }

        function showTrackerKeluar(id) {
            const mail = suratKeluarData.find(m => m.id_surat_keluar == id);
            
            document.getElementById('tracker-subtitle').textContent = `Surat Keluar #${mail.nomor_surat_keluar}`;

            const infoBox = document.getElementById('tracker-mail-info');
            infoBox.style.display = 'block';
            const tgl = new Date(mail.tanggal_surat).toLocaleDateString('id-ID', {day: 'numeric', month: 'short', year: 'numeric'});
            infoBox.innerHTML = `
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; font-size: 0.85rem;">
                    <div style="grid-column: span 2;">
                        <span style="color: #64748b; display: block; font-size: 0.75rem; text-transform: uppercase; font-weight: 700; margin-bottom: 0.25rem;">Perihal</span>
                        <strong style="color: #0f172a; font-size: 0.95rem;">${mail.perihal}</strong>
                    </div>
                    <div>
                        <span style="color: #64748b; display: block; font-size: 0.75rem; text-transform: uppercase; font-weight: 700; margin-bottom: 0.25rem;">Tujuan</span>
                        <strong style="color: #0f172a;">${mail.tujuan}</strong>
                    </div>
                    <div>
                        <span style="color: #64748b; display: block; font-size: 0.75rem; text-transform: uppercase; font-weight: 700; margin-bottom: 0.25rem;">Tanggal Surat</span>
                        <strong style="color: #0f172a;">${tgl}</strong>
                    </div>
                </div>
            `;

            const timeline = document.getElementById('timeline-box');
            timeline.innerHTML = '';

            const senderAdmin = mail.pengirim || 'Staf Penulis';
            const senderUnit = mail.nama_seksi || mail.nama_bidang || '';
            const senderFull = senderUnit ? `${senderAdmin} (Staf ${senderUnit})` : senderAdmin;
            
            const adminReviewer = adminBidangDict[mail.id_bidang] || 'Admin Perbidang';
            const reviewerUnit = mail.nama_bidang ? `(Admin ${mail.nama_bidang})` : '';
            const reviewerFull = `${adminReviewer} ${reviewerUnit}`.trim();

            addTimelineItem(senderFull, 'Staf pengusul membuat draft surat.', mail.created_at, 'done');

            addTimelineItem(reviewerFull, 'Telah di tinjau dan disetujui (Verifikasi passed).', null, 'done');
            
            addTimelineItem('Arsip Digital', `Surat keluar telah dikirim dan diarsipkan pada ${senderUnit || 'database arsip'}.`, null, 'done');

            document.getElementById('tracker-modal').style.display = 'flex';
        }

        function addTimelineItem(title, desc, time, type) {
            const box = document.getElementById('timeline-box');
            const item = document.createElement('div');
            item.className = 'timeline-item ' + type;
            item.innerHTML = `
                <div class="timeline-content">
                    <h4>${title}</h4>
                    <p>${desc}</p>
                    ${time ? `<div class="timeline-time">${new Date(time).toLocaleString('id-ID')}</div>` : ''}
                </div>
            `;
            box.appendChild(item);
        }

        function closeTracker() {
            document.getElementById('tracker-modal').style.display = 'none';
        }
    </script>
</body>

</html>