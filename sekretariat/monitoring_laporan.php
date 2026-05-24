<?php
session_start();
require_once '../config/db.php';

// Auth Check
if (!isset($_SESSION['user_nip']) || $_SESSION['user_role'] !== 'sekretariat') {
    header('Location: ../auth/login.php');
    exit;
}

$nip_admin = $_SESSION['user_nip'];
$success_msg = $_SESSION['success_msg'] ?? "";
$error_msg = $_SESSION['error_msg'] ?? "";
unset($_SESSION['success_msg'], $_SESSION['error_msg']);

// --- FETCH ADMIN DATA ---
$stmt = $pdo->prepare("SELECT * FROM users WHERE nip = ?");
$stmt->execute([$nip_admin]);
$admin = $stmt->fetch();

$kadin = $pdo->query("SELECT nama FROM users WHERE role='kepala_dinas' LIMIT 1")->fetchColumn() ?: 'Kepala Dinas';

// --- HANDLE CRUD ACTIONS ---

// 1. DELETE ACTION
if (isset($_GET['delete_id']) && isset($_GET['type'])) {
    $id = (int)$_GET['delete_id'];
    $type = $_GET['type'];
    try {
        if ($type === 'masuk') {
            $pdo->prepare("DELETE FROM surat_masuk WHERE id_surat_masuk = ?")->execute([$id]);
        } else {
            $pdo->prepare("DELETE FROM surat_keluar WHERE id_surat_keluar = ?")->execute([$id]);
        }
        $_SESSION['success_msg'] = "Data berhasil dihapus.";
    } catch (PDOException $e) {
        $_SESSION['error_msg'] = "Gagal menghapus data: " . $e->getMessage();
    }
    header("Location: monitoring_laporan.php");
    exit;
}

