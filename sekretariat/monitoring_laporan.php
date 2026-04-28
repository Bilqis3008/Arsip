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

$stmt_admin = $pdo->query("SELECT id_bidang, nama FROM users WHERE role = 'admin_bidang'");
$admin_bidang_list = [];
while ($row = $stmt_admin->fetch()) {
    $admin_bidang_list[$row['id_bidang']] = $row['nama'];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Arsip - Arsip Digital Premium</title>
    <link rel="stylesheet" href="../css/sekretariat/home.css">
    <link rel="stylesheet" href="../css/sekretariat/surat_masuk.css">
    <link rel="stylesheet" href="../css/sekretariat/monitoring_laporan.css">
    <link rel="stylesheet" href="../css/notifications.css">
    <style>
        .report-summary-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.5rem; margin-bottom: 2.5rem; }
        .summary-premium-card { background: #fff; padding: 2rem; border-radius: 2rem; border: 1px solid var(--border); display: flex; align-items: center; gap: 1.5rem; transition: var(--transition); }
        .summary-premium-card:hover { transform: translateY(-5px); box-shadow: var(--shadow-premium); }
        .s-icon { width: 60px; height: 60px; border-radius: 1.25rem; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; }
        .s-info h4 { font-size: 0.75rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.1em; margin-bottom: 0.25rem; }
        .s-info .s-value { font-size: 2rem; font-weight: 900; color: var(--text-main); line-height: 1.2; }
        @media print { .sidebar, .content-header, .filter-card, .btn, .action-cell { display: none !important; } .main-content { margin-left: 0 !important; } .summary-premium-card { border: 1px solid #000 !important; } }
    </style>
</head>
<body>
    <aside class="sidebar">
        <div class="sidebar-header">
            <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path></svg>
            <h2>ARSIP DIGITAL</h2>
        </div>
        <nav class="sidebar-menu">
            <div class="menu-label">Menu Utama</div>
            <a href="home.php" class="menu-item"><svg class="icon" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg> Dashboard</a>
            <div class="menu-label">Buku Agenda</div>
            <a href="surat_masuk.php" class="menu-item"><svg class="icon" viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg> Surat Masuk</a>
            <a href="surat_keluar.php" class="menu-item"><svg class="icon" viewBox="0 0 24 24"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg> Surat Keluar</a>
            <div class="menu-label">Administrasi Sistem</div>
            <a href="manajemen_pengguna.php" class="menu-item"><svg class="icon" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle></svg> Manajemen Pengguna</a>
            <a href="verifikasi_staff.php" class="menu-item"><svg class="icon" viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg> Verifikasi Staff</a>
            <a href="monitoring_surat.php" class="menu-item"><svg class="icon" viewBox="0 0 24 24"><path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"></path><polyline points="14 2 14 8 20 8"></polyline><path d="M16 13a2 2 0 1 1-4 0v-2a2 2 0 1 0-4 0"></path><line x1="12" y1="14" x2="12" y2="19"></line></svg> Monitoring Surat</a>
            <div class="menu-label">Monitoring</div>
            <a href="monitoring_laporan.php" class="menu-item active"><svg class="icon" viewBox="0 0 24 24"><line x1="18" y1="20" x2="18" y2="10"></line><line x1="12" y1="20" x2="12" y2="4"></line><line x1="6" y1="20" x2="6" y2="14"></line></svg> Laporan</a>
            <div class="menu-label">Akun</div>
            <a href="profil.php" class="menu-item"><svg class="icon" viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg> Profil Saya</a>
        </nav>
        <div class="sidebar-footer"><a href="../auth/logout.php" class="logout-btn"><svg class="icon" viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg> Keluar Sistem</a></div>
    </aside>

    <main class="main-content">
        <header class="content-header">
            <div class="header-title"><h1>Laporan Arsip</h1><p style="font-size:0.9rem; color:var(--text-muted);">Rekapitulasi data surat masuk dan keluar yang telah terselesaikan.</p></div>
            <div class="user-profile">
                <div class="user-info">
                    <span class="user-name"><?= htmlspecialchars($admin['nama'] ?? 'Admin') ?></span>
                    <span class="user-role">Sekretariat</span>
                </div>
                <div class="user-avatar"><?= strtoupper(substr((string)($admin['nama'] ?? 'A'), 0, 1)) ?></div>
            </div>
        </header>

        <div class="content-body">
            <div class="filter-card" style="margin-bottom:2rem;">
                <form method="GET" id="reportForm" style="display:flex; flex-wrap:wrap; gap:1.5rem; align-items:flex-end;">
                    <div class="form-group">
                        <label>Jenis Laporan</label>
                        <select name="jenis_laporan" onchange="this.form.submit()">
                            <option value="total_surat" <?= $jenis_laporan === 'total_surat' ? 'selected' : '' ?>>Total Keseluruhan</option>
                            <option value="surat_masuk" <?= $jenis_laporan === 'surat_masuk' ? 'selected' : '' ?>>Hanya Surat Masuk</option>
                            <option value="surat_keluar" <?= $jenis_laporan === 'surat_keluar' ? 'selected' : '' ?>>Hanya Surat Keluar</option>
                        </select>
                    </div>
                    <div class="form-group"><label>Mulai</label><input type="date" name="date_start" value="<?= $date_start ?>"></div>
                    <div class="form-group"><label>Sampai</label><input type="date" name="date_end" value="<?= $date_end ?>"></div>
                    <button type="submit" class="btn btn-primary" style="height:46px;"><svg class="icon"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg> Filter</button>
                    <button type="button" onclick="window.print()" class="btn btn-success" style="height:46px; margin-left:auto;"><svg class="icon"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg> Cetak Laporan</button>
                </form>
            </div>

            <div class="report-summary-grid">
                <div class="summary-premium-card">
                    <div class="s-icon" style="background:rgba(59, 130, 246, 0.1); color:var(--primary);"><svg class="icon" style="width:30px; height:30px;"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg></div>
                    <div class="s-info"><h4>Masuk Periode</h4><div class="s-value"><?= $total_masuk_period ?></div></div>
                </div>
                <div class="summary-premium-card">
                    <div class="s-icon" style="background:rgba(16, 185, 129, 0.1); color:var(--success);"><svg class="icon" style="width:30px; height:30px;"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg></div>
                    <div class="s-info"><h4>Keluar Periode</h4><div class="s-value"><?= $total_keluar_period ?></div></div>
                </div>
                <div class="summary-premium-card" style="background:linear-gradient(135deg, var(--primary), var(--primary-dark)); color:#fff; border:none;">
                    <div class="s-icon" style="background:rgba(255,255,255,0.2); color:#fff;"><svg class="icon" style="width:30px; height:30px;"><path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"></path><polyline points="14 2 14 8 20 8"></polyline></svg></div>
                    <div class="s-info"><h4 style="color:rgba(255,255,255,0.7);">Total Arsip</h4><div class="s-value" style="color:#fff;"><?= $total_surat_period ?></div></div>
                </div>
            </div>

            <?php if ($jenis_laporan === 'surat_masuk' || $jenis_laporan === 'total_surat'): ?>
                <div class="report-content" style="margin-bottom:3rem;">
                    <div class="table-header"><h3>Daftar Surat Masuk Terselesaikan</h3><span class="badge badge-success"><?= $total_masuk_period ?> Dokumen</span></div>
                    <div class="table-container">
                        <table class="data-table">
                            <thead><tr><th>Identitas Surat</th><th>Pengirim</th><th>Tanggal Terima</th><th>Perihal</th><th style="text-align:center;">Aksi</th></tr></thead>
                            <tbody>
                                <?php if (empty($report_masuk)): ?>
                                    <tr><td colspan="5" style="text-align:center; padding:3rem; color:var(--text-muted);">Tidak ada data surat masuk.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($report_masuk as $m): ?>
                                        <tr>
                                            <td><div style="font-weight:800;"><?= htmlspecialchars($m['nomor_surat'] ?? '') ?></div><div style="font-size:0.75rem; color:var(--text-muted);">Agenda: <?= htmlspecialchars($m['nomor_agenda'] ?? '-') ?></div></td>
                                            <td><?= htmlspecialchars($m['pengirim'] ?? '-') ?></td>
                                            <td><b><?= date('d/m/Y', strtotime($m['tanggal_terima'] ?? 'now')) ?></b></td>
                                            <td>
                                                <div style="font-weight:700;"><?= htmlspecialchars($m['perihal'] ?? '') ?></div>
                                                <?php if (!empty($m['reply_no'])): ?>
                                                    <div style="font-size:0.75rem; color:var(--success); margin-top:4px; font-weight:700;"><svg class="icon" style="width:10px; height:10px;"><polyline points="20 6 9 17 4 12"></polyline></svg> Dibalas: <?= htmlspecialchars($m['reply_no']) ?></div>
                                                <?php endif; ?>
                                            </td>
                                            <td class="action-cell">
                                                <div style="display:flex; gap:0.5rem; justify-content:center;">
                                                    <?php if ($m['file_path']): ?><a href="../<?= htmlspecialchars($m['file_path']) ?>" target="_blank" class="action-btn btn-view" title="Lihat Surat"><svg class="icon"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg></a><?php endif; ?>
                                                    <?php if ($m['reply_file']): ?><a href="../uploads/surat_keluar/<?= htmlspecialchars($m['reply_file']) ?>" target="_blank" class="action-btn btn-edit" style="background:rgba(245, 158, 11, 0.1); color:var(--warning);" title="Lihat Balasan"><svg class="icon"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg></a><?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($jenis_laporan === 'surat_keluar' || $jenis_laporan === 'total_surat'): ?>
                <div class="report-content">
                    <div class="table-header"><h3>Daftar Surat Keluar Diarsipkan</h3><span class="badge badge-info"><?= $total_keluar_period ?> Dokumen</span></div>
                    <div class="table-container">
                        <table class="data-table">
                            <thead><tr><th>Identitas Surat</th><th>Tujuan</th><th>Tanggal Surat</th><th>Perihal</th><th style="text-align:center;">Aksi</th></tr></thead>
                            <tbody>
                                <?php if (empty($report_keluar)): ?>
                                    <tr><td colspan="5" style="text-align:center; padding:3rem; color:var(--text-muted);">Tidak ada data surat keluar.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($report_keluar as $k): ?>
                                        <tr>
                                            <td><div style="font-weight:800;"><?= htmlspecialchars($k['nomor_surat_keluar'] ?? '') ?></div></td>
                                            <td><?= htmlspecialchars($k['tujuan'] ?? '-') ?></td>
                                            <td><b><?= date('d/m/Y', strtotime($k['tanggal_surat'] ?? 'now')) ?></b></td>
                                            <td><?= htmlspecialchars($k['perihal'] ?? '') ?></td>
                                            <td class="action-cell">
                                                <div style="display:flex; justify-content:center;">
                                                    <?php if ($k['file_path']): ?><a href="../uploads/surat_keluar/<?= htmlspecialchars($k['file_path']) ?>" target="_blank" class="action-btn btn-view" title="Lihat Dokumen"><svg class="icon"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg></a><?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>
    <script src="../js/notifications.js"></script>
</body>
</html>