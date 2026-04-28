<?php
session_start();
require_once '../config/db.php';

// Auth Check
if (!isset($_SESSION['user_nip']) || $_SESSION['user_role'] !== 'sekretariat') {
    header('Location: ../auth/login.php');
    exit;
}

$nip = $_SESSION['user_nip'];
$success_msg = $_SESSION['success_msg'] ?? "";
$error_msg = $_SESSION['error_msg'] ?? "";

// Clear messages after reading
unset($_SESSION['success_msg'], $_SESSION['error_msg']);

// --- HANDLE FORM SUBMISSION (INPUT) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_mail') {
    // Generasi Nomor Agenda Otomatis
    $date_prefix = 'ARS-' . date('Ymd') . '-';
    $stmt_last = $pdo->prepare("SELECT nomor_agenda FROM surat_masuk WHERE nomor_agenda LIKE ? ORDER BY nomor_agenda DESC LIMIT 1");
    $stmt_last->execute([$date_prefix . '%']);
    $last_agenda = $stmt_last->fetchColumn();
    if ($last_agenda) {
        $last_num = (int) substr((string)$last_agenda, -4);
        $new_num = str_pad($last_num + 1, 4, '0', STR_PAD_LEFT);
    } else {
        $new_num = '0001';
    }
    $nomor_agenda = $date_prefix . $new_num;
    $nomor_surat = $_POST['nomor_surat'] ?? '';
    $tanggal_surat = $_POST['tanggal_surat'] ?? date('Y-m-d');
    $tanggal_terima = $_POST['tanggal_terima'] ?? date('Y-m-d');
    $pengirim = $_POST['pengirim'] ?? '';
    $perihal = $_POST['perihal'] ?? '';
    $sifat_surat = $_POST['sifat_surat'] ?? 'biasa';
    $lampiran = (int) ($_POST['lampiran'] ?? 0);
    $keterangan = $_POST['keterangan'] ?? '';

    // File Upload handling
    $file_path = null;
    $upload_error = false;

    if (isset($_FILES['file_surat']) && $_FILES['file_surat']['name'] !== '') {
        if ($_FILES['file_surat']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../uploads/surat_masuk/';
            if (!is_dir($upload_dir))
                mkdir($upload_dir, 0777, true);

            $file_extension = pathinfo($_FILES['file_surat']['name'], PATHINFO_EXTENSION);
            $filename = time() . '_' . preg_replace("/[^a-zA-Z0-9]/", "_", (string)$perihal) . '.' . $file_extension;
            $target_file = $upload_dir . $filename;

            if (move_uploaded_file($_FILES['file_surat']['tmp_name'], $target_file)) {
                $file_path = 'uploads/surat_masuk/' . $filename;
            }
        } else {
            $upload_error = true;
            if ($_FILES['file_surat']['error'] === UPLOAD_ERR_INI_SIZE || $_FILES['file_surat']['error'] === UPLOAD_ERR_FORM_SIZE) {
                $error_msg = "Ukuran file terlalu besar! Silakan perkecil ukuran file atau kompres terlebih dahulu.";
            } else {
                $error_msg = "Terjadi kesalahan saat mengunggah file (Kode: " . $_FILES['file_surat']['error'] . ")";
            }
        }
    }

    if (!$upload_error) {
        try {
            $stmt = $pdo->prepare("INSERT INTO surat_masuk (nomor_agenda, nomor_surat, tanggal_surat, tanggal_terima, pengirim, perihal, sifat_surat, lampiran, file_path, input_by, status, keterangan) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'tercatat', ?)");
            $stmt->execute([$nomor_agenda, $nomor_surat, $tanggal_surat, $tanggal_terima, $pengirim, $perihal, $sifat_surat, $lampiran, $file_path, $nip, $keterangan]);
            $_SESSION['success_msg'] = "Surat masuk berhasil disimpan!";
            
            // Notification Logic
            require_once '../shared/notification_helper.php';
            notifyKadin($pdo, "Surat Masuk Baru: $perihal ($nomor_surat)", "surat_masuk.php");
            header("Location: surat_masuk.php");
            exit;
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                $_SESSION['error_msg'] = "Nomor Agenda sudah terdaftar dalam sistem!";
            } else {
                $_SESSION['error_msg'] = "Gagal menyimpan surat: " . $e->getMessage();
            }
            header("Location: surat_masuk.php");
            exit;
        }
    } else {
        $_SESSION['error_msg'] = $error_msg;
        header("Location: surat_masuk.php");
        exit;
    }
}