// 2. UPDATE ACTION
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'update_masuk') {
        $id = (int)$_POST['id_surat_masuk'];
        $nomor_surat = $_POST['nomor_surat'];
        $pengirim = $_POST['pengirim'];
        $perihal = $_POST['perihal'];
        $tanggal_terima = $_POST['tanggal_terima'];
        $existing_file = $_POST['existing_file_path'] ?? null;
        
        // Handle File Upload
        $file_path = $existing_file;
        if (isset($_FILES['file_surat']) && $_FILES['file_surat']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../uploads/surat_masuk/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            $ext = pathinfo($_FILES['file_surat']['name'], PATHINFO_EXTENSION);
            $new_name = time() . '_EDIT_' . preg_replace("/[^a-zA-Z0-9]/", "_", (string)$perihal) . '.' . $ext;
            if (move_uploaded_file($_FILES['file_surat']['tmp_name'], $upload_dir . $new_name)) {
                // Delete old file if exists
                if ($existing_file && file_exists('../' . $existing_file)) unlink('../' . $existing_file);
                $file_path = 'uploads/surat_masuk/' . $new_name;
            }
        }
        
        try {
            $stmt = $pdo->prepare("UPDATE surat_masuk SET nomor_surat = ?, pengirim = ?, perihal = ?, tanggal_terima = ?, file_path = ? WHERE id_surat_masuk = ?");
            $stmt->execute([$nomor_surat, $pengirim, $perihal, $tanggal_terima, $file_path, $id]);
            $_SESSION['success_msg'] = "Data surat masuk berhasil diperbarui.";
        } catch (PDOException $e) {
            $_SESSION['error_msg'] = "Gagal memperbarui data: " . $e->getMessage();
        }
    } 
    elseif ($action === 'update_keluar') {
        $id = (int)$_POST['id_surat_keluar'];
        $nomor_surat = $_POST['nomor_surat_keluar'];
        $tujuan = $_POST['tujuan'];
        $perihal = $_POST['perihal'];
        $tanggal_surat = $_POST['tanggal_surat'];
        $existing_file = $_POST['existing_file_path'] ?? null;

        // Handle File Upload
        $file_path = $existing_file;
        if (isset($_FILES['file_surat']) && $_FILES['file_surat']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../uploads/surat_keluar/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            $ext = pathinfo($_FILES['file_surat']['name'], PATHINFO_EXTENSION);
            $new_name = time() . '_EDIT_' . preg_replace("/[^a-zA-Z0-9]/", "_", (string)$perihal) . '.' . $ext;
            if (move_uploaded_file($_FILES['file_surat']['tmp_name'], $upload_dir . $new_name)) {
                // Delete old file if exists
                if ($existing_file && file_exists('../uploads/surat_keluar/' . $existing_file)) {
                    unlink('../uploads/surat_keluar/' . $existing_file);
                }
                $file_path = $new_name;
            }
        }
        
        try {
            $stmt = $pdo->prepare("UPDATE surat_keluar SET nomor_surat_keluar = ?, tujuan = ?, perihal = ?, tanggal_surat = ?, file_path = ? WHERE id_surat_keluar = ?");
            $stmt->execute([$nomor_surat, $tujuan, $perihal, $tanggal_surat, $file_path, $id]);
            $_SESSION['success_msg'] = "Data surat keluar berhasil diperbarui.";
        } catch (PDOException $e) {
            $_SESSION['error_msg'] = "Gagal memperbarui data: " . $e->getMessage();
        }
    }
    elseif ($action === 'create_masuk') {
        $nomor_surat = $_POST['nomor_surat'];
        $pengirim = $_POST['pengirim'];
        $perihal = $_POST['perihal'];
        $tanggal_terima = $_POST['tanggal_terima'];
        $nomor_agenda = 'ARS-' . date('Ymd') . '-' . rand(1000, 9999);
        
        try {
            $stmt = $pdo->prepare("INSERT INTO surat_masuk (nomor_agenda, nomor_surat, pengirim, perihal, tanggal_terima, tanggal_surat, status, input_by) VALUES (?, ?, ?, ?, ?, ?, 'diarsipkan', ?)");
            $stmt->execute([$nomor_agenda, $nomor_surat, $pengirim, $perihal, $tanggal_terima, $tanggal_terima, $nip_admin]);
            $_SESSION['success_msg'] = "Arsip surat masuk baru berhasil ditambahkan.";
        } catch (PDOException $e) {
            $_SESSION['error_msg'] = "Gagal menambahkan arsip: " . $e->getMessage();
        }
    }
    elseif ($action === 'create_keluar') {
        $nomor_surat = $_POST['nomor_surat_keluar'];
        $tujuan = $_POST['tujuan'];
        $perihal = $_POST['perihal'];
        $tanggal_surat = $_POST['tanggal_surat'];
        
        try {
            $stmt = $pdo->prepare("INSERT INTO surat_keluar (nomor_surat_keluar, tujuan, perihal, tanggal_surat, status, uploaded_by) VALUES (?, ?, ?, ?, 'diarsipkan', ?)");
            $stmt->execute([$nomor_surat, $tujuan, $perihal, $tanggal_surat, $nip_admin]);
            $_SESSION['success_msg'] = "Arsip surat keluar baru berhasil ditambahkan.";
        } catch (PDOException $e) {
            $_SESSION['error_msg'] = "Gagal menambahkan arsip: " . $e->getMessage();
        }
    }
    
    header("Location: monitoring_laporan.php");
    exit;
}

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
</head>
<body>
    <aside class="sidebar">
        <div class="sidebar-header">
            <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
            <h2>ARSIP DIGITAL</h2>
        </div>
        <nav class="sidebar-menu">
            <div class="menu-label">Menu Utama</div>
            <a href="home.php" class="menu-item"><svg class="icon" viewBox="0 0 24 24"><rect width="7" height="7" x="3" y="3" rx="1"/><rect width="7" height="7" x="14" y="3" rx="1"/><rect width="7" height="7" x="14" y="14" rx="1"/><rect width="7" height="7" x="3" y="14" rx="1"/></svg> Dashboard</a>
            <div class="menu-label">Buku Agenda</div>
            <a href="surat_masuk.php" class="menu-item"><svg class="icon" viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg> Surat Masuk</a>
            <a href="surat_keluar.php" class="menu-item"><svg class="icon" viewBox="0 0 24 24"><path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/></svg> Surat Keluar</a>
            <div class="menu-label">Administrasi Sistem</div>
            <a href="manajemen_pengguna.php" class="menu-item"><svg class="icon" viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg> Manajemen Pengguna</a>
            <a href="monitoring_surat.php" class="menu-item"><svg class="icon" viewBox="0 0 24 24"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg> Monitoring Surat</a>
            <div class="menu-label">Monitoring</div>
            <a href="monitoring_laporan.php" class="menu-item active"><svg class="icon" viewBox="0 0 24 24"><path d="M3 3v18h18"/><path d="m19 9-5 5-4-4-3 3"/></svg> Laporan</a>
            <div class="menu-label">Akun</div>
            <a href="profil.php" class="menu-item"><svg class="icon" viewBox="0 0 24 24"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg> Profil Saya</a>
        </nav>
        <div class="sidebar-footer"><a href="../auth/logout.php" class="logout-btn"><svg class="icon" viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5"/><path d="M21 12H9"/></svg> Logout</a></div>
    </aside>

    <main class="main-content">
        <header class="content-header">
            <div class="header-title"><h1>Laporan Arsip</h1><p class="header-desc-text">Rekapitulasi data surat masuk dan keluar yang telah terselesaikan.</p></div>
            <div class="user-profile">
                <div class="user-info">
                    <span class="user-name"><?= htmlspecialchars($admin['nama'] ?? 'Admin') ?></span>
                    <span class="user-role">Sekretariat</span>
                </div>
                <div class="user-avatar"><?= strtoupper(substr((string)($admin['nama'] ?? 'A'), 0, 1)) ?></div>
            </div>
        </header>

        <div class="content-body">
            <!-- Alert Notifications -->
            <?php if ($success_msg): ?>
                <div class="alert-message-success">
                    <svg class="icon alert-message-success-icon"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg> <?= $success_msg ?>
                </div>
            <?php endif; ?>
            <?php if ($error_msg): ?>
                <div class="alert-message-danger">
                    <svg class="icon alert-message-danger-icon"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg> <?= $error_msg ?>
                </div>
            <?php endif; ?>

            <div class="filter-card filter-card-container">
                <form method="GET" id="reportForm" class="filter-form-premium">
                    <div class="filter-group-main">
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
                        <button type="submit" class="btn btn-primary filter-btn-submit"><svg class="icon" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg> Filter</button>
                    </div>
                    <div class="filter-group-actions">
                        <button type="button" onclick="openAddModal()" class="btn btn-info filter-btn-add"><svg class="icon" viewBox="0 0 24 24"><path d="M12 5v14m-7-7h14"/></svg> Tambah Laporan</button>
                        <button type="button" onclick="window.print()" class="btn btn-success filter-btn-print"><svg class="icon" viewBox="0 0 24 24"><path d="M6 9V2h12v7"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><path d="M6 14h12v8H6z"/></svg> Cetak Laporan</button>
                    </div>
                </form>
            </div>

            <div class="report-summary-grid">
                <div class="summary-premium-card">
                    <div class="s-icon s-icon-masuk"><svg class="icon s-icon-masuk-svg" viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg></div>
                    <div class="s-info"><h4>Masuk Periode</h4><div class="s-value"><?= $total_masuk_period ?></div></div>
                </div>
                <div class="summary-premium-card">
                    <div class="s-icon s-icon-keluar"><svg class="icon s-icon-keluar-svg" viewBox="0 0 24 24"><path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/></svg></div>
                    <div class="s-info"><h4>Keluar Periode</h4><div class="s-value"><?= $total_keluar_period ?></div></div>
                </div>
                <div class="summary-premium-card s-card-total-arsip">
                    <div class="s-icon s-icon-total"><svg class="icon s-icon-total-svg" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M16 13H8"/><path d="M16 17H8"/><path d="M10 9H8"/></svg></div>
                    <div class="s-info"><h4 class="s-info-total-title">Total Arsip</h4><div class="s-value s-info-total-value"><?= $total_surat_period ?></div></div>
                </div>
            </div>

            <?php if ($jenis_laporan === 'surat_masuk' || $jenis_laporan === 'total_surat'): ?>
                <div class="report-content report-content-masuk">
                    <div class="table-header"><h3>Daftar Surat Masuk Terselesaikan</h3><span class="badge badge-success"><?= $total_masuk_period ?> Dokumen</span></div>
                    <div class="table-container">
                        <table class="data-table">
                            <thead><tr><th>Identitas Surat</th><th>Pengirim</th><th>Tanggal Terima</th><th>Perihal</th><th class="action-th-center">Aksi</th></tr></thead>
                            <tbody>
                                <?php if (empty($report_masuk)): ?>
                                    <tr><td colspan="5" class="empty-table-row">Tidak ada data surat masuk.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($report_masuk as $m): ?>
                                        <tr>
                                            <td><div class="user-no-text"><?= htmlspecialchars($m['nomor_surat'] ?? '') ?></div><div class="agenda-subtext">Agenda: <?= htmlspecialchars($m['nomor_agenda'] ?? '-') ?></div></td>
                                            <td><?= htmlspecialchars($m['pengirim'] ?? '-') ?></td>
                                            <td><b class="date-bold-text"><?= date('d/m/Y', strtotime($m['tanggal_terima'] ?? 'now')) ?></b></td>
                                            <td>
                                                <div class="perihal-main-text"><?= htmlspecialchars($m['perihal'] ?? '') ?></div>
                                                <?php if (!empty($m['reply_no'])): ?>
                                                    <div class="reply-info-text"><svg class="icon reply-info-icon"><polyline points="20 6 9 17 4 12"></polyline></svg> Dibalas: <?= htmlspecialchars($m['reply_no']) ?></div>
                                                <?php endif; ?>
                                            </td>
                                            <td class="action-cell">
                                                <div class="action-btns action-btns-flex">
                                                    <?php if ($m['file_path']): ?><a href="../<?= htmlspecialchars($m['file_path']) ?>" target="_blank" class="action-btn btn-view" title="Lihat Surat"><svg class="icon" viewBox="0 0 24 24"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg></a><?php endif; ?>
                                                    <?php if (!empty($m['id_surat_keluar'])): ?>
                                                        <a href="<?= !empty($m['reply_file']) ? '../uploads/surat_keluar/' . htmlspecialchars($m['reply_file']) : '#' ?>" 
                                                           target="<?= !empty($m['reply_file']) ? '_blank' : '_self' ?>" 
                                                           class="action-btn" 
                                                           style="background:#10b981;" 
                                                           title="Lihat Balasan (<?= htmlspecialchars($m['reply_no']) ?>)">
                                                            <svg class="icon" viewBox="0 0 24 24"><path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/></svg>
                                                        </a>
                                                    <?php endif; ?>
                                                    <button class="action-btn btn-edit btn-edit-custom" title="Edit" onclick='openEditMasuk(<?= json_encode($m) ?>)'><svg class="icon" viewBox="0 0 24 24"><path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/><path d="m15 5 4 4"/></svg></button>
                                                    <a href="?delete_id=<?= $m['id_surat_masuk'] ?>&type=masuk" class="action-btn btn-delete btn-delete-custom" title="Hapus" onclick="return confirm('Yakin ingin menghapus arsip ini?')"><svg class="icon" viewBox="0 0 24 24"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/><line x1="10" x2="10" y1="11" y2="17"/><line x1="14" x2="14" y1="11" y2="17"/></svg></a>
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
                            <thead><tr><th>Identitas Surat</th><th>Tujuan</th><th>Tanggal Surat</th><th>Perihal</th><th class="action-th-center">Aksi</th></tr></thead>
                            <tbody>
                                <?php if (empty($report_keluar)): ?>
                                    <tr><td colspan="5" class="empty-table-row">Tidak ada data surat keluar.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($report_keluar as $k): ?>
                                        <tr>
                                            <td><div class="user-no-text"><?= htmlspecialchars($k['nomor_surat_keluar'] ?? '') ?></div></td>
                                            <td><?= htmlspecialchars($k['tujuan'] ?? '-') ?></td>
                                            <td><b class="date-bold-text"><?= date('d/m/Y', strtotime($k['tanggal_surat'] ?? 'now')) ?></b></td>
                                            <td><?= htmlspecialchars($k['perihal'] ?? '') ?></td>
                                            <td class="action-cell">
                                                <div class="action-btns action-btns-flex">
                                                    <?php if ($k['file_path']): ?><a href="../uploads/surat_keluar/<?= htmlspecialchars($k['file_path']) ?>" target="_blank" class="action-btn btn-view" title="Lihat Dokumen"><svg class="icon" viewBox="0 0 24 24"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg></a><?php endif; ?>
                                                    <button class="action-btn btn-edit btn-edit-custom" title="Edit" onclick='openEditKeluar(<?= json_encode($k) ?>)'><svg class="icon" viewBox="0 0 24 24"><path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/><path d="m15 5 4 4"/></svg></button>
                                                    <a href="?delete_id=<?= $k['id_surat_keluar'] ?>&type=keluar" class="action-btn btn-delete btn-delete-custom" title="Hapus" onclick="return confirm('Yakin ingin menghapus arsip ini?')"><svg class="icon" viewBox="0 0 24 24"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/><line x1="10" x2="10" y1="11" y2="17"/><line x1="14" x2="14" y1="11" y2="17"/></svg></a>
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

    <!-- ====== MODALS ====== -->
    
    <!-- Modal: Add Laporan -->
    <div id="addModal" class="modal-overlay">
        <div class="modal-card">
            <div class="modal-header"><h2>Tambah Arsip Baru</h2><button class="btn-close" onclick="closeAddModal()">✕</button></div>
            <div class="add-modal-tabs">
                <button class="btn btn-primary tab-btn-flex" id="btnTabMasuk" onclick="switchAddTab('masuk')">Surat Masuk</button>
                <button class="btn tab-btn-flex" id="btnTabKeluar" onclick="switchAddTab('keluar')">Surat Keluar</button>
            </div>
            
            <form id="formMasuk" method="POST" class="add-tab-content">
                <input type="hidden" name="action" value="create_masuk">
                <div class="form-grid">
                    <div class="form-group full-width"><label>Nomor Surat</label><input type="text" name="nomor_surat" required></div>
                    <div class="form-group full-width"><label>Pengirim</label><input type="text" name="pengirim" required></div>
                    <div class="form-group full-width"><label>Perihal</label><input type="text" name="perihal" required></div>
                    <div class="form-group full-width"><label>Tanggal Terima</label><input type="date" name="tanggal_terima" value="<?= date('Y-m-d') ?>" required></div>
                </div>
                <div class="modal-footer-align"><button type="submit" class="btn btn-primary">Simpan Arsip</button></div>
            </form>

            <form id="formKeluar" method="POST" class="add-tab-content" style="display:none;">
                <input type="hidden" name="action" value="create_keluar">
                <div class="form-grid">
                    <div class="form-group full-width"><label>Nomor Surat Keluar</label><input type="text" name="nomor_surat_keluar" required></div>
                    <div class="form-group full-width"><label>Tujuan</label><input type="text" name="tujuan" required></div>
                    <div class="form-group full-width"><label>Perihal</label><input type="text" name="perihal" required></div>
                    <div class="form-group full-width"><label>Tanggal Surat</label><input type="date" name="tanggal_surat" value="<?= date('Y-m-d') ?>" required></div>
                </div>
                <div class="modal-footer-align"><button type="submit" class="btn btn-primary">Simpan Arsip</button></div>
            </form>
        </div>
    </div>

    <!-- Modal: Edit Surat Masuk -->
    <div id="editMasukModal" class="modal-overlay">
        <div class="modal-card">
            <div class="modal-header"><h2>Edit Arsip Surat Masuk</h2><button class="btn-close" onclick="closeEditMasuk()">✕</button></div>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="update_masuk">
                <input type="hidden" name="id_surat_masuk" id="edit_id_masuk">
                <input type="hidden" name="existing_file_path" id="edit_file_masuk">
                <div class="form-grid">
                    <div class="form-group full-width"><label>Nomor Surat</label><input type="text" name="nomor_surat" id="edit_no_masuk" required></div>
                    <div class="form-group full-width"><label>Pengirim</label><input type="text" name="pengirim" id="edit_pengirim_masuk" required></div>
                    <div class="form-group full-width"><label>Perihal</label><input type="text" name="perihal" id="edit_perihal_masuk" required></div>
                    <div class="form-group"><label>Tanggal Terima</label><input type="date" name="tanggal_terima" id="edit_tgl_masuk" required></div>
                    <div class="form-group"><label>Ganti File (Optional)</label><input type="file" name="file_surat"></div>
                </div>
                <div class="modal-footer-align"><button type="submit" class="btn btn-primary">Update Data</button></div>
            </form>
        </div>
    </div>

    <!-- Modal: Edit Surat Keluar -->
    <div id="editKeluarModal" class="modal-overlay">
        <div class="modal-card">
            <div class="modal-header"><h2>Edit Arsip Surat Keluar</h2><button class="btn-close" onclick="closeEditKeluar()">✕</button></div>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="update_keluar">
                <input type="hidden" name="id_surat_keluar" id="edit_id_keluar">
                <input type="hidden" name="existing_file_path" id="edit_file_keluar">
                <div class="form-grid">
                    <div class="form-group full-width"><label>Nomor Surat</label><input type="text" name="nomor_surat_keluar" id="edit_no_keluar" required></div>
                    <div class="form-group full-width"><label>Tujuan</label><input type="text" name="tujuan" id="edit_tujuan_keluar" required></div>
                    <div class="form-group full-width"><label>Perihal</label><input type="text" name="perihal" id="edit_perihal_keluar" required></div>
                    <div class="form-group"><label>Tanggal Surat</label><input type="date" name="tanggal_surat" id="edit_tgl_keluar" required></div>
                    <div class="form-group"><label>Ganti File (Optional)</label><input type="file" name="file_surat"></div>
                </div>
                <div class="modal-footer-align"><button type="submit" class="btn btn-primary">Update Data</button></div>
            </form>
        </div>
    </div>

    <script>
        // Modal Handlers
        function openAddModal() { document.getElementById('addModal').classList.add('active'); switchAddTab('masuk'); }
        function closeAddModal() { document.getElementById('addModal').classList.remove('active'); }
        
        function switchAddTab(tab) {
            const isMasuk = tab === 'masuk';
            document.getElementById('formMasuk').style.display = isMasuk ? 'block' : 'none';
            document.getElementById('formKeluar').style.display = isMasuk ? 'none' : 'block';
            document.getElementById('btnTabMasuk').className = isMasuk ? 'btn btn-primary' : 'btn';
            document.getElementById('btnTabKeluar').className = isMasuk ? 'btn' : 'btn btn-primary';
        }

        function openEditMasuk(data) {
            document.getElementById('edit_id_masuk').value = data.id_surat_masuk;
            document.getElementById('edit_no_masuk').value = data.nomor_surat;
            document.getElementById('edit_pengirim_masuk').value = data.pengirim;
            document.getElementById('edit_perihal_masuk').value = data.perihal;
            document.getElementById('edit_tgl_masuk').value = data.tanggal_terima;
            document.getElementById('edit_file_masuk').value = data.file_path || '';
            document.getElementById('editMasukModal').classList.add('active');
        }
        function closeEditMasuk() { document.getElementById('editMasukModal').classList.remove('active'); }

        function openEditKeluar(data) {
            document.getElementById('edit_id_keluar').value = data.id_surat_keluar;
            document.getElementById('edit_no_keluar').value = data.nomor_surat_keluar;
            document.getElementById('edit_tujuan_keluar').value = data.tujuan;
            document.getElementById('edit_perihal_keluar').value = data.perihal;
            document.getElementById('edit_tgl_keluar').value = data.tanggal_surat;
            document.getElementById('edit_file_keluar').value = data.file_path || '';
            document.getElementById('editKeluarModal').classList.add('active');
        }
        function closeEditKeluar() { document.getElementById('editKeluarModal').classList.remove('active'); }

        window.onclick = e => { if (e.target.classList.contains('modal-overlay')) e.target.classList.remove('active'); };
    </script>
    <script src="../js/notifications.js"></script>
</body>
</html>