// --- HANDLE DELETE ---
if (isset($_GET['delete_id']) && is_numeric($_GET['delete_id'])) {
    $del_id = (int) $_GET['delete_id'];
    // Only allow delete if status is 'tercatat' (not yet processed)
    $check = $pdo->prepare("SELECT file_path, status FROM surat_masuk WHERE id_surat_masuk = ?");
    $check->execute([$del_id]);
    $del_row = $check->fetch();
    // Allow delete if 'tercatat' or just 'didispokan' (to Kadin) but not yet processed by internal sectors
    if ($del_row && in_array($del_row['status'], ['tercatat', 'didispokan'])) {
        // Delete file if exists
        if ($del_row['file_path'] && file_exists('../' . $del_row['file_path'])) {
            unlink('../' . $del_row['file_path']);
        }
        $pdo->prepare("DELETE FROM surat_masuk WHERE id_surat_masuk = ?")->execute([$del_id]);
        $_SESSION['success_msg'] = "Surat masuk berhasil dihapus.";
    } else {
        $_SESSION['error_msg'] = "Surat yang sudah diproses oleh Bidang tidak dapat dihapus.";
    }
    header("Location: surat_masuk.php");
    exit;
}

// --- HANDLE EDIT (UPDATE) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_mail') {
    $edit_id = (int) $_POST['edit_id'];
    $nomor_agenda = $_POST['nomor_agenda'] ?? '';
    $nomor_surat = $_POST['nomor_surat'] ?? '';
    $tanggal_surat = $_POST['tanggal_surat'] ?? date('Y-m-d');
    $tanggal_terima = $_POST['tanggal_terima'] ?? date('Y-m-d');
    $pengirim = $_POST['pengirim'] ?? '';
    $perihal = $_POST['perihal'] ?? '';
    $sifat_surat = $_POST['sifat_surat'] ?? 'biasa';
    $lampiran = (int) ($_POST['lampiran'] ?? 0);
    $keterangan = $_POST['keterangan'] ?? '';

    // Check if new file uploaded
    $new_file_path = $_POST['existing_file_path'] ?? null;
    if (isset($_FILES['file_surat']) && $_FILES['file_surat']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../uploads/surat_masuk/';
        if (!is_dir($upload_dir))
            mkdir($upload_dir, 0777, true);
        $ext = pathinfo($_FILES['file_surat']['name'], PATHINFO_EXTENSION);
        $fname = time() . '_' . preg_replace('/[^a-zA-Z0-9]/', '_', (string)$perihal) . '.' . $ext;
        if (move_uploaded_file($_FILES['file_surat']['tmp_name'], $upload_dir . $fname)) {
            // Delete old file
            if ($new_file_path && file_exists('../' . $new_file_path))
                unlink('../' . $new_file_path);
            $new_file_path = 'uploads/surat_masuk/' . $fname;
        }
    }

    try {
        $stmt = $pdo->prepare("UPDATE surat_masuk SET nomor_agenda=?, nomor_surat=?, tanggal_surat=?, tanggal_terima=?, pengirim=?, perihal=?, sifat_surat=?, lampiran=?, keterangan=?, file_path=? WHERE id_surat_masuk=?");
        $stmt->execute([$nomor_agenda, $nomor_surat, $tanggal_surat, $tanggal_terima, $pengirim, $perihal, $sifat_surat, $lampiran, $keterangan, $new_file_path, $edit_id]);
        $_SESSION['success_msg'] = "Data surat berhasil diperbarui!";
    } catch (PDOException $e) {
        $_SESSION['error_msg'] = "Gagal memperbarui: " . $e->getMessage();
    }
    header("Location: surat_masuk.php");
    exit;
}

// --- FETCH DATA FOR TABLE (DAFTAR & RIWAYAT) ---
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$sifat_filter = $_GET['sifat'] ?? '';

$query = "SELECT * FROM surat_masuk WHERE (perihal LIKE ? OR nomor_surat LIKE ? OR pengirim LIKE ?)";
$params = ["%$search%", "%$search%", "%$search%"];

if (!empty($status_filter)) {
    $query .= " AND status = ?";
    $params[] = $status_filter;
}

if (!empty($sifat_filter)) {
    $query .= " AND sifat_surat = ?";
    $params[] = $sifat_filter;
}

$query .= " ORDER BY created_at DESC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$all_mails = $stmt->fetchAll();
$mails = [];
$riwayat_mails = [];
foreach ($all_mails as $m) {
    if (in_array($m['status'], ['selesai', 'diteruskan', 'diarsipkan'])) {
        $riwayat_mails[] = $m;
    } else {
        $mails[] = $m;
    }
}

// --- FETCH SURAT TUGAS (DISPOSISI KADIN TO SEKRETARIAT UMUM) ---
$id_bidang_sekretariat = 8;
$query_tugas = "SELECT sm.*, d.isi_disposisi as instruksi_kadin, d.tanggal_disposisi as tgl_dispo_kadin, d.id_disposisi
              FROM surat_masuk sm 
              JOIN disposisi d ON sm.id_surat_masuk = d.id_surat_masuk
              WHERE d.id_bidang = ? 
              AND d.nip_pemberi IN (SELECT nip FROM users WHERE role = 'kepala_dinas')
              AND sm.status = 'didispokan'
              AND (sm.perihal LIKE ? OR sm.nomor_surat LIKE ? OR sm.pengirim LIKE ?)
              ORDER BY sm.created_at DESC";
$stmt_tugas = $pdo->prepare($query_tugas);
$stmt_tugas->execute([$id_bidang_sekretariat, "%$search%", "%$search%", "%$search%"]);
$tugas_mails = $stmt_tugas->fetchAll();

// --- HANDLE ACTION: ARSIP TUGAS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'archive_tugas') {
    $id_target = $_POST['id_surat'];
    try {
        $stmt = $pdo->prepare("UPDATE surat_masuk SET status = 'selesai', id_bidang = ? WHERE id_surat_masuk = ?");
        $stmt->execute([$id_bidang_sekretariat, $id_target]);
        $_SESSION['success_msg'] = "Surat berhasil diarsipkan ke Sekretariat Umum.";
    } catch (PDOException $e) {
        $_SESSION['error_msg'] = "Gagal mengarsipkan: " . $e->getMessage();
    }
    header("Location: surat_masuk.php");
    exit;
}

// Pre-calculate next agenda number for the Add Form display
$date_prefix = 'ARS-' . date('Ymd') . '-';
$stmt_last = $pdo->prepare("SELECT nomor_agenda FROM surat_masuk WHERE nomor_agenda LIKE ? ORDER BY nomor_agenda DESC LIMIT 1");
$stmt_last->execute([$date_prefix . '%']);
$last_agenda = $stmt_last->fetchColumn();
$new_num = $last_agenda ? str_pad(((int) substr((string)$last_agenda, -4)) + 1, 4, '0', STR_PAD_LEFT) : '0001';
$next_agenda_number = $date_prefix . $new_num;

// Fetch Admin Data for profile
$stmt = $pdo->prepare("SELECT * FROM users WHERE nip = ?");
$stmt->execute([$nip]);
$admin = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Surat Masuk - Arsip Digital</title>
    <link rel="stylesheet" href="../css/sekretariat/home.css">
    <link rel="stylesheet" href="../css/sekretariat/surat_masuk.css">
    <link rel="stylesheet" href="../css/notifications.css">
</head>
<body>
    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path></svg>
            <h2>ARSIP DIGITAL</h2>
        </div>
        <nav class="sidebar-menu">
            <div class="menu-label">Menu Utama</div>
            <a href="home.php" class="menu-item"><svg class="icon" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg> Dashboard</a>
            <div class="menu-label">Buku Agenda</div>
            <a href="surat_masuk.php" class="menu-item active"><svg class="icon" viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg> Surat Masuk</a>
            <a href="surat_keluar.php" class="menu-item"><svg class="icon" viewBox="0 0 24 24"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg> Surat Keluar</a>
            <div class="menu-label">Administrasi Sistem</div>
            <a href="manajemen_pengguna.php" class="menu-item"><svg class="icon" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg> Manajemen Pengguna</a>
            <a href="verifikasi_staff.php" class="menu-item"><svg class="icon" viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg> Verifikasi Staff</a>
            <a href="monitoring_surat.php" class="menu-item"><svg class="icon" viewBox="0 0 24 24"><path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"></path><polyline points="14 2 14 8 20 8"></polyline><path d="M16 13a2 2 0 1 1-4 0v-2a2 2 0 1 0-4 0"></path><line x1="12" y1="14" x2="12" y2="19"></line></svg> Monitoring Surat</a>
            <div class="menu-label">Monitoring</div>
            <a href="monitoring_laporan.php" class="menu-item"><svg class="icon" viewBox="0 0 24 24"><line x1="18" y1="20" x2="18" y2="10"></line><line x1="12" y1="20" x2="12" y2="4"></line><line x1="6" y1="20" x2="6" y2="14"></line></svg> Laporan</a>
            <div class="menu-label">Akun</div>
            <a href="profil.php" class="menu-item"><svg class="icon" viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg> Profil Saya</a>
        </nav>
        <div class="sidebar-footer"><a href="../auth/logout.php" class="logout-btn"><svg class="icon" viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg> Keluar Sistem</a></div>
    </aside>

    <main class="main-content">
        <header class="content-header">
            <div class="header-title"><h1>Manajemen Surat Masuk</h1></div>
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
                <div style="background: rgba(16, 185, 129, 0.1); color: var(--success); padding: 1rem; border-radius: 1rem; border: 1px solid var(--success); margin-bottom: 1.5rem; font-weight: 600;">
                    <svg class="icon" style="width: 18px; height: 18px; vertical-align: middle; margin-right: 0.5rem;"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg> <?= $success_msg ?>
                </div>
            <?php endif; ?>
            <?php if ($error_msg): ?>
                <div style="background: rgba(239, 68, 68, 0.1); color: var(--danger); padding: 1rem; border-radius: 1rem; border: 1px solid var(--danger); margin-bottom: 1.5rem; font-weight: 600;">
                    <svg class="icon" style="width: 18px; height: 18px; vertical-align: middle; margin-right: 0.5rem;"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg> <?= $error_msg ?>
                </div>
            <?php endif; ?>

            <!-- Module Tabs -->
            <div class="module-tabs">
                <button class="tab-btn active" onclick="switchTab('daftar')"><svg class="icon"><line x1="8" y1="6" x2="21" y2="6"></line><line x1="8" y1="12" x2="21" y2="12"></line><line x1="8" y1="18" x2="21" y2="18"></line><circle cx="3" cy="6" r="1"></circle><circle cx="3" cy="12" r="1"></circle><circle cx="3" cy="18" r="1"></circle></svg> Daftar Surat</button>
                <button class="tab-btn" onclick="switchTab('tugas')"><svg class="icon"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line></svg> Surat Tugas</button>
                <button class="tab-btn" onclick="switchTab('riwayat')"><svg class="icon"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg> Riwayat Selesai</button>
            </div>

            <!-- Section: Daftar Surat -->
            <section id="section-daftar" class="module-section active">
                <div class="card">
                    <div class="table-controls">
                        <div style="display: flex; gap: 1rem; flex-wrap: wrap; flex: 1;">
                            <form action="" method="GET" class="search-box">
                                <svg class="icon"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                                <input type="text" name="search" placeholder="Cari perihal, nomor surat atau pengirim..." value="<?= htmlspecialchars((string)$search) ?>">
                            </form>
                            <form action="" method="GET" class="filter-group">
                                <select name="status" onchange="this.form.submit()">
                                    <option value="">Semua Status</option>
                                    <option value="tercatat" <?= $status_filter === 'tercatat' ? 'selected' : '' ?>>Tercatat</option>
                                    <option value="didispokan" <?= $status_filter === 'didispokan' ? 'selected' : '' ?>>Dalam Proses</option>
                                </select>
                                <select name="sifat" onchange="this.form.submit()">
                                    <option value="">Semua Sifat</option>
                                    <option value="biasa" <?= $sifat_filter === 'biasa' ? 'selected' : '' ?>>Biasa</option>
                                    <option value="penting" <?= $sifat_filter === 'penting' ? 'selected' : '' ?>>Penting</option>
                                    <option value="segera" <?= $sifat_filter === 'segera' ? 'selected' : '' ?>>Segera</option>
                                </select>
                            </form>
                        </div>
                        <button class="btn btn-primary" onclick="openInputModal()"><svg class="icon"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg> Tambah Surat</button>
                    </div>

                    <div class="data-table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>No. Agenda</th>
                                    <th>Identitas Surat</th>
                                    <th>Pengirim</th>
                                    <th>Sifat</th>
                                    <th>Status</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($mails)): ?>
                                    <tr><td colspan="6" style="text-align: center; padding: 3rem; color: var(--text-muted);">Tidak ada agenda surat aktif.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($mails as $mail): ?>
                                        <tr>
                                            <td style="font-weight: 800; color: var(--primary);"><?= htmlspecialchars($mail['nomor_agenda'] ?? '') ?></td>
                                            <td>
                                                <div style="font-weight: 700;"><?= htmlspecialchars($mail['perihal'] ?? '') ?></div>
                                                <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 2px;">No: <?= htmlspecialchars($mail['nomor_surat'] ?? '') ?> • <?= date('d M Y', strtotime($mail['tanggal_terima'] ?? 'now')) ?></div>
                                            </td>
                                            <td><?= htmlspecialchars($mail['pengirim'] ?? '') ?></td>
                                            <td><span class="badge-status status-<?= $mail['sifat_surat'] ?>"><?= ucfirst($mail['sifat_surat'] ?? '') ?></span></td>
                                            <td><span class="badge-status status-<?= $mail['status'] ?>"><?= ucfirst($mail['status'] === 'tercatat' ? 'Tercatat' : 'Proses') ?></span></td>
                                            <td class="action-btns">
                                                <button class="action-btn btn-view" title="Lihat Detail" onclick='openViewModal(<?= htmlspecialchars(json_encode($mail)) ?>)'><svg class="icon"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg></button>
                                                <?php if ($mail['status'] === 'tercatat'): ?>
                                                    <button class="action-btn btn-edit" title="Edit" onclick='openEditModal(<?= htmlspecialchars(json_encode($mail)) ?>)'><svg class="icon"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 1 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg></button>
                                                    <a href="?delete_id=<?= $mail['id_surat_masuk'] ?>" class="action-btn btn-delete" title="Hapus" onclick="return confirm('Yakin ingin menghapus surat ini?')"><svg class="icon"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg></a>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

            <!-- Section: Surat Tugas -->
            <section id="section-tugas" class="module-section">
                <div class="card">
                    <div class="card-header"><h2>Agenda Surat Tugas (Sekretariat Umum)</h2><p>Daftar disposisi pimpinan untuk unit kerja Sekretariat.</p></div>
                    <div class="data-table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Terima</th>
                                    <th>Perihal & Instruksi</th>
                                    <th>Pengirim</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($tugas_mails)): ?>
                                    <tr><td colspan="4" style="text-align: center; padding: 4rem; color: var(--text-muted);">Tidak ada agenda surat tugas saat ini.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($tugas_mails as $m): ?>
                                        <tr>
                                            <td><b><?= date('d/m/Y', strtotime($m['tanggal_terima'] ?? 'now')) ?></b></td>
                                            <td>
                                                <div style="font-weight: 700; color: var(--primary);"><?= htmlspecialchars($m['perihal'] ?? '') ?></div>
                                                <div style="font-size: 0.8rem; color: #475569; background: #f1f5f9; padding: 0.5rem; border-radius: 0.5rem; margin-top: 0.5rem;">"<?= htmlspecialchars($m['instruksi_kadin'] ?? 'Segera tindak lanjuti') ?>"</div>
                                            </td>
                                            <td><?= htmlspecialchars($m['pengirim'] ?? '') ?></td>
                                            <td class="action-btns">
                                                <a href="disposisi_surat.php?id=<?= $m['id_surat_masuk'] ?>" class="action-btn btn-edit" title="Forward ke Staff"><svg class="icon"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg></a>
                                                <form action="" method="POST" style="display:inline;" onsubmit="return confirm('Arsipkan surat ini ke Sekretariat?')">
                                                    <input type="hidden" name="action" value="archive_tugas">
                                                    <input type="hidden" name="id_surat" value="<?= $m['id_surat_masuk'] ?>">
                                                    <button type="submit" class="action-btn btn-view" title="Selesaikan & Arsip"><svg class="icon"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path></svg></button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

            <!-- Section: Riwayat -->
            <section id="section-riwayat" class="module-section">
                <div class="card">
                    <div class="card-header"><h2>Riwayat Persuratan (Tuntas)</h2><p>Daftar arsip digital surat masuk yang telah selesai diproses.</p></div>
                    <div class="data-table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Agenda</th>
                                    <th>Identitas Surat</th>
                                    <th>Pengirim</th>
                                    <th>Status</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($riwayat_mails)): ?>
                                    <tr><td colspan="5" style="text-align: center; padding: 3rem; color: var(--text-muted);">Belum ada riwayat surat selesai.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($riwayat_mails as $mail): ?>
                                        <tr>
                                            <td style="font-weight: 700;"><?= htmlspecialchars($mail['nomor_agenda'] ?? '') ?></td>
                                            <td><div style="font-weight: 600;"><?= htmlspecialchars($mail['perihal'] ?? '') ?></div><div style="font-size: 0.75rem; color: var(--text-muted);">No: <?= htmlspecialchars($mail['nomor_surat'] ?? '') ?></div></td>
                                            <td><?= htmlspecialchars($mail['pengirim'] ?? '') ?></td>
                                            <td><span class="badge-status status-selesai">Selesai</span></td>
                                            <td class="action-btns"><button class="action-btn btn-view" title="Detail Arsip" onclick='openViewModal(<?= htmlspecialchars(json_encode($mail)) ?>)'><svg class="icon"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg></button></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        </div>
    </main>

    <!-- ====== MODAL: INPUT ====== -->
    <div id="inputModal" class="modal-overlay">
        <div class="modal-card">
            <div class="modal-header"><h2>Input Surat Masuk</h2><button class="btn-close" onclick="closeInputModal()">✕</button></div>
            <form action="" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="save_mail">
                <div class="form-grid">
                    <div class="form-group"><label>Nomor Agenda (Auto)</label><input type="text" name="nomor_agenda" value="<?= $next_agenda_number ?>" readonly style="background:#f1f5f9; cursor:not-allowed;"></div>
                    <div class="form-group"><label>Nomor Surat *</label><input type="text" name="nomor_surat" required placeholder="Contoh: 001/DISDIK/IV/2026"></div>
                    <div class="form-group"><label>Tanggal Surat *</label><input type="date" name="tanggal_surat" required></div>
                    <div class="form-group"><label>Tanggal Terima *</label><input type="date" name="tanggal_terima" value="<?= date('Y-m-d') ?>" required></div>
                    <div class="form-group full-width"><label>Pengirim *</label><input type="text" name="pengirim" required placeholder="Nama instansi atau perorangan"></div>
                    <div class="form-group full-width"><label>Perihal *</label><input type="text" name="perihal" required placeholder="Pokok isi surat"></div>
                    <div class="form-group"><label>Sifat Surat</label><select name="sifat_surat"><option value="biasa">Biasa</option><option value="penting">Penting</option><option value="segera">Segera</option><option value="rahasia">Rahasia</option></select></div>
                    <div class="form-group"><label>Lampiran (Lembar)</label><input type="number" name="lampiran" min="0" value="0"></div>
                    <div class="form-group full-width"><label>Dokumen Digital (PDF/IMG)</label><div class="file-upload-area" onclick="document.getElementById('file-input').click()"><svg class="icon" style="width:40px;height:40px;margin-bottom:1rem;"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="17 8 12 3 7 8"></polyline><line x1="12" y1="3" x2="12" y2="15"></line></svg><p id="file-name">Klik untuk memilih file dokumen</p><input type="file" id="file-input" name="file_surat" hidden onchange="validateFile(this)"></div></div>
                    <div class="form-group full-width"><label>Keterangan</label><textarea name="keterangan" placeholder="Catatan tambahan jika ada..."></textarea></div>
                </div>
                <div style="display:flex; justify-content:flex-end; gap:1rem; margin-top:2.5rem;"><button type="reset" class="btn">Reset</button><button type="submit" class="btn btn-primary">Simpan Agenda</button></div>
            </form>
        </div>
    </div>

    <!-- ====== MODAL: VIEW ====== -->
    <div id="viewModal" class="modal-overlay">
        <div class="modal-card">
            <div class="modal-header"><div id="vTitleBox"><h2 id="vPerihal"></h2><p id="vNo" style="font-size:0.9rem; color:var(--text-muted);"></p></div><button class="btn-close" onclick="closeViewModal()">✕</button></div>
            <div class="detail-grid">
                <div>
                    <div class="detail-item"><span class="detail-label">Pengirim</span><p id="vPengirim" class="detail-value"></p></div>
                    <div class="detail-item"><span class="detail-label">Tanggal Surat / Terima</span><p id="vTgl" class="detail-value"></p></div>
                    <div class="detail-item"><span class="detail-label">Sifat & Status</span><p id="vInfo" class="detail-value"></p></div>
                    <div class="detail-item"><span class="detail-label">Keterangan</span><p id="vKet" style="font-weight:500; font-style:italic; border-left:4px solid var(--border); padding-left:1rem; color:#475569;"></p></div>
                </div>
                <div id="vPreview" style="background:#f8fafc; border-radius:1rem; border:1px solid var(--border); min-height:300px; display:flex; align-items:center; justify-content:center;"></div>
            </div>
        </div>
    </div>

    <!-- ====== MODAL: EDIT ====== -->
    <div id="editModal" class="modal-overlay">
        <div class="modal-card">
            <div class="modal-header"><h2>Edit Agenda Surat</h2><button class="btn-close" onclick="closeEditModal()">✕</button></div>
            <form id="editForm" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="update_mail">
                <input type="hidden" name="edit_id" id="eId">
                <input type="hidden" name="existing_file_path" id="eFilePath">
                <div class="form-grid">
                    <div class="form-group"><label>Agenda</label><input type="text" name="nomor_agenda" id="eNomorAgenda" readonly style="background:#f1f5f9;"></div>
                    <div class="form-group"><label>No Surat</label><input type="text" name="nomor_surat" id="eNomorSurat"></div>
                    <div class="form-group"><label>Tgl Surat</label><input type="date" name="tanggal_surat" id="eTglSurat"></div>
                    <div class="form-group"><label>Tgl Terima</label><input type="date" name="tanggal_terima" id="eTglTerima"></div>
                    <div class="form-group full-width"><label>Pengirim</label><input type="text" name="pengirim" id="ePengirim"></div>
                    <div class="form-group full-width"><label>Perihal</label><input type="text" name="perihal" id="ePerihal"></div>
                    <div class="form-group"><label>Sifat</label><select name="sifat_surat" id="eSifat"><option value="biasa">Biasa</option><option value="penting">Penting</option><option value="segera">Segera</option><option value="rahasia">Rahasia</option></select></div>
                    <div class="form-group"><label>Lampiran</label><input type="number" name="lampiran" id="eLampiran"></div>
                    <div class="form-group full-width"><label>Update File (PDF/IMG)</label><input type="file" name="file_surat" class="p-input"></div>
                    <div class="form-group full-width"><label>Keterangan</label><textarea name="keterangan" id="eKet"></textarea></div>
                </div>
                <div style="display:flex; justify-content:flex-end; gap:1rem; margin-top:2.5rem;"><button type="button" class="btn" onclick="closeEditModal()">Batal</button><button type="submit" class="btn btn-primary">Simpan Perubahan</button></div>
            </form>
        </div>
    </div>

    <script>
        function switchTab(id) {
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.toggle('active', b.onclick.toString().includes(id)));
            document.querySelectorAll('.module-section').forEach(s => s.classList.toggle('active', s.id === 'section-' + id));
        }
        function openInputModal() { document.getElementById('inputModal').classList.add('active'); }
        function closeInputModal() { document.getElementById('inputModal').classList.remove('active'); }
        function openViewModal(m) {
            document.getElementById('vPerihal').innerText = m.perihal;
            document.getElementById('vNo').innerText = `No: ${m.nomor_surat} | Agenda: ${m.nomor_agenda}`;
            document.getElementById('vPengirim').innerText = m.pengirim;
            document.getElementById('vTgl').innerText = `${m.tanggal_surat} (Terima: ${m.tanggal_terima})`;
            document.getElementById('vInfo').innerText = `${m.sifat_surat.toUpperCase()} - ${m.status.toUpperCase()}`;
            document.getElementById('vKet').innerText = m.keterangan || 'Tidak ada keterangan tambahan.';
            const prev = document.getElementById('vPreview');
            prev.innerHTML = m.file_path ? (/\.(png|jpg|jpeg)$/i.test(m.file_path) ? `<img src="../${m.file_path}" style="max-width:100%; max-height:400px; border-radius:0.5rem;">` : `<iframe src="../${m.file_path}" style="width:100%; height:450px; border:none; border-radius:0.5rem;"></iframe>`) : '<p style="color:#94a3b8;">Tidak ada lampiran dokumen digital.</p>';
            document.getElementById('viewModal').classList.add('active');
        }
        function closeViewModal() { document.getElementById('viewModal').classList.remove('active'); }
        function openEditModal(m) {
            document.getElementById('eId').value = m.id_surat_masuk;
            document.getElementById('eNomorAgenda').value = m.nomor_agenda;
            document.getElementById('eNomorSurat').value = m.nomor_surat;
            document.getElementById('eTglSurat').value = m.tanggal_surat;
            document.getElementById('eTglTerima').value = m.tanggal_terima;
            document.getElementById('ePengirim').value = m.pengirim;
            document.getElementById('ePerihal').value = m.perihal;
            document.getElementById('eSifat').value = m.sifat_surat;
            document.getElementById('eLampiran').value = m.lampiran;
            document.getElementById('eKet').value = m.keterangan || '';
            document.getElementById('eFilePath').value = m.file_path || '';
            document.getElementById('editModal').classList.add('active');
        }
        function closeEditModal() { document.getElementById('editModal').classList.remove('active'); }
        function validateFile(i) { const f = i.files[0]; if (f && f.size > 10 * 1024 * 1024) { alert('Maksimal 10MB!'); i.value = ''; } else if (f) { document.getElementById('file-name').innerText = f.name; } }
        window.onclick = e => { if (e.target.classList.contains('modal-overlay')) e.target.classList.remove('active'); };
        <?php if ($success_msg || $error_msg): ?> switchTab('daftar'); <?php endif; ?>
    </script>
    <script src="../js/notifications.js"></script>
</body>
</html>